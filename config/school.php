<?php

/*
| Identitas sekolah. Aplikasi ini dipakai di banyak sekolah, jadi semua tampilan
| mengambil nama, logo, dan warna dari sini, bukan ditulis di view.
|
| Nilai di bawah adalah bawaan (dari .env). Admin dapat menimpanya di menu Pengaturan:
| nilai yang tersimpan di database (tabel site_settings) diterapkan oleh middleware
| App\Http\Middleware\ApplySiteSettings pada setiap permintaan web.
*/
return [
    'name' => env('SCHOOL_NAME', 'Nama Sekolah'),
    'short_name' => env('SCHOOL_SHORT_NAME', 'Sekolah'),
    'tagline' => env('SCHOOL_TAGLINE', 'Sistem Informasi Sekolah'),

    // Path relatif terhadap folder public, mis. "images/logo.png". Kosong = tampilkan ikon topi wisuda.
    'logo' => env('SCHOOL_LOGO'),

    // Tema warna aplikasi: navy, hijau, marun, ungu, teal, cokelat, slate (lihat App\Support\Tema).
    'tema_warna' => env('SCHOOL_TEMA_WARNA', 'navy'),

    // Identitas tambahan
    'npsn' => null,
    'akreditasi' => null,
    'tahun_berdiri' => null,

    // Kontak dan media sosial
    'alamat' => null,
    'telepon' => null,
    'email' => null,
    'whatsapp' => null,
    'jam_layanan' => 'Senin – Jumat, 07.00 – 15.00 WIB',
    'peta_embed_url' => null,
    'facebook' => null,
    'instagram' => null,
    'youtube' => null,
    'tiktok' => null,

    // Beranda
    // Tema tampilan situs publik: 'klasik' (lama) atau 'portal' (baru). Dapat diganti di menu Pengaturan.
    'tema_depan' => env('SCHOOL_TEMA', 'klasik'),
    'hero_judul' => 'Mewujudkan Generasi Cerdas, Berkarakter, dan Berprestasi',
    'hero_deskripsi' => 'Lingkungan belajar yang hangat dan modern untuk mengembangkan potensi setiap siswa.',
    'hero_gambar' => null,
    'kepsek_nama' => null,
    'kepsek_jabatan' => 'Kepala Sekolah',
    'kepsek_foto' => null,
    'kepsek_sambutan' => 'Pendidikan bukan sekadar mengisi pikiran, melainkan menyalakan semangat untuk terus belajar sepanjang hayat.',
    'stat_prestasi' => null,
    'stat_alumni' => null,

    // PPDB
    'ppdb_aktif' => false,
    'ppdb_tahun_ajaran' => null,
    'ppdb_buka' => null,
    'ppdb_tutup' => null,
    'ppdb_kuota' => null,
    'ppdb_info' => null,
];
