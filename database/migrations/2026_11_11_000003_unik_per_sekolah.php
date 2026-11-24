<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 SaaS: keunikan menjadi per sekolah.
 *  - users: email dan username unik per (sekolah_id, kolom)
 *  - site_settings: key unik per sekolah
 *  - chat_settings: kunci utama "key" diganti id + unik (sekolah_id, key)
 *  - pengaturan: halaman profil dikenali lewat "slot" 1-8 (bukan id), unik per sekolah
 * NISN, NIP, nama kelas/mapel/kategori dijaga lewat validasi per sekolah (SekolahAktif::unik); slug berita unik per sekolah
 * karena Sluggable memeriksa lewat model yang sudah difilter sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->gantiUnik('users', ['email'], ['sekolah_id', 'email']);
        $this->gantiUnik('users', ['username'], ['sekolah_id', 'username']);
        $this->gantiUnik('site_settings', ['key'], ['sekolah_id', 'key']);
        $this->bangunUlangChatSettings();
        $this->pengaturanSlot();
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: mengembalikan unik global bisa gagal bila sudah ada dua sekolah dengan nilai yang sama.
    }

    /** @param array<int,string> $lama @param array<int,string> $baru */
    private function gantiUnik(string $tabel, array $lama, array $baru): void
    {
        if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'sekolah_id')) {
            return;
        }

        $indeks = collect(Schema::getIndexes($tabel));
        $sudahBaru = $indeks->contains(fn ($i) => $i['unique'] && $i['columns'] === $baru);

        Schema::table($tabel, function (Blueprint $t) use ($indeks, $lama, $baru, $sudahBaru) {
            foreach ($indeks as $i) {
                if ($i['unique'] && ! $i['primary'] && $i['columns'] === $lama) {
                    $t->dropUnique($i['name']);
                }
            }
            if (! $sudahBaru) {
                $t->unique($baru);
            }
        });
    }

    private function bangunUlangChatSettings(): void
    {
        if (! Schema::hasTable('chat_settings') || Schema::hasColumn('chat_settings', 'id')) {
            return;
        }

        $baris = DB::table('chat_settings')->get();
        Schema::drop('chat_settings');

        Schema::create('chat_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sekolah_id')->nullable()->index();
            $t->string('key', 60);
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['sekolah_id', 'key']);
        });

        foreach ($baris as $b) {
            DB::table('chat_settings')->insert([
                'sekolah_id' => $b->sekolah_id ?? null, 'key' => $b->key, 'value' => $b->value,
                'created_at' => $b->created_at ?? now(), 'updated_at' => $b->updated_at ?? now(),
            ]);
        }
    }

    private function pengaturanSlot(): void
    {
        if (! Schema::hasTable('pengaturan')) {
            return;
        }

        if (! Schema::hasColumn('pengaturan', 'slot')) {
            Schema::table('pengaturan', function (Blueprint $t) {
                $t->unsignedTinyInteger('slot')->nullable();
            });
            DB::table('pengaturan')->whereNull('slot')->update(['slot' => DB::raw('id')]);
        }

        $sudah = collect(Schema::getIndexes('pengaturan'))->contains(fn ($i) => $i['unique'] && $i['columns'] === ['sekolah_id', 'slot']);
        if (! $sudah && Schema::hasColumn('pengaturan', 'sekolah_id')) {
            Schema::table('pengaturan', fn (Blueprint $t) => $t->unique(['sekolah_id', 'slot']));
        }
    }
};
