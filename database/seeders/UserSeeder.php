<?php

namespace Database\Seeders;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\OrangTua;
use App\Peran;
use App\Siswa;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Database\Seeder;

/**
 * Akun uji: satu per peran. Semua password: "password". Login memakai username.
 *   admin, kepsek, guru, wali (wali kelas rombel pertama), siswa, ortu (orang tua siswa), bendahara, superadmin (platform).
 * Admin, kepsek, guru, dan wali juga punya data guru karena sidebar dan profil membacanya.
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $mapel = Mapel::orderBy('id')->pluck('id');
        $kelas = Kelas::orderBy('id')->get();

        // [username, peran, nama, nip, jenis kelamin, mapel]
        $guru = [
            ['admin', 'admin', 'Admin Sekolah', '198001010001', 'Laki-Laki', $mapel[0]],
            ['kepsek', 'kepsek', 'Drs. Hasan Basri', '197501010002', 'Laki-Laki', $mapel[0]],
            ['guru', 'guru', 'Budi Santoso', '198505050003', 'Laki-Laki', $mapel[0]],
            ['wali', 'guru', 'Siti Aminah', '199002020004', 'Perempuan', $mapel[1] ?? $mapel[0]],
        ];
        $dataGuru = [];
        foreach ($guru as [$username, $peran, $nama, $nip, $jk, $mapelId]) {
            $user = $this->user($username, $peran, $nama);
            $dataGuru[$username] = Guru::updateOrCreate(['user_id' => $user->id], [
                'nip_guru' => $nip, 'nama_guru' => $nama, 'mapel_id' => $mapelId, 'jenis_kelamin' => $jk,
                'no_hp' => '081234567890', 'tanggal_lahir' => '1985-05-05',
            ]);
        }

        // Wali kelas: rombel pertama diampu "wali"; peran wali_kelas diturunkan dari penugasan ini.
        if ($rombel = $kelas->first()) {
            $rombel->update(['wali_guru_id' => $dataGuru['wali']->id]);
            Kelas::sinkronkanPeranWali();
        }

        $user = $this->user('siswa', 'siswa', 'Andi Pratama');
        $siswa = Siswa::updateOrCreate(['user_id' => $user->id], [
            'nisn' => '0010000001', 'nama_siswa' => 'Andi Pratama', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki',
            'tanggal_lahir' => '2010-03-15', 'kelas_id' => $kelas->first()?->id,
        ]);

        $user = $this->user('ortu', 'orang_tua', 'Bambang Pratama');
        $ortu = OrangTua::updateOrCreate(['user_id' => $user->id], ['nama' => 'Bambang Pratama', 'no_hp' => '081234567891', 'pekerjaan' => 'Wiraswasta', 'alamat' => 'Jl. Contoh No. 1']);
        $ortu->hubungkan($siswa, 'ayah');

        $this->user('bendahara', 'bendahara', 'Rina Bendahara');

        SekolahAktif::jalankan(null, function () {
            $user = User::firstOrNew(['username' => 'superadmin']);
            $user->fill(['name' => 'Super Admin', 'email' => 'superadmin@example.test', 'password' => self::PASSWORD, 'sekolah_id' => null])->save();
            $user->setPeran([Peran::SUPER_ADMIN]);
        });
    }

    private function user(string $username, string $peran, string $nama): User
    {
        $user = User::firstOrNew(['username' => $username]);
        $user->fill(['name' => $nama, 'email' => $username.'@example.test', 'password' => self::PASSWORD, 'role' => $peran])->save();

        return $user;
    }
}
