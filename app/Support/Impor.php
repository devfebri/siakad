<?php

namespace App\Support;

use App\Guru;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\OrangTuaController;
use App\Kelas;
use App\Mapel;
use App\OrangTua;
use App\Siswa;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Impor data guru, siswa, dan orang tua dari Excel/CSV: template, pembacaan, pemeriksaan per baris, dan penyimpanan.
 * Baris yang gagal diperiksa dilewati dan dilaporkan; yang valid disimpan dalam satu transaksi.
 */
class Impor
{
    public const MAKS_BARIS = 1000;

    public const JENIS = [
        'guru' => [
            'label' => 'Guru',
            'kolom' => ['nip', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'mata_pelajaran', 'no_hp', 'username', 'email'],
            'wajib' => ['nip', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'mata_pelajaran'],
            'contoh' => ['198001012005011001', 'Budi Santoso, S.Pd', 'Laki-Laki', '1980-01-01', 'Matematika', '081234567890', '', ''],
        ],
        'siswa' => [
            'label' => 'Siswa',
            'kolom' => ['nisn', 'nama', 'jenis_kelamin', 'agama', 'tanggal_lahir', 'rombel', 'username', 'email'],
            'wajib' => ['nisn', 'nama', 'jenis_kelamin', 'agama', 'tanggal_lahir'],
            'contoh' => ['0012345678', 'Siti Aminah', 'Perempuan', 'Islam', '2010-05-17', 'VII A', '', ''],
        ],
        'orang_tua' => [
            'label' => 'Orang Tua',
            'kolom' => ['nama', 'no_hp', 'nisn_anak', 'hubungan', 'pekerjaan', 'alamat', 'username', 'email'],
            'wajib' => ['nama', 'nisn_anak'],
            'contoh' => ['Bambang Pratama', '081234567890', '0012345678; 0012345679', 'Ayah', 'Wiraswasta', 'Jl. Merdeka No. 1', '', ''],
        ],
    ];

    /** Sandi awal akun hasil impor menurut jenis. */
    public static function sandiAwal(string $jenis): string
    {
        return match ($jenis) {
            'guru' => GuruController::DEFAULT_PASSWORD,
            'orang_tua' => OrangTuaController::DEFAULT_PASSWORD,
            default => SiswaController::DEFAULT_PASSWORD,
        };
    }

    /** Apakah jenis impor ini boleh dipakai sekolah aktif (impor orang tua butuh Portal Orang Tua atau Keuangan). */
    public static function tersedia(string $jenis): bool
    {
        return isset(self::JENIS[$jenis]) && ($jenis !== 'orang_tua' || AksesSekolah::modul('portal_orang_tua') || AksesSekolah::modul('keuangan'));
    }

    private const PETUNJUK = [
        'guru' => [
            'nip' => 'Wajib, unik. Dipakai sebagai username bila kolom username kosong.',
            'nama' => 'Wajib.',
            'jenis_kelamin' => 'Wajib: Laki-Laki / Perempuan (boleh L / P).',
            'tanggal_lahir' => 'Wajib: format 2026-01-31 atau 31/01/2026.',
            'mata_pelajaran' => 'Wajib. Mata pelajaran yang belum ada dibuat otomatis.',
            'no_hp' => 'Opsional.',
            'username' => 'Opsional: huruf kecil, angka, titik, garis bawah, strip. Kosong = NIP.',
            'email' => 'Opsional (login memakai username).',
        ],
        'siswa' => [
            'nisn' => 'Wajib, unik. Dipakai sebagai username bila kolom username kosong.',
            'nama' => 'Wajib.',
            'jenis_kelamin' => 'Wajib: Laki-Laki / Perempuan (boleh L / P).',
            'agama' => 'Wajib: Islam, Kristen, Katolik, Hindu, Budha, atau Konghucu.',
            'tanggal_lahir' => 'Wajib: format 2026-01-31 atau 31/01/2026.',
            'rombel' => 'Opsional: nama rombel pada tahun ajaran aktif (harus sudah ada). Kosong = belum ditempatkan.',
            'username' => 'Opsional. Kosong = NISN.',
            'email' => 'Opsional (login memakai username).',
        ],
        'orang_tua' => [
            'nama' => 'Wajib: nama orang tua/wali.',
            'no_hp' => 'Opsional, tetapi diperlukan agar orang tua menerima pesan WhatsApp. Contoh: 0812 3456 7890.',
            'nisn_anak' => 'Wajib: NISN anak yang SUDAH terdaftar. Beberapa anak dipisah titik koma, misalnya 0012345678; 0012345679. Daftar NISN ada di lembar Daftar Siswa.',
            'hubungan' => 'Opsional: Ayah, Ibu, atau Wali. Kosong = Wali. Berlaku untuk semua anak di baris itu.',
            'pekerjaan' => 'Opsional.',
            'alamat' => 'Opsional.',
            'username' => 'Opsional: huruf kecil, angka, titik, garis bawah, strip. Kosong = dibuat otomatis (ortu + NISN anak pertama).',
            'email' => 'Opsional (login memakai username).',
        ],
    ];

    private const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'];

    /** Jumlah baris kosong berformat teks yang disiapkan di lembar Data (supaya NISN/NIP berawalan 0 dan tanggal tidak diubah Excel). */
    public const BARIS_SIAP = 500;

    /** Baris contoh di lembar Data diawali kata ini; baris itu otomatis dilewati saat impor. */
    public const PENANDA_CONTOH = 'CONTOH';

    /**
     * Tulis template Excel ke $path.
     *  - Lembar "Data": judul kolom berwarna, satu baris CONTOH (abu-abu, otomatis dilewati saat impor), lalu baris kosong berformat teks untuk diisi.
     *  - Lembar "Petunjuk": aturan tiap kolom (wajib atau tidak, contoh).
     *  - Orang tua: lembar "Daftar Siswa" berisi NISN siswa aktif sebagai contekan (dimatikan lewat $denganDaftarSiswa untuk berkas umum).
     */
    public static function template(string $jenis, string $path, bool $denganDaftarSiswa = true): void
    {
        $def = self::JENIS[$jenis];
        $n = count($def['kolom']);

        $opsi = new Options();
        $opsi->setColumnWidth(24, ...range(1, $n));
        foreach ($def['kolom'] as $i => $k) {
            if (in_array($k, ['nama', 'alamat', 'mata_pelajaran', 'email', 'nisn_anak'], true)) {
                $opsi->setColumnWidthForRange(34, $i + 1, $i + 1);
            }
        }

        $judul = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('1E3A8A')->withShouldWrapText(false);
        $wajib = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('B45309');      // kolom wajib: oranye tua
        $contoh = (new Style())->withFontItalic(true)->withFontColor('64748B')->withBackgroundColor('F1F5F9')->withFormat('@');
        $teks = (new Style())->withFormat('@');                                                                         // format teks
        $tebal = (new Style())->withFontBold(true);

        $w = new Writer($opsi);
        $w->openToFile($path);

        // ---- lembar Data
        $w->getCurrentSheet()->setName('Data');
        $w->addRow(new Row(array_map(fn ($k) => \OpenSpout\Common\Entity\Cell::fromValue($k, in_array($k, $def['wajib'], true) ? $wajib : $judul), $def['kolom'])));
        $contohBaris = $def['contoh'];
        $contohBaris[0] = self::PENANDA_CONTOH.' - '.$contohBaris[0];
        $w->addRow(Row::fromValuesWithStyle(array_map('strval', $contohBaris), $contoh));
        for ($i = 0; $i < self::BARIS_SIAP; $i++) {
            $w->addRow(Row::fromValuesWithStyle(array_fill(0, $n, ''), $teks));
        }

        // ---- lembar Petunjuk
        $w->addNewSheetAndMakeItCurrent()->setName('Petunjuk');
        $w->addRow(Row::fromValuesWithStyle(['CARA MENGISI TEMPLATE '.mb_strtoupper($def['label'])], $tebal));
        foreach ([
            '1. Isi data pada lembar "Data" mulai baris ke-3. Baris ke-2 (abu-abu, berawalan CONTOH) hanya contoh dan otomatis dilewati.',
            '2. Judul kolom ORANYE wajib diisi; judul BIRU boleh dikosongkan.',
            '3. Jangan mengubah judul kolom dan jangan menambah atau menukar urutan kolom.',
            '4. Simpan sebagai .xlsx (atau .csv), lalu unggah di menu Impor Data. Maksimal '.self::MAKS_BARIS.' baris per berkas.',
        ] as $baris) {
            $w->addRow(Row::fromValues([$baris]));
        }
        $w->addRow(Row::fromValues(['']));
        $w->addRow(Row::fromValuesWithStyle(['Kolom', 'Wajib?', 'Aturan', 'Contoh'], $judul));
        foreach ($def['kolom'] as $i => $k) {
            $w->addRow(Row::fromValues([$k, in_array($k, $def['wajib'], true) ? 'YA' : 'tidak', self::PETUNJUK[$jenis][$k], (string) $def['contoh'][$i]]));
        }

        // ---- contekan NISN untuk orang tua
        if ($jenis === 'orang_tua' && $denganDaftarSiswa) {
            $w->addNewSheetAndMakeItCurrent()->setName('Daftar Siswa');
            $w->addRow(Row::fromValuesWithStyle(['nisn', 'nama', 'rombel'], $judul));
            foreach (Siswa::query()->with('kelas:id,nama_kelas')->where('status', 'aktif')->orderBy('nama_siswa')->limit(5000)->get(['id', 'nisn', 'nama_siswa', 'kelas_id']) as $s) {
                $w->addRow(Row::fromValues([(string) $s->nisn, $s->nama_siswa, $s->kelas?->nama_kelas ?? '']));
            }
        }

        $w->close();
    }

    /**
     * Baca lembar pertama berkas .xlsx/.csv menjadi daftar baris berkunci nama kolom (huruf kecil, spasi menjadi garis bawah).
     *
     * @return array<int, array{baris: int, data: array<string, string>}>
     */
    public static function baca(string $path, string $ekstensi): array
    {
        $reader = strtolower($ekstensi) === 'csv' ? new CsvReader() : new XlsxReader();
        $reader->open($path);

        $hasil = [];
        $kepala = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $no => $row) {
                $nilai = array_map(fn ($v) => self::teks($v), $row->toArray());
                if (! array_filter($nilai, fn ($v) => $v !== '')) {
                    continue;
                }
                if ($kepala !== null && preg_match('/^\s*'.self::PENANDA_CONTOH.'\b/i', $nilai[0] ?? '')) {
                    continue; // baris contoh dari template
                }
                if ($kepala === null) {
                    $kepala = array_map(fn ($v) => Str::snake(str_replace([' ', '-', '.'], '_', mb_strtolower($v))), $nilai);

                    continue;
                }
                $hasil[] = ['baris' => $no, 'data' => array_combine($kepala, array_pad(array_slice($nilai, 0, count($kepala)), count($kepala), ''))];
                if (count($hasil) > self::MAKS_BARIS) {
                    $reader->close();
                    throw new \RuntimeException('Berkas melebihi '.self::MAKS_BARIS.' baris. Pecah menjadi beberapa berkas.');
                }
            }
            break; // hanya lembar pertama
        }
        $reader->close();

        if ($kepala === null) {
            throw new \RuntimeException('Berkas kosong.');
        }

        return $hasil;
    }

    /** Nilai sel menjadi teks (tanggal ke Y-m-d, angka bulat tanpa desimal). */
    private static function teks(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
            is_float($v) && floor($v) === $v => (string) (int) $v,
            default => trim((string) $v),
        };
    }

    /**
     * Periksa semua baris. Mengembalikan baris valid (sudah dinormalisasi) dan daftar galat per baris.
     *
     * @param  array<int, array{baris: int, data: array<string, string>}>  $baris
     * @return array{valid: array<int, array<string, mixed>>, galat: array<int, array{baris: int, pesan: string}>}
     */
    public static function periksa(string $jenis, array $baris): array
    {
        if ($jenis === 'orang_tua') {
            return self::periksaOrangTua($baris);
        }

        $def = self::JENIS[$jenis];
        $kolomHilang = array_diff($def['wajib'], array_keys($baris[0]['data'] ?? array_flip($def['kolom'])));
        if ($kolomHilang) {
            throw new \RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $kolomHilang).'. Gunakan template dari halaman ini.');
        }

        $kunci = $jenis === 'guru' ? 'nip' : 'nisn';
        $ada = ($jenis === 'guru' ? Guru::query()->pluck('nip_guru') : Siswa::query()->pluck('nisn'))->map(fn ($v) => (string) $v)->flip();
        $username = User::query()->pluck('username')->map(fn ($v) => mb_strtolower((string) $v))->flip();
        $email = User::query()->pluck('email')->map(fn ($v) => mb_strtolower((string) $v))->flip();
        $rombel = Kelas::tahunAktif()->pluck('id', 'nama_kelas')->mapWithKeys(fn ($id, $nama) => [mb_strtolower($nama) => $id]);

        $valid = [];
        $galat = [];
        $kembar = [];

        foreach ($baris as ['baris' => $no, 'data' => $d]) {
            $e = [];
            foreach ($def['wajib'] as $k) {
                if (($d[$k] ?? '') === '') {
                    $e[] = "kolom {$k} kosong";
                }
            }

            $id = $d[$kunci] ?? '';
            if ($id !== '') {
                if ($ada->has($id)) {
                    $e[] = strtoupper($kunci)." {$id} sudah terdaftar";
                } elseif (isset($kembar[$id])) {
                    $e[] = strtoupper($kunci)." {$id} kembar dengan baris {$kembar[$id]}";
                }
            }

            $jk = match (strtoupper($d['jenis_kelamin'] ?? '')) {
                'L', 'LAKI-LAKI', 'LAKI LAKI' => 'Laki-Laki',
                'P', 'PEREMPUAN' => 'Perempuan',
                default => null,
            };
            if (($d['jenis_kelamin'] ?? '') !== '' && ! $jk) {
                $e[] = 'jenis kelamin harus Laki-Laki atau Perempuan';
            }

            $tgl = self::tanggal($d['tanggal_lahir'] ?? '');
            if (($d['tanggal_lahir'] ?? '') !== '' && (! $tgl || $tgl->isFuture())) {
                $e[] = 'tanggal lahir tidak valid (pakai 2026-01-31 atau 31/01/2026)';
            }

            $u = mb_strtolower($d['username'] ?? '');
            if ($u !== '') {
                if (! preg_match('/^[a-z0-9._-]{3,50}$/', $u)) {
                    $e[] = 'username hanya huruf kecil, angka, titik, garis bawah, strip (3-50 karakter)';
                } elseif ($username->has($u) || isset($kembar['u:'.$u])) {
                    $e[] = "username {$u} sudah dipakai";
                }
            }
            $mail = mb_strtolower($d['email'] ?? '');
            if ($mail !== '') {
                if (! filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $e[] = 'email tidak valid';
                } elseif ($email->has($mail) || isset($kembar['e:'.$mail])) {
                    $e[] = "email {$mail} sudah dipakai";
                }
            }

            $agama = null;
            $rombelId = null;
            if ($jenis === 'siswa') {
                $agama = collect(self::AGAMA)->first(fn ($a) => strcasecmp($a, $d['agama'] ?? '') === 0) ?? (strcasecmp($d['agama'] ?? '', 'Buddha') === 0 ? 'Budha' : null);
                if (($d['agama'] ?? '') !== '' && ! $agama) {
                    $e[] = 'agama harus salah satu dari: '.implode(', ', self::AGAMA);
                }
                if (($d['rombel'] ?? '') !== '') {
                    $rombelId = $rombel->get(mb_strtolower($d['rombel']));
                    if (! $rombelId) {
                        $e[] = "rombel {$d['rombel']} tidak ada pada tahun ajaran aktif";
                    }
                }
            }

            if ($e) {
                $galat[] = ['baris' => $no, 'pesan' => implode('; ', $e)];

                continue;
            }

            $kembar[$id] = $no;
            $u !== '' && $kembar['u:'.$u] = $no;
            $mail !== '' && $kembar['e:'.$mail] = $no;

            $valid[] = [
                'baris' => $no, 'id' => $id, 'nama' => $d['nama'], 'jenis_kelamin' => $jk, 'tanggal_lahir' => $tgl->toDateString(),
                'username' => $u ?: null, 'email' => $mail ?: null,
            ] + ($jenis === 'guru'
                ? ['mata_pelajaran' => $d['mata_pelajaran'], 'no_hp' => $d['no_hp'] ?? '']
                : ['agama' => $agama, 'rombel_id' => $rombelId, 'rombel' => $d['rombel'] ?? '']);
        }

        return ['valid' => $valid, 'galat' => $galat];
    }

    /**
     * Pemeriksaan impor orang tua: nama dan NISN anak wajib, anak harus sudah terdaftar, dan orang tua yang sama
     * (nama sama, anak sama) tidak dibuat dua kali, baik terhadap data yang ada maupun di dalam berkas.
     *
     * @param  array<int, array{baris: int, data: array<string, string>}>  $baris
     */
    private static function periksaOrangTua(array $baris): array
    {
        $def = self::JENIS['orang_tua'];
        $kolomHilang = array_diff($def['wajib'], array_keys($baris[0]['data'] ?? array_flip($def['kolom'])));
        if ($kolomHilang) {
            throw new \RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $kolomHilang).'. Gunakan template dari halaman ini.');
        }

        $siswa = Siswa::query()->get(['id', 'nisn', 'nama_siswa'])->keyBy(fn ($s) => (string) $s->nisn);
        $username = User::query()->pluck('username')->map(fn ($v) => mb_strtolower((string) $v))->flip();
        $email = User::query()->pluck('email')->map(fn ($v) => mb_strtolower((string) $v))->flip();
        $hubungan = ['ayah' => 'ayah', 'ibu' => 'ibu', 'wali' => 'wali', 'bapak' => 'ayah', 'mama' => 'ibu'];

        // pasangan "nama|id siswa" yang sudah terhubung
        $terhubung = [];
        foreach (OrangTua::query()->with('anak:id')->get(['id', 'nama']) as $o) {
            foreach ($o->anak as $a) {
                $terhubung[mb_strtolower($o->nama).'|'.$a->id] = true;
            }
        }

        $valid = [];
        $galat = [];
        $kembar = [];

        foreach ($baris as ['baris' => $no, 'data' => $d]) {
            $e = [];
            if (($d['nama'] ?? '') === '') {
                $e[] = 'kolom nama kosong';
            }

            // NISN anak: boleh beberapa, dipisah ; , / atau garis miring
            $nisnList = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\/|]+/', (string) ($d['nisn_anak'] ?? ''))), fn ($v) => $v !== '')));
            $anak = [];
            if (! $nisnList) {
                $e[] = 'kolom nisn_anak kosong';
            }
            foreach ($nisnList as $nisn) {
                if (! $siswa->has($nisn)) {
                    $e[] = "NISN anak {$nisn} belum terdaftar sebagai siswa";

                    continue;
                }
                $anak[] = $siswa[$nisn];
                $kunciPasang = mb_strtolower($d['nama'] ?? '').'|'.$siswa[$nisn]->id;
                if (isset($terhubung[$kunciPasang])) {
                    $e[] = "orang tua {$d['nama']} sudah terhubung ke {$siswa[$nisn]->nama_siswa}";
                } elseif (isset($kembar[$kunciPasang])) {
                    $e[] = "orang tua {$d['nama']} untuk {$siswa[$nisn]->nama_siswa} kembar dengan baris {$kembar[$kunciPasang]}";
                }
            }

            $hub = 'wali';
            if (($d['hubungan'] ?? '') !== '') {
                $hub = $hubungan[mb_strtolower($d['hubungan'])] ?? null;
                if (! $hub) {
                    $e[] = 'hubungan harus Ayah, Ibu, atau Wali';
                }
            }

            $u = mb_strtolower($d['username'] ?? '');
            if ($u !== '') {
                if (! preg_match('/^[a-z0-9._-]{3,50}$/', $u)) {
                    $e[] = 'username hanya huruf kecil, angka, titik, garis bawah, strip (3-50 karakter)';
                } elseif ($username->has($u) || isset($kembar['u:'.$u])) {
                    $e[] = "username {$u} sudah dipakai";
                }
            }
            $mail = mb_strtolower($d['email'] ?? '');
            if ($mail !== '') {
                if (! filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $e[] = 'email tidak valid';
                } elseif ($email->has($mail) || isset($kembar['e:'.$mail])) {
                    $e[] = "email {$mail} sudah dipakai";
                }
            }

            if ($e) {
                $galat[] = ['baris' => $no, 'pesan' => implode('; ', $e)];

                continue;
            }

            foreach ($anak as $a) {
                $kembar[mb_strtolower($d['nama']).'|'.$a->id] = $no;
            }
            $u !== '' && $kembar['u:'.$u] = $no;
            $mail !== '' && $kembar['e:'.$mail] = $no;

            $valid[] = [
                'baris' => $no, 'id' => $d['no_hp'] ?? '', 'nama' => $d['nama'], 'username' => $u ?: null, 'email' => $mail ?: null,
                'no_hp' => $d['no_hp'] ?? '', 'pekerjaan' => $d['pekerjaan'] ?? '', 'alamat' => $d['alamat'] ?? '', 'hubungan' => $hub,
                'anak_id' => array_map(fn ($a) => $a->id, $anak), 'anak_nama' => implode(', ', array_map(fn ($a) => $a->nama_siswa, $anak)), 'nisn_pertama' => $anak[0]->nisn,
            ];
        }

        return ['valid' => $valid, 'galat' => $galat];
    }

    private static function tanggal(string $teks): ?Carbon
    {
        if ($teks === '') {
            return null;
        }
        // sel tanggal Excel yang terbaca sebagai nomor seri (hari sejak 1899-12-30)
        if (ctype_digit($teks) && (int) $teks >= 10000 && (int) $teks <= 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $teks);
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j/n/Y', 'j-n-Y'] as $format) {
            try {
                $t = Carbon::createFromFormat('!'.$format, $teks);
                if ($t && $t->format($format) === $teks) {
                    return $t;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** Simpan baris valid (dalam satu transaksi). Mengembalikan jumlah baris tersimpan dan mata pelajaran yang dibuat. */
    public static function simpan(string $jenis, array $valid): array
    {
        $mapelBaru = [];

        DB::transaction(function () use ($jenis, $valid, &$mapelBaru) {
            foreach ($valid as $r) {
                if ($jenis === 'orang_tua') {
                    $username = $r['username'] ?? User::usernameUnik('ortu'.$r['nisn_pertama']);
                    $user = User::create([
                        'role' => 'orang_tua', 'name' => $r['nama'], 'username' => $username, 'email' => $r['email'] ?? $username.'@ortu.invalid',
                        'password' => OrangTuaController::DEFAULT_PASSWORD, 'remember_token' => Str::random(60),
                    ]);
                    $ortu = OrangTua::create([
                        'user_id' => $user->id, 'nama' => $r['nama'], 'no_hp' => $r['no_hp'] !== '' ? $r['no_hp'] : null,
                        'pekerjaan' => $r['pekerjaan'] !== '' ? $r['pekerjaan'] : null, 'alamat' => $r['alamat'] !== '' ? $r['alamat'] : null,
                    ]);
                    foreach ($r['anak_id'] as $anakId) {
                        $ortu->hubungkan(Siswa::findOrFail($anakId), $r['hubungan']);
                    }

                    continue;
                }

                $username = $r['username'] ?? User::usernameUnik($r['id']);
                $user = User::create([
                    'role' => $jenis,
                    'name' => $r['nama'],
                    'username' => $username,
                    'email' => $r['email'] ?? $username.'@impor.invalid', // login memakai username; email boleh kosong
                    'password' => self::sandiAwal($jenis),
                    'remember_token' => Str::random(60),
                ]);

                if ($jenis === 'guru') {
                    $mapel = Mapel::firstOrCreate(['mapel_nama' => $r['mata_pelajaran']]);
                    $mapel->wasRecentlyCreated && $mapelBaru[$mapel->id] = $mapel->mapel_nama;
                    Guru::create([
                        'user_id' => $user->id, 'nip_guru' => $r['id'], 'nama_guru' => $r['nama'], 'mapel_id' => $mapel->id,
                        'jenis_kelamin' => $r['jenis_kelamin'], 'no_hp' => $r['no_hp'] !== '' ? $r['no_hp'] : '-', 'tanggal_lahir' => $r['tanggal_lahir'],
                    ]);
                } else {
                    Siswa::create([
                        'user_id' => $user->id, 'nisn' => $r['id'], 'nama_siswa' => $r['nama'], 'agama' => $r['agama'],
                        'jenis_kelamin' => $r['jenis_kelamin'], 'tanggal_lahir' => $r['tanggal_lahir'], 'kelas_id' => $r['rombel_id'],
                    ]);
                }
            }
        });

        return ['tersimpan' => count($valid), 'mapel_baru' => array_values($mapelBaru)];
    }
}
