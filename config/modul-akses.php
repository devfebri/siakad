<?php

/*
| Alamat (pola request()->is) yang termasuk setiap modul paket. Sekolah yang paketnya tidak memuat modul tersebut
| tidak dapat membuka alamatnya (App\Http\Middleware\CekLangganan). Fitur inti (data siswa/guru/kelas/mapel/jadwal,
| pengaturan, profil, langganan) tidak dibatasi.
*/
return [
    'modul' => [
        'absensi' => ['admin/absensi*', 'guru/absensi*', 'siswa/kehadiran*', 'kepsek/kehadiran*', 'ortu/kehadiran*', 'guru/wali*'],
        'nilai_rapor' => ['admin/nilai*', 'guru/nilai*', 'siswa/nilai*', 'admin/rapor*', 'guru/rapor*', 'kepsek/rapor*', 'ortu/nilai*'],
        'konten_web' => [
            'admin/berita*', 'guru/berita*', 'admin/kategori*', 'guru/kategori*', 'admin/gallery*', 'admin/pengumuman*', 'admin/agenda*',
            'berita*', 'galeri*', 'gallery*', 'pengumuman*', 'agenda*',
        ],
        'ppdb' => ['admin/ppdb*', 'ppdb*'],
        'konsultasi_online' => ['admin/chat*', 'chat', 'chat/*'],
        'unduhan' => ['admin/unduhan*', 'unduhan*'],
        // Panel orang tua terbuka bila sekolah punya Portal Orang Tua ATAU Keuangan (orang tua membayar tagihan). Kehadiran dan nilai tetap khusus Portal Orang Tua.
        'portal_orang_tua|keuangan' => ['ortu', 'ortu/*', 'admin/orang-tua*'],
        'portal_orang_tua' => ['ortu/kehadiran*', 'ortu/nilai*'],
        'keuangan' => ['admin/keuangan*', 'bendahara', 'bendahara/*', 'ortu/tagihan*', 'ortu/kuitansi*'],
        'integrasi_elearning' => ['admin/integrasi*'],
        'notifikasi_wa' => ['admin/wa*'],
    ],

    /*
    | Alamat yang tetap boleh menerima POST/PUT/PATCH/DELETE saat sekolah dalam mode baca-saja
    | (langganan habis): masuk/keluar, pindah panel, login-sebagai, pengajuan perpanjangan, serta formulir pengunjung publik
    | (pendaftaran PPDB dan konsultasi online) yang tetap melayani calon siswa.
    */
    'boleh_saat_baca_saja' => [
        'login', 'logout', 'ganti-panel/*', 'impersonasi', 'impersonasi/*', 'admin/langganan/ajukan', 'admin/langganan/invoice/*', 'ortu/tagihan/bayar',
        'ppdb', 'ppdb/cek', 'chat/*',
    ],
];
