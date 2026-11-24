<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Ekspor isi tabel modul e-learning (materi, tugas, soal, pengerjaan) ke JSON sebelum tabelnya di-DROP
 * oleh migrasi 2026_11_10_000001_drop_tabel_elearning. Satu berkas JSON per tabel + manifest.json
 * di storage/app/ekspor-elearning/. Berkas fisik materi (public/storage/materi) tidak dihapus oleh migrasi.
 */
class ElearningEkspor extends Command
{
    public const TABEL = ['materi', 'tugas', 'kelas_tugas', 'tugas_siswa', 'soal_essay', 'soal_pilgan'];

    protected $signature = 'elearning:ekspor';
    protected $description = 'Ekspor isi tabel e-learning (materi, tugas, soal, pengerjaan) ke JSON sebelum modulnya dihapus';

    /** Hanya untuk tes: arahkan ekspor ke folder sementara agar ekspor sungguhan tidak tersentuh. */
    public static ?string $folderUji = null;

    public static function folder(): string
    {
        return self::$folderUji ?? storage_path('app/ekspor-elearning');
    }

    public function handle(): int
    {
        $folder = self::folder();
        File::ensureDirectoryExists($folder);

        $jumlah = [];
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel)) {
                $this->line("Lewati {$tabel} (tabel tidak ada).");

                continue;
            }

            $jumlah[$tabel] = $this->ekspor($tabel, $folder.'/'.$tabel.'.json');
            $this->info(sprintf('%-12s %d baris', $tabel, $jumlah[$tabel]));
        }

        $berkas = Schema::hasTable('materi')
            ? DB::table('materi')->whereNotNull('file_materi')->pluck('file_materi')->all()
            : [];

        File::put($folder.'/manifest.json', json_encode([
            'diekspor_pada' => now()->toDateTimeString(),
            'jumlah_baris' => $jumlah,
            'berkas_materi' => $berkas,
            'catatan' => 'Berkas fisik materi tetap di public/storage/materi dan tidak dihapus oleh migrasi.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Selesai. Ekspor disimpan di {$folder}");

        return self::SUCCESS;
    }

    /** Tulis satu tabel sebagai array JSON secara bertahap (hemat memori). */
    private function ekspor(string $tabel, string $path): int
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "[\n");
        $n = 0;

        foreach (DB::table($tabel)->orderBy(Schema::hasColumn($tabel, 'id') ? 'id' : DB::raw('1'))->cursor() as $baris) {
            fwrite($fh, ($n > 0 ? ",\n" : '').json_encode($baris, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $n++;
        }

        fwrite($fh, "\n]\n");
        fclose($fh);

        return $n;
    }
}
