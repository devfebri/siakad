<?php

/*
| Halaman profil sekolah yang bisa diedit di menu "Profil Sekolah".
| id    : id baris di tabel `pengaturan` (dibaca langsung oleh ProfileDepanController)
| dir   : folder gambar di public/storage/pengaturan/{dir}; null = bagian ini tidak punya gambar
*/
return [
    1 => ['label' => 'Tentang Kami', 'icon' => 'info-circle', 'dir' => 'tentangkami'],
    2 => ['label' => 'Visi dan Misi', 'icon' => 'bullseye', 'dir' => 'visimisi'],
    3 => ['label' => 'Sejarah', 'icon' => 'clock-history', 'dir' => null],
    4 => ['label' => 'Sosial Media', 'icon' => 'share', 'dir' => null],
    5 => ['label' => 'Administrasi Kantor', 'icon' => 'folder2-open', 'dir' => null],
    6 => ['label' => 'Perpustakaan', 'icon' => 'book', 'dir' => 'perpustakaan'],
    7 => ['label' => 'Lab Komputer', 'icon' => 'pc-display', 'dir' => 'labkom'],
    8 => ['label' => 'Struktur Organisasi', 'icon' => 'diagram-3', 'dir' => 'strukturorganisasi'],
];
