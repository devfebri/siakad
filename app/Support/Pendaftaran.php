<?php

namespace App\Support;

use App\Langganan;
use App\LogAktivitas;
use App\Mail\VerifikasiPendaftaranMail;
use App\Paket;
use App\PendaftaranSekolah;
use App\PengaturanPlatform;
use App\Sekolah;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Pendaftaran sekolah mandiri: pesan verifikasi email, lalu (setelah diverifikasi) buat sekolah, admin, dan trial atau invoice. */
class Pendaftaran
{
    public const BERLAKU_JAM = 24;

    /** Apakah trial sedang ditawarkan platform. */
    public static function trialDitawarkan(): bool
    {
        return PengaturanPlatform::aktif('trial_aktif') && self::trialHari() > 0;
    }

    public static function trialHari(): int
    {
        return max(0, min(90, (int) PengaturanPlatform::get('trial_hari', 14)));
    }

    /** Trial hanya sekali per email dan per NPSN. */
    public static function trialBoleh(string $email, ?string $npsn): bool
    {
        if (! self::trialDitawarkan()) {
            return false;
        }

        $email = mb_strtolower($email);
        $pernah = PendaftaranSekolah::whereNotNull('diverifikasi_pada')->where('pakai_trial', true)
            ->where(fn ($q) => $q->where('email', $email)->when($npsn, fn ($q) => $q->orWhere('npsn', $npsn)))->exists();

        $sekolahNpsn = $npsn ? Sekolah::where('npsn', $npsn)->pluck('id') : collect();
        $pernahLangganan = $sekolahNpsn->isNotEmpty() && Langganan::where('trial', true)->whereIn('sekolah_id', $sekolahNpsn)->exists();

        return ! $pernah && ! $pernahLangganan;
    }

    /** Apakah subdomain masih bebas (tidak dipakai sekolah, tidak dicadangkan, tidak sedang ditahan pendaftaran lain yang belum kedaluwarsa). */
    public static function subdomainBebas(string $subdomain): bool
    {
        $subdomain = mb_strtolower($subdomain);

        return ! in_array($subdomain, Sekolah::SUBDOMAIN_DICADANGKAN, true)
            && ! Sekolah::where('subdomain', $subdomain)->exists()
            && ! PendaftaranSekolah::menunggu()->where('subdomain', $subdomain)->exists();
    }

    public static function aturan(): array
    {
        return [
            'nama' => ['required', 'string', 'min:3', 'max:150'],
            'npsn' => ['nullable', 'string', 'regex:/^[0-9]{6,20}$/'],
            'jenjang' => ['required', Rule::in(Sekolah::JENJANG)],
            'subdomain' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn(Sekolah::SUBDOMAIN_DICADANGKAN), 'unique:sekolah,subdomain'],
            'paket_id' => ['required', 'integer', Rule::exists('paket', 'id')->where('aktif', true)],
            'durasi_bulan' => ['required', 'integer', Rule::in(Paket::DURASI)],
            'admin_name' => ['required', 'string', 'min:2', 'max:100'],
            'admin_email' => ['required', 'email', 'max:191'],
            'admin_username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'],
            'admin_password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'pakai_trial' => ['nullable', 'boolean'],
            'setuju' => ['accepted'],
        ];
    }

    public static function pesan(): array
    {
        return [
            'subdomain.regex' => 'Alamat hanya boleh huruf kecil, angka, dan strip (tidak di awal atau akhir).',
            'subdomain.not_in' => 'Alamat ini tidak bisa dipakai. Pilih yang lain.',
            'subdomain.unique' => 'Alamat ini sudah dipakai sekolah lain. Pilih yang lain.',
            'admin_username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.',
            'admin_password.confirmed' => 'Pengulangan kata sandi tidak sama.',
            'admin_password.min' => 'Kata sandi minimal 8 karakter.',
            'npsn.regex' => 'NPSN hanya angka (6–20 digit).',
            'setuju.accepted' => 'Centang persetujuan untuk melanjutkan.',
        ];
    }

    public static function atribut(): array
    {
        return [
            'nama' => 'nama sekolah', 'subdomain' => 'alamat situs', 'paket_id' => 'paket', 'durasi_bulan' => 'lama langganan',
            'admin_name' => 'nama admin', 'admin_email' => 'email', 'admin_username' => 'username', 'admin_password' => 'kata sandi',
        ];
    }

    /** Simpan pendaftaran (menunggu verifikasi) dan kirim email. Pendaftaran lama dengan email yang sama yang belum diverifikasi diganti. */
    public static function mulai(array $data, ?string $ip = null): PendaftaranSekolah
    {
        $email = mb_strtolower(trim($data['admin_email']));
        $subdomain = mb_strtolower(trim($data['subdomain']));

        if (! self::subdomainBebas($subdomain) && ! PendaftaranSekolah::menunggu()->where('subdomain', $subdomain)->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['subdomain' => 'Alamat ini sudah dipakai sekolah lain. Pilih yang lain.']);
        }
        if (User::withoutGlobalScopes()->where('email', $email)->whereHas('peran', fn ($q) => $q->where('kode', 'admin'))->exists()) {
            throw ValidationException::withMessages(['admin_email' => 'Email ini sudah terdaftar sebagai admin sekolah. Masuk ke sekolah Anda, atau pakai email lain.']);
        }

        $token = Str::random(64);
        $pakaiTrial = ! empty($data['pakai_trial']) && self::trialBoleh($email, $data['npsn'] ?? null);

        $pendaftaran = DB::transaction(function () use ($data, $email, $subdomain, $token, $pakaiTrial, $ip) {
            PendaftaranSekolah::whereNull('diverifikasi_pada')->where('email', $email)->delete();

            return PendaftaranSekolah::create([
                'token_hash' => hash('sha256', $token), 'email' => $email, 'subdomain' => $subdomain, 'npsn' => $data['npsn'] ?? null,
                'pakai_trial' => $pakaiTrial, 'kedaluwarsa_pada' => now()->addHours(self::BERLAKU_JAM), 'ip' => $ip,
                'data' => [
                    'nama' => trim(strip_tags($data['nama'])), 'jenjang' => $data['jenjang'], 'paket_id' => (int) $data['paket_id'], 'durasi_bulan' => (int) $data['durasi_bulan'],
                    'admin' => ['name' => trim(strip_tags($data['admin_name'])), 'username' => mb_strtolower($data['admin_username']), 'email' => $email, 'password' => Hash::make($data['admin_password'])],
                ],
            ]);
        });

        Mail::to($email)->send(new VerifikasiPendaftaranMail($pendaftaran, route('daftar.verifikasi', $token)));

        return $pendaftaran;
    }

    /**
     * Verifikasi email lalu buat sekolah. Token dipakai sekali; tautan yang sudah dipakai atau kedaluwarsa menghasilkan null.
     *
     * @return array{sekolah: Sekolah, trial: bool, invoice: ?\App\Invoice, pendaftaran: PendaftaranSekolah}|null
     */
    public static function verifikasi(string $token): ?array
    {
        return DB::transaction(function () use ($token) {
            $p = PendaftaranSekolah::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $p || $p->diverifikasi_pada || $p->kedaluwarsa_pada->isPast()) {
                return null;
            }

            $d = $p->data;
            $subdomain = $p->subdomain;
            if (Sekolah::where('subdomain', $subdomain)->exists()) {
                return null; // diambil pihak lain di antara pendaftaran dan verifikasi
            }

            $sekolah = Penyewaan::buatSekolah(
                ['nama' => $d['nama'], 'npsn' => $p->npsn, 'jenjang' => $d['jenjang'], 'subdomain' => $subdomain],
                $d['admin'],
            );

            $paket = Paket::with('harga')->find($d['paket_id']);
            $invoice = null;
            $trial = $p->pakai_trial && self::trialBoleh($p->email, $p->npsn);

            if ($paket && $trial) {
                $hari = self::trialHari();
                Langganan::create([
                    'sekolah_id' => $sekolah->id, 'paket_id' => $paket->id, 'durasi_bulan' => 1, 'mulai' => today()->toDateString(),
                    'berakhir' => today()->addDays($hari - 1)->toDateString(), 'status' => 'aktif', 'trial' => true, 'catatan' => "Percobaan gratis {$hari} hari",
                ]);
            } elseif ($paket && $paket->hargaUntuk($d['durasi_bulan']) !== null) {
                $invoice = Penyewaan::buatInvoice($sekolah, $paket, $d['durasi_bulan'], 'Pendaftaran mandiri');
            }

            $p->update(['diverifikasi_pada' => now(), 'sekolah_id' => $sekolah->id, 'pakai_trial' => $trial]);
            LogAktivitas::catat('sekolah.daftar_mandiri', "Pendaftaran mandiri {$sekolah->nama} ({$subdomain}) diverifikasi".($trial ? ' dengan trial.' : '.'), $sekolah->id);

            return ['sekolah' => $sekolah, 'trial' => $trial, 'invoice' => $invoice, 'pendaftaran' => $p];
        });
    }
}
