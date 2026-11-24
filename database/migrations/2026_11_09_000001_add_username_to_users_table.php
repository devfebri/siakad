<?php

use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        // Akun yang sudah ada: username = bagian depan email (sebelum @), dibuat unik. Admin dapat mengubahnya di menu Pengguna.
        $dipakai = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'name']) as $u) {
            $dasar = User::bersihkan(explode('@', (string) $u->email)[0]) ?: User::bersihkan((string) $u->name) ?: 'user';
            $username = $dasar;
            for ($i = 2; isset($dipakai[$username]); $i++) {
                $username = $dasar.$i;
            }
            $dipakai[$username] = true;
            DB::table('users')->where('id', $u->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            });
        }
    }
};
