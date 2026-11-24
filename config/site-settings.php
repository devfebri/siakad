<?php

/*
| Definisi kolom pengaturan situs (menu Pengaturan). Setiap grup menjadi satu formulir.
|   type  : text | textarea | url | email | number | date | datetime | color | image | select | checkbox
|   rules : aturan validasi Laravel
|   config: kunci config yang ditimpa nilainya (bawaan: school.<kunci>)
| Nilai disimpan di tabel site_settings dan diterapkan oleh middleware ApplySiteSettings.
*/
return [
    'identitas' => [
        'label' => 'Identitas Sekolah',
        'icon' => 'building',
        'fields' => [
            'nama_sekolah' => ['label' => 'Nama Sekolah', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'config' => 'school.name'],
            'nama_singkat' => ['label' => 'Nama Singkat', 'type' => 'text', 'rules' => ['required', 'string', 'max:40'], 'config' => 'school.short_name', 'hint' => 'Dipakai di judul tab dan tempat yang sempit.'],
            'tagline' => ['label' => 'Slogan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150'], 'config' => 'school.tagline'],
            'logo' => ['label' => 'Logo', 'type' => 'image', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'], 'config' => 'school.logo', 'hint' => 'PNG/JPG/WEBP, maks. 1 MB. Latar transparan paling cocok.'],
            'tema_warna' => ['label' => 'Tema Warna', 'type' => 'select', 'options' => \App\Support\Tema::opsi(), 'rules' => ['required', 'in:'.implode(',', array_keys(\App\Support\Tema::PALET))], 'config' => 'school.tema_warna', 'hint' => 'Mengganti seluruh warna aplikasi: panel admin, guru, siswa, login, dan website sekolah.'],
            'npsn' => ['label' => 'NPSN', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20']],
            'akreditasi' => ['label' => 'Akreditasi', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'hint' => 'Mis. A'],
            'tahun_berdiri' => ['label' => 'Tahun Berdiri', 'type' => 'number', 'rules' => ['nullable', 'integer', 'between:1900,2100']],
        ],
    ],

    'kontak' => [
        'label' => 'Kontak & Media Sosial',
        'icon' => 'telephone',
        'fields' => [
            'alamat' => ['label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
            'telepon' => ['label' => 'Telepon', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40']],
            'email' => ['label' => 'Email Sekolah', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:150']],
            'whatsapp' => ['label' => 'WhatsApp', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'hint' => 'Format 628xxxxxxxxxx (tanpa tanda +).'],
            'jam_layanan' => ['label' => 'Jam Layanan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
            'peta_embed_url' => ['label' => 'Tautan Embed Google Maps', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:600'], 'hint' => 'Google Maps → Bagikan → Sematkan peta → salin nilai src pada iframe.'],
            'facebook' => ['label' => 'Facebook', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:200']],
            'instagram' => ['label' => 'Instagram', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:200']],
            'youtube' => ['label' => 'YouTube', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:200']],
            'tiktok' => ['label' => 'TikTok', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:200']],
        ],
    ],

    'beranda' => [
        'label' => 'Beranda & Sambutan',
        'icon' => 'house-heart',
        'fields' => [
            'tema_depan' => ['label' => 'Tema Tampilan Website', 'type' => 'select', 'options' => ['klasik' => 'Klasik (tampilan lama)', 'portal' => 'Portal (gaya sekolah SMK: slider, berita, mitra)', 'modern' => 'Modern (hero membulat, kartu bento, berita unggulan)'], 'rules' => ['required', 'in:klasik,portal,modern'], 'config' => 'school.tema_depan', 'hint' => 'Bisa diganti kapan saja tanpa kehilangan data.'],
            'hero_judul' => ['label' => 'Judul Utama (Slogan/Visi)', 'type' => 'text', 'rules' => ['required', 'string', 'max:160']],
            'hero_deskripsi' => ['label' => 'Deskripsi Singkat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:400']],
            'hero_gambar' => ['label' => 'Foto Utama (Hero)', 'type' => 'image', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], 'hint' => 'Foto siswa/sekolah, rasio mendekati persegi atau 4:5. Maks. 2 MB.'],
            'kepsek_nama' => ['label' => 'Nama Kepala Sekolah', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:120']],
            'kepsek_jabatan' => ['label' => 'Jabatan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:80']],
            'kepsek_foto' => ['label' => 'Foto Kepala Sekolah', 'type' => 'image', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']],
            'kepsek_sambutan' => ['label' => 'Kutipan Sambutan', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1200']],
            'stat_prestasi' => ['label' => 'Jumlah Prestasi', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0'], 'hint' => 'Jumlah siswa dan guru dihitung otomatis dari data.'],
            'stat_alumni' => ['label' => 'Jumlah Alumni', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0']],
        ],
    ],

    'ppdb' => [
        'label' => 'PPDB',
        'icon' => 'person-plus',
        'fields' => [
            'ppdb_aktif' => ['label' => 'Pendaftaran PPDB dibuka', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
            'ppdb_tahun_ajaran' => ['label' => 'Tahun Ajaran PPDB', 'type' => 'text', 'rules' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'], 'hint' => 'Mis. 2027/2028'],
            'ppdb_buka' => ['label' => 'Tanggal Dibuka', 'type' => 'datetime', 'rules' => ['nullable', 'date']],
            'ppdb_tutup' => ['label' => 'Tanggal Ditutup', 'type' => 'datetime', 'rules' => ['nullable', 'date', 'after_or_equal:ppdb_buka'], 'hint' => 'Dipakai untuk hitung mundur di beranda.'],
            'ppdb_kuota' => ['label' => 'Kuota Penerimaan', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1']],
            'ppdb_info' => ['label' => 'Persyaratan & Informasi', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000'], 'hint' => 'Tampil di halaman PPDB. Satu persyaratan per baris.'],
        ],
    ],
];
