<?php

/*
| Isi halaman produk (landing). Ubah teks di sini tanpa menyentuh tampilan.
| Harga, perbandingan paket, dan modul diambil dari database (menu Paket & Modul di panel platform).
*/
return [
    'fitur' => [
        ['icon' => 'people', 'judul' => 'Data sekolah rapi', 'isi' => 'Siswa, guru, kelas, dan jadwal tersimpan di satu tempat, bisa diimpor dari Excel.'],
        ['icon' => 'clipboard2-check', 'judul' => 'Absensi dan nilai', 'isi' => 'Guru mencatat kehadiran dan nilai dengan sedikit ketukan. Rapor siap dicetak.'],
        ['icon' => 'globe2', 'judul' => 'Website sekolah sendiri', 'isi' => 'Berita, pengumuman, galeri, dan pendaftaran siswa baru (PPDB) di alamat sekolah Anda.'],
        ['icon' => 'person-hearts', 'judul' => 'Orang tua ikut memantau', 'isi' => 'Orang tua melihat kehadiran dan nilai anak dari ponsel, tanpa perlu datang ke sekolah.'],
        ['icon' => 'chat-dots', 'judul' => 'Konsultasi online', 'isi' => 'Calon wali murid bisa bertanya langsung lewat chat di website sekolah.'],
        ['icon' => 'shield-check', 'judul' => 'Data terpisah per sekolah', 'isi' => 'Setiap sekolah punya data sendiri. Tidak ada yang bisa melihat data sekolah lain.'],
    ],

    'faq' => [
        ['t' => 'Apakah harus pasang aplikasi atau beli server?', 'j' => 'Tidak. Semua berjalan di internet. Cukup buka lewat peramban (browser) di komputer atau ponsel.'],
        ['t' => 'Apa yang terjadi kalau masa langganan habis?', 'j' => 'Ada masa tenggang 7 hari. Setelah itu sekolah masuk mode baca-saja: data tetap aman dan bisa dilihat, tetapi tidak bisa diubah sampai langganan diperpanjang.'],
        ['t' => 'Bagaimana dengan data siswa dan guru yang sudah ada?', 'j' => 'Bisa dimasukkan sekaligus lewat impor Excel. Ada contoh berkasnya di menu Impor Data.'],
        ['t' => 'Apakah bisa pakai alamat website sendiri?', 'j' => 'Bisa. Selain alamat bawaan (namasekolah.domain-kami), sekolah bisa memakai domain sendiri, misalnya www.sekolahku.sch.id.'],
        ['t' => 'Bagaimana cara membayar?', 'j' => 'Setelah mendaftar, tagihan muncul di menu Langganan Saya. Pembayaran bisa lewat transfer, atau pembayaran online bila sudah tersedia.'],
        ['t' => 'Apakah ada masa percobaan gratis?', 'j' => 'Ada, selama platform menawarkannya (lihat keterangan di halaman ini). Percobaan tidak memerlukan kartu atau pembayaran.'],
    ],
];
