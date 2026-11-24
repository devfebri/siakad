<?php

use App\Console\Commands\ElearningEkspor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 SaaS: modul e-learning (materi, tugas, soal, pengerjaan) dihapus dari aplikasi.
 * PENGAMAN: bila tabel masih berisi data, migrasi menolak berjalan sampai `php artisan elearning:ekspor`
 * dijalankan dan jumlah baris di manifest.json cocok. Berkas fisik materi tidak dihapus.
 */
return new class extends Migration
{
    /** Urutan drop: tabel anak lebih dulu. */
    private array $tabel = ['tugas_siswa', 'soal_pilgan', 'soal_essay', 'kelas_tugas', 'tugas', 'materi'];

    public function up(): void
    {
        $this->pastikanSudahDiekspor();

        Schema::disableForeignKeyConstraints();
        foreach ($this->tabel as $nama) {
            Schema::dropIfExists($nama);
        }
        Schema::enableForeignKeyConstraints();
    }

    /** Membuat ulang struktur (kosong) agar migrasi bisa di-rollback; data dipulihkan dari ekspor JSON atau backup. */
    public function down(): void
    {
        if (! Schema::hasTable('materi')) {
            Schema::create('materi', function (Blueprint $table) {
                $table->id();
                $table->string('materi', 100);
                $table->unsignedBigInteger('mapel_id')->index();
                $table->string('deskripsi', 100)->default('-');
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('file_materi', 200)->nullable();
                $table->string('link_materi', 100)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tugas')) {
            Schema::create('tugas', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 100);
                $table->unsignedBigInteger('mapel_id')->index();
                $table->unsignedBigInteger('guru_id')->nullable()->index();
                $table->string('waktu', 100)->nullable();
                $table->string('jenis_tugas', 200);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('kelas_tugas')) {
            Schema::create('kelas_tugas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('tugas_id')->index();
            });
        }
        if (! Schema::hasTable('soal_essay')) {
            Schema::create('soal_essay', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_id')->index();
                $table->text('soal');
                $table->string('gambar')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('soal_pilgan')) {
            Schema::create('soal_pilgan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_id')->index();
                $table->text('soal');
                $table->string('a');
                $table->string('b');
                $table->string('c');
                $table->string('d');
                $table->string('kunci', 10);
                $table->unsignedInteger('score')->default(0);
                $table->string('gambar')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tugas_siswa')) {
            Schema::create('tugas_siswa', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('siswa_id')->index();
                $table->unsignedBigInteger('tugas_id')->index();
                $table->timestamps();
                $table->timestamp('mulai_at')->nullable();
                $table->timestamp('selesai_at')->nullable();
                $table->unsignedInteger('nilai')->nullable();
                $table->unsignedInteger('nilai_maks')->nullable();
                $table->longText('jawaban')->nullable();
            });
        }
    }

    private function pastikanSudahDiekspor(): void
    {
        $manifestPath = ElearningEkspor::folder().'/manifest.json';
        $manifest = File::isFile($manifestPath) ? json_decode(File::get($manifestPath), true) : null;

        foreach ($this->tabel as $nama) {
            if (! Schema::hasTable($nama)) {
                continue;
            }

            $baris = DB::table($nama)->count();
            if ($baris === 0) {
                continue;
            }

            $diekspor = (int) ($manifest['jumlah_baris'][$nama] ?? -1);
            if ($diekspor < $baris || ! File::isFile(ElearningEkspor::folder()."/{$nama}.json")) {
                throw new RuntimeException(
                    "Tabel {$nama} masih berisi {$baris} baris dan belum diekspor. Jalankan dulu: php artisan elearning:ekspor (hasil di storage/app/ekspor-elearning), lalu ulangi php artisan migrate."
                );
            }
        }
    }
};
