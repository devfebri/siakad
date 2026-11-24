<?php

namespace App\Support;

use App\Invoice;
use App\Langganan;
use App\LogAktivitas;
use App\Paket;
use App\Peran;
use App\Sekolah;
use App\Setting;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Alur sewa: membuat sekolah beserta admin pertama, invoice, dan konfirmasi pembayaran manual (membuat/memperpanjang langganan). */
class Penyewaan
{
    /**
     * Buat sekolah baru: data bawaan konsultasi online, nama di pengaturan situs, dan akun admin sekolah pertama.
     *
     * @param array{nama: string, npsn?: ?string, jenjang: string, subdomain: string, domain_kustom?: ?string, status?: string} $data
     * @param array{name: string, username: string, email: string, password: string} $admin
     */
    public static function buatSekolah(array $data, array $admin): Sekolah
    {
        return DB::transaction(function () use ($data, $admin) {
            $sekolah = Sekolah::create($data + ['status' => 'aktif']);

            SekolahAktif::jalankan($sekolah, function () use ($sekolah, $admin) {
                Setting::put(array_filter(['nama_sekolah' => $sekolah->nama, 'npsn' => $sekolah->npsn]));
                User::create([
                    'name' => $admin['name'], 'username' => $admin['username'], 'email' => $admin['email'],
                    'password' => $admin['password'], 'remember_token' => Str::random(60), 'role' => Peran::ADMIN,
                ]);
            });
            ChatDefault::pasang($sekolah);

            LogAktivitas::catat('sekolah.buat', "Sekolah {$sekolah->nama} dibuat (subdomain {$sekolah->subdomain}) dengan admin {$admin['username']}.", $sekolah->id);

            return $sekolah;
        });
    }

    /** Buat invoice dengan harga dari daftar harga paket. */
    public static function buatInvoice(Sekolah $sekolah, Paket $paket, int $durasiBulan, ?string $catatan = null, ?int $jatuhTempoHari = 7): Invoice
    {
        $harga = $paket->hargaUntuk($durasiBulan);
        if ($harga === null) {
            throw ValidationException::withMessages(['durasi_bulan' => "Paket {$paket->nama} belum punya harga untuk {$durasiBulan} bulan."]);
        }

        $invoice = Invoice::create([
            'nomor' => Invoice::nomorBerikut(),
            'sekolah_id' => $sekolah->id,
            'paket_id' => $paket->id,
            'durasi_bulan' => $durasiBulan,
            'jumlah' => $harga,
            'status' => 'menunggu',
            'jatuh_tempo' => $jatuhTempoHari !== null ? now()->addDays($jatuhTempoHari)->toDateString() : null,
            'catatan' => $catatan,
        ]);

        LogAktivitas::catat('invoice.buat', "Invoice {$invoice->nomor} ({$paket->nama}, {$durasiBulan} bulan) untuk {$sekolah->nama}.", $sekolah->id);

        return $invoice;
    }

    /**
     * Tandai invoice lunas lalu buat langganan: mulai hari ini, atau menyambung sehari setelah langganan yang masih berjalan berakhir.
     * Mengonfirmasi dua kali ditolak.
     */
    public static function konfirmasiLunas(Invoice $invoice, ?string $metode = null, ?string $catatan = null): Langganan
    {
        return DB::transaction(function () use ($invoice, $metode, $catatan) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'menunggu') {
                throw ValidationException::withMessages(['status' => 'Invoice ini sudah '.strtolower(Invoice::STATUS[$invoice->status] ?? $invoice->status).'.']);
            }

            $sekolah = Sekolah::findOrFail($invoice->sekolah_id);
            $terkini = $sekolah->langgananTerkini();
            $hariIni = Carbon::today();

            $mulai = ($terkini && $terkini->berakhir->gte($hariIni)) ? $terkini->berakhir->copy()->addDay() : $hariIni;
            $berakhir = $mulai->copy()->addMonthsNoOverflow($invoice->durasi_bulan)->subDay();

            $langganan = Langganan::create([
                'sekolah_id' => $sekolah->id, 'paket_id' => $invoice->paket_id, 'invoice_id' => $invoice->id,
                'durasi_bulan' => $invoice->durasi_bulan, 'mulai' => $mulai->toDateString(), 'berakhir' => $berakhir->toDateString(), 'status' => 'aktif',
            ]);

            $invoice->update([
                'status' => 'lunas', 'dibayar_pada' => now(), 'metode' => $metode ?: 'Transfer', 'catatan' => $catatan ?? $invoice->catatan,
                'dikonfirmasi_oleh' => auth()->id(), 'langganan_id' => $langganan->id,
            ]);

            LogAktivitas::catat('invoice.lunas', "Invoice {$invoice->nomor} dikonfirmasi lunas; langganan {$mulai->toDateString()} s.d. {$berakhir->toDateString()}.", $sekolah->id);

            return $langganan;
        });
    }

    public static function batalkanInvoice(Invoice $invoice): void
    {
        if ($invoice->status !== 'menunggu') {
            throw ValidationException::withMessages(['status' => 'Hanya invoice yang menunggu pembayaran yang dapat dibatalkan.']);
        }

        $invoice->update(['status' => 'batal']);
        LogAktivitas::catat('invoice.batal', "Invoice {$invoice->nomor} dibatalkan.", $invoice->sekolah_id);
    }
}
