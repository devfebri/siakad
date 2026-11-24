<?php

namespace App\Backup;

use App\BackupSekolah;
use App\Jobs\BuatBackupSekolah;
use App\LogAktivitas;
use App\Sekolah;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Backup satu sekolah ke berkas zip: seluruh data tabel milik sekolah (JSON, satu berkas per tabel), berkas unggahan, dan manifest.
 * Data sensitif (password, kunci API, token) TIDAK ikut diekspor. Zip disimpan di disk privat (storage/app/private/backup-sekolah)
 * dan hanya bisa diunduh lewat rute berotorisasi.
 */
class Pencadangan
{
    public const FORMAT = 1;

    /** Tabel milik sekolah yang tidak ikut (operasional platform, bukan data sekolah). */
    public const KECUALI_TABEL = ['pendaftaran_sekolah', 'callback_gateway', 'api_request_log', 'webhook_log', 'backup_sekolah'];

    /** Kolom rahasia per tabel yang tidak boleh keluar dari server. */
    public const KECUALI_KOLOM = [
        'users' => ['password', 'cek_password', 'remember_token'],
        'sekolah' => ['domain_token'],
        'sso_klien' => ['webhook_secret'],
        'pengaturan_pembayaran_sekolah' => ['midtrans_server_key', 'xendit_secret_key', 'xendit_callback_token', 'webhook_token'],
        'pengaturan_wa_sekolah' => ['token', 'kunci2'],
    ];

    /** Tabel tanpa sekolah_id yang dimiliki lewat tabel induk: [induk, kolom penghubung]. */
    public const ANAK = ['transaksi_spp_item' => ['transaksi_spp', 'transaksi_spp_id'], 'peran_user' => ['users', 'user_id']];

    /** Tabel yang ikut diekspor untuk sekolah (ditemukan dari skema, jadi tabel baru otomatis ikut). */
    public static function daftarTabel(): array
    {
        $tabel = [];
        foreach (Schema::getTableListing(null, false) as $nama) {
            if (in_array($nama, self::KECUALI_TABEL, true)) {
                continue;
            }
            if ($nama === 'sekolah' || isset(self::ANAK[$nama]) || Schema::hasColumn($nama, 'sekolah_id')) {
                $tabel[] = $nama;
            }
        }
        sort($tabel);

        return $tabel;
    }

    /** Buat catatan backup (berjalan) lalu antrikan pengerjaannya. */
    public static function mulai(Sekolah $sekolah, string $jenis = 'manual', ?int $oleh = null): BackupSekolah
    {
        $backup = BackupSekolah::create(['sekolah_id' => $sekolah->id, 'jenis' => $jenis, 'status' => 'berjalan', 'dibuat_oleh' => $oleh]);
        BuatBackupSekolah::dispatch($backup->id);

        return $backup;
    }

    /** Kerjakan backup. Mengembalikan catatan yang sudah diperbarui (selesai atau gagal; tidak melempar exception). */
    public static function kerjakan(BackupSekolah $backup): BackupSekolah
    {
        $sekolah = Sekolah::find($backup->sekolah_id);
        $kerja = storage_path('app/tmp/backup-'.Str::random(12));

        try {
            if (! $sekolah) {
                throw new \RuntimeException('Sekolah tidak ditemukan.');
            }
            File::ensureDirectoryExists($kerja.'/data');

            $jumlah = [];
            foreach (self::daftarTabel() as $tabel) {
                $jumlah[$tabel] = self::tulisTabel($sekolah, $tabel, $kerja.'/data/'.$tabel.'.json');
            }

            $berkas = BerkasSekolah::daftar($sekolah);
            $nama = Str::slug($sekolah->subdomain).'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
            $zipTmp = $kerja.'/'.$nama;

            $zip = new ZipArchive();
            if ($zip->open($zipTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Tidak bisa membuat berkas zip.');
            }
            foreach (array_keys($jumlah) as $tabel) {
                $zip->addFile($kerja.'/data/'.$tabel.'.json', 'data/'.$tabel.'.json');
            }
            foreach ($berkas as $mutlak => $dalamZip) {
                $zip->addFile($mutlak, $dalamZip);
            }
            $zip->addFromString('manifest.json', json_encode([
                'format' => self::FORMAT,
                'sekolah' => ['id' => $sekolah->id, 'nama' => $sekolah->nama, 'subdomain' => $sekolah->subdomain, 'npsn' => $sekolah->npsn],
                'jenis' => $backup->jenis,
                'dibuat_pada' => now()->toIso8601String(),
                'migrasi_terakhir' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                'tabel' => $jumlah,
                'jumlah_berkas' => count($berkas),
                'kolom_dikecualikan' => self::KECUALI_KOLOM,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('BACA-SAYA.txt', self::bacaSaya($sekolah));
            $zip->close();

            $rel = 'backup-sekolah/'.$sekolah->id.'/'.$nama;
            File::ensureDirectoryExists(dirname(Storage::disk('local')->path($rel)));
            File::move($zipTmp, Storage::disk('local')->path($rel));

            $backup->update([
                'status' => 'selesai', 'berkas' => $rel, 'ukuran' => filesize(Storage::disk('local')->path($rel)), 'checksum' => hash_file('sha256', Storage::disk('local')->path($rel)),
                'jumlah_tabel' => count($jumlah), 'jumlah_baris' => array_sum($jumlah), 'jumlah_berkas' => count($berkas), 'galat' => null,
            ]);
            if ($backup->jenis === 'arsip') {
                $sekolah->forceFill(['diarsipkan_pada' => now()])->saveQuietly();
            }
            LogAktivitas::catat('backup.selesai', "Backup {$backup->jenis} {$sekolah->nama} selesai (".count($jumlah).' tabel, '.array_sum($jumlah).' baris, '.count($berkas).' berkas).', $sekolah->id, ['backup_id' => $backup->id]);
        } catch (\Throwable $e) {
            $backup->update(['status' => 'gagal', 'galat' => mb_substr($e->getMessage(), 0, 280)]);
            LogAktivitas::catat('backup.gagal', 'Backup gagal: '.mb_substr($e->getMessage(), 0, 200), $backup->sekolah_id, ['backup_id' => $backup->id]);
        } finally {
            File::deleteDirectory($kerja);
        }

        return $backup->fresh();
    }

    /** Tulis seluruh baris satu tabel milik sekolah ke berkas JSON (array), tanpa memuat semuanya ke memori. Mengembalikan jumlah baris. */
    private static function tulisTabel(Sekolah $sekolah, string $tabel, string $tujuan): int
    {
        $kolomBuang = array_flip(self::KECUALI_KOLOM[$tabel] ?? []);
        $query = DB::table($tabel);

        if ($tabel === 'sekolah') {
            $query->where('id', $sekolah->id);
        } elseif (isset(self::ANAK[$tabel])) {
            [$induk, $kolom] = self::ANAK[$tabel];
            $query->whereIn($kolom, DB::table($induk)->where('sekolah_id', $sekolah->id)->select('id'));
        } else {
            $query->where('sekolah_id', $sekolah->id);
        }

        $h = fopen($tujuan, 'wb');
        fwrite($h, '[');
        $n = 0;
        foreach ($query->cursor() as $baris) {
            fwrite($h, ($n ? ",\n" : "\n").json_encode(array_diff_key((array) $baris, $kolomBuang), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
            $n++;
        }
        fwrite($h, $n ? "\n]" : ']');
        fclose($h);

        return $n;
    }

    private static function bacaSaya(Sekolah $s): string
    {
        return "BACKUP DATA {$s->nama}\n"
            ."Dibuat: ".now()->translatedFormat('d F Y H:i')."\n\n"
            ."Isi berkas ini:\n"
            ."- manifest.json : ringkasan isi backup (jumlah baris per tabel, versi format)\n"
            ."- data/*.json   : seluruh data sekolah, satu berkas per tabel (dapat dibuka di editor teks atau Excel/Google Sheets via impor JSON)\n"
            ."- berkas/        : foto, dokumen, dan lampiran yang diunggah sekolah\n\n"
            ."Yang TIDAK disertakan demi keamanan: kata sandi akun, kunci API (pembayaran, WhatsApp, E-Learning), dan token.\n"
            ."Berkas ini berisi data pribadi siswa dan guru. Simpan di tempat aman dan jangan dibagikan.\n";
    }

    /** Hapus backup beserta berkasnya. */
    public static function hapus(BackupSekolah $backup): void
    {
        if ($backup->berkas) {
            Storage::disk('local')->delete($backup->berkas);
        }
        $backup->delete();
    }

    /** Simpan hanya $simpan backup terjadwal terbaru per sekolah; manual dan arsip tidak pernah dihapus otomatis. Mengembalikan jumlah yang dihapus. */
    public static function bersihkanTerjadwal(int $simpan): int
    {
        $dihapus = 0;
        $perSekolah = BackupSekolah::where('jenis', 'terjadwal')->orderByDesc('id')->get()->groupBy('sekolah_id');
        foreach ($perSekolah as $daftar) {
            foreach ($daftar->slice(max(1, $simpan)) as $lama) {
                self::hapus($lama);
                $dihapus++;
            }
        }

        return $dihapus;
    }
}
