<?php

namespace Database\Seeders;

use App\Agenda;
use App\Berita;
use App\Guru;
use App\Kategori;
use App\Kelas;
use App\Mapel;
use App\PpdbPendaftar;
use App\Pengumuman;
use App\Siswa;
use App\Support\Akademik;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tambahan data contoh agar daftar/pagination terlihat hidup: guru, siswa, berita, pengumuman, agenda, dan pendaftar PPDB
 * (masing-masing 10 baris dengan data bawaan). Aman dijalankan berulang. Password akun: "password".
 */
class DataContohSeeder extends Seeder
{
    public function run(): void
    {
        // Akun guru/siswa contoh tidak dibuat lagi: UserSeeder sudah menyediakan satu akun per peran.
        $this->berita();
        $this->pengumuman();
        $this->agenda();
        $this->ppdb();
    }

    private function akun(string $role, string $email, string $nama): User
    {
        return User::updateOrCreate(['email' => $email], ['name' => $nama, 'role' => $role, 'password' => 'password']);
    }

    private function guru(): void
    {
        $mapel = Mapel::orderBy('id')->pluck('id');
        $data = [
            ['Ahmad Fauzi', 'Laki-Laki'], ['Rina Marlina', 'Perempuan'], ['Dedi Kurniawan', 'Laki-Laki'], ['Lestari Wulandari', 'Perempuan'],
            ['Hendra Gunawan', 'Laki-Laki'], ['Maya Sari', 'Perempuan'], ['Yusuf Maulana', 'Laki-Laki'], ['Putri Handayani', 'Perempuan'],
            ['Agus Salim', 'Laki-Laki'], ['Nurul Hidayah', 'Perempuan'],
        ];

        foreach ($data as $i => [$nama, $jk]) {
            $n = $i + 1;
            $user = $this->akun('guru', 'guru.contoh'.$n.'@example.test', $nama);
            Guru::updateOrCreate(['user_id' => $user->id], [
                'nip_guru' => sprintf('1990%02d%02d2015%04d', $n, $n, $n), 'nama_guru' => $nama, 'mapel_id' => $mapel[$i % $mapel->count()],
                'jenis_kelamin' => $jk, 'no_hp' => '08123456'.sprintf('%04d', $n), 'tanggal_lahir' => sprintf('198%d-%02d-15', $i % 10, ($i % 12) + 1),
            ]);
        }
    }

    private function siswa(): void
    {
        $kelas = Kelas::orderBy('id')->pluck('id');
        $data = [
            ['Aldi Saputra', 'Laki-Laki'], ['Bunga Citra', 'Perempuan'], ['Cahyo Nugroho', 'Laki-Laki'], ['Dinda Permata', 'Perempuan'],
            ['Eko Prasetyo', 'Laki-Laki'], ['Fitri Ramadani', 'Perempuan'], ['Gilang Mahendra', 'Laki-Laki'], ['Hana Safitri', 'Perempuan'],
            ['Irfan Hakim', 'Laki-Laki'], ['Jihan Aulia', 'Perempuan'],
        ];

        foreach ($data as $i => [$nama, $jk]) {
            $n = $i + 1;
            $user = $this->akun('siswa', 'siswa.contoh'.$n.'@example.test', $nama);
            Siswa::updateOrCreate(['user_id' => $user->id], [
                'nisn' => '00200'.sprintf('%05d', $n), 'nama_siswa' => $nama, 'agama' => $i % 5 === 4 ? 'Kristen' : 'Islam', 'jenis_kelamin' => $jk,
                'tanggal_lahir' => sprintf('2010-%02d-%02d', ($i % 12) + 1, ($i * 2) % 27 + 1), 'kelas_id' => $kelas[$i % $kelas->count()],
            ]);
        }
    }

    private function berita(): void
    {
        $kategori = Kategori::orderBy('id')->pluck('id');
        $data = [
            'Juara Lomba Cerdas Cermat Tingkat Kabupaten', 'Kegiatan Jumat Bersih Bersama Warga Sekolah', 'Pelatihan Guru: Pembelajaran Berbasis Proyek',
            'Pentas Seni Akhir Tahun Meriah', 'Kunjungan Edukasi ke Museum Daerah', 'Tim Futsal Sekolah Lolos Semifinal',
            'Perpustakaan Tambah Koleksi 500 Buku Baru', 'Sosialisasi Bahaya Narkoba dan Perundungan', 'Peringatan Hari Guru Nasional',
            'Penerimaan Siswa Baru Segera Dibuka',
        ];

        foreach ($data as $i => $judul) {
            Berita::firstOrCreate(['berita_judul' => $judul], [
                'berita_isi' => "<p>{$judul}. Kegiatan berlangsung lancar dengan partisipasi aktif siswa, guru, dan orang tua. Panitia berterima kasih kepada semua pihak yang telah mendukung terlaksananya acara ini.</p><p>Dokumentasi lengkap dapat dilihat pada halaman galeri sekolah.</p>",
                'kategori_id' => $kategori[$i % $kategori->count()],
                'author' => 'Admin Sekolah',
                'berita_views' => 20 + $i * 13,
                'berita_tanggal' => now()->subDays($i * 3),
            ]);
        }
    }

    private function pengumuman(): void
    {
        $data = [
            'Jadwal Penilaian Tengah Semester', 'Pembagian Rapor Semester Ganjil', 'Libur Nasional dan Cuti Bersama', 'Pendaftaran Ekstrakurikuler Baru',
            'Pengumpulan Berkas Beasiswa Prestasi', 'Perubahan Jam Pelajaran Hari Jumat', 'Kerja Bakti Menyambut Tahun Ajaran Baru',
            'Pemeriksaan Kesehatan Siswa Gratis', 'Rapat Orang Tua Siswa Kelas XII', 'Ujian Praktik Mata Pelajaran Seni dan Olahraga',
        ];

        foreach ($data as $i => $judul) {
            Pengumuman::firstOrCreate(['pengumuman_judul' => $judul], [
                'pengumuman_deskripsi' => "{$judul}. Informasi lengkap dapat ditanyakan kepada wali kelas atau ke ruang tata usaha pada jam layanan.",
                'pengumuman_author' => 'Admin Sekolah',
                'pengumuman_tanggal' => now()->subDays($i * 2)->format('Y-m-d'),
            ]);
        }
    }

    private function agenda(): void
    {
        $data = [
            ['Upacara Bendera Senin', 'Lapangan Sekolah', '07:00'], ['Rapat Dewan Guru', 'Ruang Guru', '13:00'], ['Penilaian Tengah Semester', 'Ruang Kelas', '07:30'],
            ['Lomba Kebersihan Kelas', 'Seluruh Kelas', '08:00'], ['Pentas Seni Siswa', 'Aula Sekolah', '09:00'], ['Pertemuan Komite Sekolah', 'Aula Sekolah', '10:00'],
            ['Pelatihan Kepramukaan', 'Lapangan Sekolah', '14:00'], ['Kunjungan Perpustakaan Daerah', 'Perpustakaan Daerah', '08:30'],
            ['Pembagian Rapor', 'Ruang Kelas', '08:00'], ['Peringatan Hari Kemerdekaan', 'Lapangan Sekolah', '07:30'],
        ];

        foreach ($data as $i => [$nama, $tempat, $waktu]) {
            $tanggal = now()->addDays(($i + 1) * 4)->format('Y-m-d');
            Agenda::firstOrCreate(['agenda_nama' => $nama], [
                'agenda_deskripsi' => "{$nama} diikuti oleh seluruh warga sekolah sesuai jadwal.",
                'agenda_mulai' => $tanggal, 'agenda_selesai' => $tanggal, 'agenda_tempat' => $tempat,
                'agenda_waktu' => $waktu, 'agenda_keterangan' => '-', 'agenda_author' => 'Admin Sekolah',
            ]);
        }
    }

    private function ppdb(): void
    {
        $tahun = Akademik::aktif()['tahun_ajaran'];
        $data = [
            ['Kayla Anindita', 'Perempuan', 'Reguler', 'IPA', 'menunggu'], ['Raffi Pratama', 'Laki-Laki', 'Prestasi', 'IPA', 'diterima'],
            ['Salma Nabila', 'Perempuan', 'Zonasi', 'IPS', 'menunggu'], ['Tegar Wibowo', 'Laki-Laki', 'Reguler', 'IPS', 'ditolak'],
            ['Uli Rahmawati', 'Perempuan', 'Afirmasi', 'Bahasa', 'diterima'], ['Vino Alamsyah', 'Laki-Laki', 'Prestasi', 'IPA', 'menunggu'],
            ['Wulan Maharani', 'Perempuan', 'Reguler', 'Bahasa', 'menunggu'], ['Yoga Firmansyah', 'Laki-Laki', 'Zonasi', 'IPS', 'diterima'],
            ['Zahra Putri', 'Perempuan', 'Reguler', 'IPA', 'menunggu'], ['Bayu Setiawan', 'Laki-Laki', 'Afirmasi', 'IPS', 'menunggu'],
        ];

        foreach ($data as $i => [$nama, $jk, $jalur, $peminatan, $status]) {
            $nisn = '98000000'.sprintf('%02d', $i + 1);
            if (PpdbPendaftar::where('nisn', $nisn)->exists()) {
                continue;
            }

            PpdbPendaftar::daftar([
                'tahun_ajaran' => $tahun, 'nama' => $nama, 'nisn' => $nisn, 'jenis_kelamin' => $jk, 'tempat_lahir' => 'Jambi',
                'tanggal_lahir' => sprintf('2011-%02d-%02d', ($i % 12) + 1, ($i * 3) % 27 + 1), 'asal_sekolah' => 'SMP Negeri '.($i + 1).' Contoh',
                'alamat' => 'Jl. Melati No. '.($i + 1).', Jambi', 'nama_ortu' => 'Orang Tua '.$nama, 'telepon' => '0812000'.sprintf('%04d', $i + 1),
                'jalur' => $jalur, 'peminatan' => $peminatan, 'nilai_rata' => 78 + $i * 1.7, 'status' => $status,
            ]);
        }
    }
}
