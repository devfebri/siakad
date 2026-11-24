<?php

namespace Database\Seeders;

use App\Berita;
use App\Gallery;
use App\Pengaturan;
use App\ProgramUnggulan;
use App\Setting;
use App\Testimoni;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Data contoh situs publik dengan foto nyata (database/seeders/foto, sumber Unsplash, lisensi bebas pakai).
 * Semuanya bisa diganti dari panel admin. Data yang sudah diisi admin tidak ditimpa.
 */
class SitusSeeder extends Seeder
{
    private const FOTO = __DIR__.'/foto';

    public function run(): void
    {
        $this->pengaturan();
        $this->profil();
        $this->program();
        $this->testimoni();
        $this->berita();
        $this->galeri();
    }

    /** Salin foto contoh ke folder publik; kembalikan nama berkas (null bila foto tidak ada). */
    private function salin(string $nama, string $tujuan): ?string
    {
        $sumber = self::FOTO.'/'.$nama.'.jpg';
        if (! File::isFile($sumber)) {
            return null;
        }

        File::ensureDirectoryExists(public_path($tujuan));
        $berkas = 'demo-'.$nama.'.jpg';
        if (! File::isFile(public_path($tujuan.'/'.$berkas))) {
            File::copy($sumber, public_path($tujuan.'/'.$berkas));
        }

        return $berkas;
    }

    private function pengaturan(): void
    {
        $isi = [
            'nama_sekolah' => 'SMA Negeri 1 Contoh', 'nama_singkat' => 'SMAN 1 Contoh', 'tagline' => 'Cerdas, Berkarakter, Berprestasi',
            'akreditasi' => 'A', 'tahun_berdiri' => '1985', 'npsn' => '10000001',
            'alamat' => 'Jl. Pendidikan No. 1, Kecamatan Contoh, Kabupaten Contoh, Jambi 36361',
            'telepon' => '(0741) 123456', 'email' => 'info@sman1contoh.sch.id', 'whatsapp' => '6281234567890',
            'jam_layanan' => 'Senin–Jumat, 07.30–15.30 WIB',
            'facebook' => 'https://facebook.com/', 'instagram' => 'https://instagram.com/', 'youtube' => 'https://youtube.com/',
            'hero_judul' => 'Mewujudkan Generasi Cerdas, Berkarakter, dan Berprestasi',
            'hero_deskripsi' => 'Lingkungan belajar yang hangat dan modern untuk mengembangkan potensi setiap siswa, di kelas maupun di luar kelas.',
            'kepsek_nama' => 'Dr. Hendra Wijaya, M.Pd.', 'kepsek_jabatan' => 'Kepala Sekolah',
            'kepsek_sambutan' => 'Selamat datang di website kami. Kami berkomitmen menghadirkan pendidikan yang bermutu, menumbuhkan karakter, dan membuka jalan bagi setiap siswa untuk meraih mimpinya. Mari tumbuh dan berprestasi bersama kami.',
            'stat_prestasi' => '120', 'stat_alumni' => '5400',
        ];

        $simpan = [];
        foreach ($isi as $kunci => $nilai) {
            if (Setting::get($kunci) === null) {
                $simpan[$kunci] = $nilai;
            }
        }

        foreach (['hero_gambar' => 'hero', 'kepsek_foto' => 'kepsek'] as $kunci => $foto) {
            if (Setting::get($kunci) === null && ($berkas = $this->salin($foto, 'storage/situs'))) {
                $simpan[$kunci] = 'storage/situs/'.$berkas;
            }
        }

        if ($simpan) {
            Setting::put($simpan);
        }
    }

    /** Delapan halaman profil sekolah (tabel pengaturan id 1–8). Hanya mengganti isi yang masih berupa contoh bawaan. */
    private function profil(): void
    {
        $halaman = [
            1 => ['Tentang Kami', 'tentangkami', 'tentang', '<p>SMA Negeri 1 Contoh adalah sekolah menengah atas negeri yang berdiri sejak 1985. Kami hadir untuk menyiapkan generasi muda yang cerdas, berakhlak, dan siap melanjutkan pendidikan maupun terjun ke masyarakat.</p><p>Dengan guru-guru berpengalaman, fasilitas belajar yang terus diperbarui, serta beragam kegiatan pengembangan diri, kami mendampingi setiap siswa menemukan dan mengembangkan potensinya.</p><ul><li>Akreditasi A</li><li>Kurikulum Merdeka dengan peminatan IPA, IPS, dan Bahasa</li><li>Lebih dari 20 kegiatan ekstrakurikuler</li></ul>'],
            2 => ['Visi dan Misi', 'visimisi', 'visimisi', '<h3>Visi</h3><p>Terwujudnya peserta didik yang beriman, cerdas, berkarakter, dan berprestasi di tingkat nasional maupun global.</p><h3>Misi</h3><ol><li>Menyelenggarakan pembelajaran yang aktif, kreatif, dan menyenangkan.</li><li>Menanamkan nilai keimanan, kejujuran, disiplin, dan tanggung jawab.</li><li>Mengembangkan bakat dan minat melalui kegiatan akademik dan non-akademik.</li><li>Membangun budaya literasi, sains, dan teknologi.</li><li>Menjalin kemitraan dengan orang tua, masyarakat, dan dunia usaha.</li></ol>'],
            3 => ['Sejarah', null, null, '<p>Sekolah ini didirikan pada tahun 1985 dengan hanya tiga ruang kelas dan seratus siswa angkatan pertama. Berkat dukungan masyarakat dan dedikasi para pendidik, sekolah berkembang pesat.</p><p>Pada tahun 2005 sekolah memperoleh akreditasi A untuk pertama kalinya, disusul pembangunan perpustakaan dan laboratorium komputer. Kini ribuan alumni telah berkiprah di berbagai bidang.</p>'],
            4 => ['Sosial Media', null, null, '<p>Ikuti kegiatan sekolah melalui Instagram, Facebook, dan YouTube resmi kami. Tautan lengkap tersedia di bagian bawah halaman ini.</p>'],
            5 => ['Administrasi Kantor Sekolah', null, null, '<p>Layanan administrasi dibuka Senin–Jumat pukul 07.30–15.30 WIB. Untuk surat keterangan siswa, legalisir ijazah, dan pengurusan mutasi, silakan datang ke ruang tata usaha dengan membawa berkas pendukung.</p>'],
            6 => ['Perpustakaan', 'perpustakaan', 'perpustakaan', '<p>Perpustakaan menyediakan lebih dari 8.000 judul buku pelajaran, fiksi, dan referensi, serta ruang baca yang nyaman dan akses internet. Siswa dapat meminjam hingga tiga buku selama dua pekan.</p>'],
            7 => ['Lab Komputer', 'labkom', 'labkom', '<p>Laboratorium komputer kami dilengkapi 40 unit komputer dan jaringan internet cepat untuk pembelajaran informatika, ujian berbasis komputer, dan kegiatan klub pemrograman.</p>'],
            8 => ['Struktur Organisasi', 'strukturorganisasi', 'struktur', '<p>Kepala Sekolah dibantu oleh empat wakil kepala sekolah (kurikulum, kesiswaan, sarana prasarana, dan humas), kepala tata usaha, serta para wali kelas dan guru mata pelajaran.</p>'],
        ];

        foreach ($halaman as $id => [$judul, $dir, $foto, $deskripsi]) {
            $row = Pengaturan::where('slot', $id)->first() ?? new Pengaturan();
            $row->slot = $id;
            $masihContoh = ! $row->exists || blank(strip_tags((string) $row->deskripsi)) || str_starts_with((string) $row->deskripsi, '<p>Konten contoh');
            if (! $masihContoh) {
                continue;
            }

            $row->judul = $judul;
            $row->deskripsi = $deskripsi;
            if ($dir && ! $row->gambar && ($berkas = $this->salin($foto, 'storage/pengaturan/'.$dir))) {
                $row->gambar = $berkas;
            }
            $row->save();
        }
    }

    private function program(): void
    {
        if (ProgramUnggulan::exists()) {
            return;
        }

        $data = [
            ['Sains & Teknologi', 'Laboratorium modern, klub robotika, dan olimpiade sains.', 'flask'],
            ['Literasi & Bahasa', 'Budaya baca, debat, dan kelas bahasa Inggris intensif.', 'translate'],
            ['Seni & Budaya', 'Musik, tari, dan teater untuk menyalurkan kreativitas.', 'palette'],
            ['Olahraga & Prestasi', 'Pembinaan atlet dan kompetisi antarsekolah.', 'trophy'],
        ];
        foreach ($data as $i => [$judul, $deskripsi, $ikon]) {
            ProgramUnggulan::create(['judul' => $judul, 'deskripsi' => $deskripsi, 'ikon' => $ikon, 'urutan' => $i + 1, 'aktif' => true]);
        }
    }

    private function testimoni(): void
    {
        if (Testimoni::exists()) {
            return;
        }

        $data = [
            ['Nabila Putri', 'Alumni 2022 – Mahasiswa Kedokteran', 'Guru-gurunya sabar dan benar-benar peduli. Persiapan masuk perguruan tinggi di sini sangat terarah sehingga saya percaya diri.', 'testi-1'],
            ['Rahmat Hidayat', 'Orang Tua Siswa Kelas XI', 'Anak saya jadi lebih disiplin dan semangat belajar. Komunikasi dengan wali kelas juga lancar dan terbuka.', null],
            ['Salsabila Ananda', 'Alumni 2021 – Mahasiswa Teknik', 'Kegiatan ekstrakurikuler dan laboratorium membuat saya terbiasa berpikir kritis. Bekal yang sangat berguna di bangku kuliah.', 'testi-2'],
        ];
        foreach ($data as $i => [$nama, $peran, $isi, $foto]) {
            Testimoni::create([
                'nama' => $nama, 'peran' => $peran, 'isi' => $isi, 'urutan' => $i + 1, 'aktif' => true,
                'foto' => $foto ? $this->salin($foto, Testimoni::DIR) : null,
            ]);
        }
    }

    private function berita(): void
    {
        $isi = [
            1 => '<p>Tim siswa kami berhasil membawa pulang medali pada olimpiade sains tingkat provinsi. Prestasi ini hasil latihan intensif selama berbulan-bulan dengan bimbingan para guru.</p><p>Kepala sekolah menyampaikan apresiasi dan berharap capaian ini memotivasi seluruh siswa untuk terus berprestasi.</p>',
            2 => '<p>Seluruh warga sekolah mengikuti upacara bendera dengan khidmat di halaman sekolah. Rangkaian acara dilanjutkan dengan lomba-lomba yang meriah dan penuh kebersamaan.</p>',
            3 => '<p>Penilaian akhir semester akan dilaksanakan sesuai jadwal yang telah ditetapkan. Siswa diminta mempersiapkan diri dan memeriksa jadwal pada papan pengumuman atau halaman siswa.</p>',
        ];

        foreach (Berita::orderBy('id')->get() as $i => $berita) {
            $no = $i % 3 + 1; // tiga foto berita dipakai bergilir
            if (! $berita->berita_gambar && ($berkas = $this->salin('berita-'.$no, Berita::IMAGE_DIR))) {
                $berita->berita_gambar = $berkas;
            }
            if (str_contains((string) $berita->berita_isi, 'isi berita contoh') && $i < 3) {
                $berita->berita_isi = $isi[$i + 1];
            }
            $berita->save();
        }
    }

    private function galeri(): void
    {
        foreach (range(1, 8) as $no) {
            if ($berkas = $this->salin('galeri-'.$no, 'storage/gallery')) {
                Gallery::firstOrCreate(['foto' => $berkas], ['author' => 'Admin Sekolah']);
            }
        }
    }
}
