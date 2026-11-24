<?php

namespace App\Support;

use App\Http\Controllers\OrangTuaController;
use App\Http\Controllers\SiswaController;
use App\Kelas;
use App\LogAktivitas;
use App\OrangTua;
use App\PpdbPendaftar;
use App\Siswa;
use App\User;
use App\Wa\NomorWa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menjadikan pendaftar PPDB yang DITERIMA sebagai siswa, lengkap dengan akun orang tua (dihubungkan ke anak).
 * rencana() menghitung apa yang akan dibuat beserta masalahnya (dipakai layar pratinjau); konversi() mengerjakannya dalam satu transaksi.
 */
class PpdbKonversi
{
    /**
     * @param array{agama?: ?string, nisn?: ?string, rombel_id?: ?int, buat_ortu?: bool} $input
     * @return array{
     *   galat: array<int, string>, peringatan: array<int, string>, agama: ?string, nisn: string, nisn_sementara: bool, rombel_id: ?int, username: string,
     *   ortu: array{mode: string, nama: string, no_hp: string, ortu: ?OrangTua, alasan: ?string}
     * }
     */
    public static function rencana(PpdbPendaftar $p, array $input = []): array
    {
        $galat = [];
        $peringatan = [];

        if ($p->siswa_id) {
            $galat[] = 'Pendaftar ini sudah dijadikan siswa.';
        } elseif ($p->status !== 'diterima') {
            $galat[] = 'Hanya pendaftar berstatus Diterima yang bisa dijadikan siswa.';
        }

        $agama = trim((string) ($input['agama'] ?? '')) ?: $p->agama;
        if (! $agama) {
            $galat[] = 'Agama belum diisi. Pilih agama calon siswa.';
        } elseif (! in_array($agama, PpdbPendaftar::AGAMA, true)) {
            $galat[] = 'Agama tidak dikenal.';
            $agama = null;
        }

        $nisnInput = trim((string) ($input['nisn'] ?? '')) ?: (string) $p->nisn;
        $sementara = $nisnInput === '';
        $nisn = $sementara ? $p->no_pendaftaran : $nisnInput;
        if (! $sementara && ! preg_match('/^\d{5,20}$/', $nisn)) {
            $galat[] = 'NISN harus angka (5–20 digit).';
        } elseif (Siswa::where('nisn', $nisn)->exists()) {
            $galat[] = "NISN {$nisn} sudah dipakai siswa lain.";
        }
        if ($sementara) {
            $peringatan[] = "NISN belum ada, dipakai nomor pendaftaran {$nisn} sebagai pengganti sementara. Ganti di Data Siswa setelah NISN resmi keluar.";
        }

        $rombelId = ! empty($input['rombel_id']) ? (int) $input['rombel_id'] : null;
        if ($rombelId && ! Kelas::tahunAktif()->whereKey($rombelId)->exists()) {
            $galat[] = 'Rombel tujuan tidak ditemukan pada tahun ajaran aktif.';
            $rombelId = null;
        }
        if (! $rombelId) {
            $peringatan[] = 'Belum ditempatkan di rombel. Bisa diatur nanti di menu Kelas & Jadwal.';
        }

        $username = User::usernameUnik($nisn);

        return [
            'galat' => $galat, 'peringatan' => $peringatan, 'agama' => $agama, 'nisn' => $nisn, 'nisn_sementara' => $sementara,
            'rombel_id' => $rombelId, 'username' => $username, 'ortu' => self::rencanaOrtu($p, $input['buat_ortu'] ?? true),
        ];
    }

    /** Akun orang tua: dibuat baru, dihubungkan ke orang tua yang sudah ada (nama dan nomor HP sama), atau dilewati. */
    private static function rencanaOrtu(PpdbPendaftar $p, bool $buat): array
    {
        $dasar = ['mode' => 'lewati', 'nama' => $p->nama_ortu, 'no_hp' => $p->telepon, 'ortu' => null, 'alasan' => null];

        if (! $buat) {
            return ['alasan' => 'Tidak dibuatkan akun orang tua (dinonaktifkan).'] + $dasar;
        }
        if (! Impor::tersedia('orang_tua')) {
            return ['alasan' => 'Paket sekolah belum mencakup portal orang tua/keuangan.'] + $dasar;
        }
        if (trim($p->nama_ortu) === '') {
            return ['alasan' => 'Nama orang tua kosong.'] + $dasar;
        }

        $hp = NomorWa::normalkan($p->telepon);
        $ada = OrangTua::whereRaw('lower(nama) = ?', [mb_strtolower(trim($p->nama_ortu))])->get()
            ->first(fn (OrangTua $o) => $hp !== null && NomorWa::normalkan($o->no_hp) === $hp);

        return $ada
            ? ['mode' => 'hubungkan', 'ortu' => $ada, 'alasan' => 'Orang tua dengan nama dan nomor HP yang sama sudah ada, anak ditambahkan ke akun itu.'] + $dasar
            : ['mode' => 'baru'] + $dasar;
    }

    /**
     * Jadikan pendaftar sebagai siswa.
     *
     * @param array{agama?: ?string, nisn?: ?string, rombel_id?: ?int, buat_ortu?: bool} $input
     * @throws ValidationException bila ada galat pada rencana
     */
    public static function konversi(PpdbPendaftar $pendaftar, array $input = []): Siswa
    {
        return DB::transaction(function () use ($pendaftar, $input) {
            $p = PpdbPendaftar::whereKey($pendaftar->id)->lockForUpdate()->firstOrFail();   // cegah dua konversi bersamaan
            $r = self::rencana($p, $input);
            if ($r['galat']) {
                throw ValidationException::withMessages(['konversi' => $r['galat']]);
            }

            $user = User::create([
                'role' => 'siswa', 'name' => $p->nama, 'username' => $r['username'], 'email' => $r['username'].'@ppdb.invalid',
                'password' => SiswaController::DEFAULT_PASSWORD, 'remember_token' => Str::random(60),
            ]);
            $siswa = Siswa::create([
                'user_id' => $user->id, 'nisn' => $r['nisn'], 'nama_siswa' => $p->nama, 'agama' => $r['agama'], 'jenis_kelamin' => $p->jenis_kelamin,
                'tanggal_lahir' => $p->tanggal_lahir->toDateString(), 'kelas_id' => $r['rombel_id'], 'status' => 'aktif',
            ]);

            $ortuInfo = 'tanpa akun orang tua';
            $o = $r['ortu'];
            if ($o['mode'] === 'baru') {
                $uOrtu = User::usernameUnik('ortu'.$r['nisn']);
                $ortu = OrangTua::create([
                    'user_id' => User::create([
                        'role' => 'orang_tua', 'name' => $p->nama_ortu, 'username' => $uOrtu, 'email' => $uOrtu.'@ortu.invalid',
                        'password' => OrangTuaController::DEFAULT_PASSWORD, 'remember_token' => Str::random(60),
                    ])->id,
                    'nama' => $p->nama_ortu, 'no_hp' => $p->telepon, 'alamat' => $p->alamat,
                ]);
                $ortu->hubungkan($siswa, 'wali');
                $ortuInfo = "akun orang tua {$uOrtu} dibuat";
            } elseif ($o['mode'] === 'hubungkan') {
                $o['ortu']->hubungkan($siswa, 'wali');
                $ortuInfo = "dihubungkan ke orang tua {$o['ortu']->nama}";
            }

            $p->forceFill(['agama' => $r['agama'], 'siswa_id' => $siswa->id, 'dijadikan_siswa_pada' => now()])->save();
            LogAktivitas::catat('ppdb.jadi_siswa', "Pendaftar {$p->no_pendaftaran} ({$p->nama}) dijadikan siswa NISN {$siswa->nisn}; {$ortuInfo}.", null, ['ppdb_id' => $p->id, 'siswa_id' => $siswa->id]);

            return $siswa;
        });
    }

    /**
     * Jadikan siswa semua pendaftar Diterima yang belum dikonversi. Yang bermasalah (mis. agama belum diisi) dilewati dan dilaporkan.
     *
     * @return array{dibuat: int, dilewati: array<int, array{nama: string, alasan: string}>}
     */
    public static function massal(?int $rombelId): array
    {
        $dibuat = 0;
        $dilewati = [];

        foreach (PpdbPendaftar::where('status', 'diterima')->whereNull('siswa_id')->orderBy('id')->limit(1000)->get() as $p) {
            try {
                self::konversi($p, ['rombel_id' => $rombelId]);
                $dibuat++;
            } catch (ValidationException $e) {
                $dilewati[] = ['nama' => $p->nama.' ('.$p->no_pendaftaran.')', 'alasan' => implode(' ', $e->errors()['konversi'] ?? $e->validator->errors()->all())];
            }
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati];
    }

    /** Jumlah pendaftar Diterima yang belum dijadikan siswa. */
    public static function jumlahSiap(): int
    {
        return PpdbPendaftar::where('status', 'diterima')->whereNull('siswa_id')->count();
    }
}
