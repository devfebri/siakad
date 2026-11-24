<?php

/*
| Menu sidebar per role. Satu sumber untuk semua role.
|   route : nama route
|   match : pola URL (request()->is) agar menu tetap aktif di halaman turunannya
|   icon  : nama ikon Bootstrap Icons tanpa awalan "bi-"
|   badge : kunci angka lencana (diisi App\View\Composers\AdminLayout)
*/
return [
    'admin' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'admin/dashboard', 'match' => ['admin/dashboard']],

        ['header' => 'Akademik'],
        ['label' => 'Data Siswa', 'icon' => 'mortarboard', 'route' => 'admin/siswa', 'match' => ['admin/siswa*']],
        ['label' => 'Data Guru', 'icon' => 'person-workspace', 'route' => 'admin/guru', 'match' => ['admin/guru*']],
        ['label' => 'Kelas & Jadwal', 'icon' => 'calendar3', 'route' => 'admin/kelas', 'match' => ['admin/kelas*', 'admin/mapel*', 'admin/jadwal*']],
        ['label' => 'Orang Tua', 'icon' => 'people-fill', 'route' => 'admin/orang-tua', 'match' => ['admin/orang-tua*'], 'modul' => 'portal_orang_tua'],
        ['label' => 'Tahun Ajaran', 'icon' => 'calendar-check', 'route' => 'admin/tahun-ajaran', 'match' => ['admin/tahun-ajaran*', 'admin/semester*']],
        ['label' => 'Proses Tahunan', 'icon' => 'arrow-repeat', 'route' => 'admin/proses-tahunan', 'match' => ['admin/proses-tahunan*']],
        ['label' => 'Data Alumni', 'icon' => 'award', 'route' => 'admin/alumni', 'match' => ['admin/alumni*']],
        ['label' => 'Nilai & Rapor', 'icon' => 'journal-check', 'route' => 'admin/nilai', 'match' => ['admin/nilai*', 'admin/rapor*'], 'modul' => 'nilai_rapor'],
        ['label' => 'Absensi', 'icon' => 'clipboard2-check', 'route' => 'admin/absensi', 'match' => ['admin/absensi*'], 'modul' => 'absensi'],

        ['header' => 'Konten Web'],
        ['label' => 'Berita', 'icon' => 'newspaper', 'route' => 'admin/berita', 'match' => ['admin/berita*', 'admin/kategori*'], 'modul' => 'konten_web'],
        ['label' => 'Galeri', 'icon' => 'images', 'route' => 'admin/gallery', 'match' => ['admin/gallery*'], 'modul' => 'konten_web'],
        ['label' => 'Pengumuman', 'icon' => 'megaphone', 'route' => 'admin/pengumuman', 'match' => ['admin/pengumuman*'], 'modul' => 'konten_web'],
        ['label' => 'Agenda', 'icon' => 'calendar-event', 'route' => 'admin/agenda', 'match' => ['admin/agenda*'], 'modul' => 'konten_web'],
        ['label' => 'Unduhan', 'icon' => 'cloud-download', 'route' => 'admin/unduhan', 'match' => ['admin/unduhan*'], 'modul' => 'unduhan'],

        ['header' => 'PPDB'],
        ['label' => 'PPDB', 'icon' => 'person-plus', 'route' => 'admin/ppdb', 'match' => ['admin/ppdb*'], 'badge' => 'ppdb', 'modul' => 'ppdb'],

        ['label' => 'Konsultasi Online', 'icon' => 'chat-dots', 'route' => 'admin/chat', 'match' => ['admin/chat*'], 'badge' => 'chat', 'modul' => 'konsultasi_online'],

        ['header' => 'Keuangan'],
        ['label' => 'Keuangan & SPP', 'icon' => 'cash-stack', 'route' => 'admin/keuangan', 'match' => ['admin/keuangan*'], 'modul' => 'keuangan'],

        ['header' => 'Sistem'],
        ['label' => 'Pengaturan Awal', 'icon' => 'rocket-takeoff', 'route' => 'admin/setup', 'match' => ['admin/setup*']],
        ['label' => 'Impor Data', 'icon' => 'file-earmark-arrow-up', 'route' => 'admin/impor', 'match' => ['admin/impor*']],
        ['label' => 'Backup Data', 'icon' => 'archive', 'route' => 'admin/backup', 'match' => ['admin/backup*']],
        ['label' => 'Domain Website', 'icon' => 'globe2', 'route' => 'admin/domain', 'match' => ['admin/domain*']],
        ['label' => 'Notifikasi WhatsApp', 'icon' => 'whatsapp', 'route' => 'admin/wa', 'match' => ['admin/wa*'], 'modul' => 'notifikasi_wa'],
        ['label' => 'Integrasi E-Learning', 'icon' => 'plug', 'route' => 'admin/integrasi', 'match' => ['admin/integrasi*'], 'modul' => 'integrasi_elearning'],
        ['label' => 'Langganan Saya', 'icon' => 'credit-card-2-front', 'route' => 'admin/langganan', 'match' => ['admin/langganan*']],
        ['label' => 'Pengguna', 'icon' => 'people', 'route' => 'admin/pengguna', 'match' => ['admin/pengguna*']],
        ['label' => 'Pengaturan', 'icon' => 'gear', 'route' => 'admin/pengaturan', 'match' => ['admin/pengaturan*', 'admin/program*', 'admin/testimoni*']],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'guru' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'guru/dashboard', 'match' => ['guru/dashboard']],

        ['header' => 'Mengajar'],
        ['label' => 'Usulan Kenaikan', 'icon' => 'signpost-2', 'route' => 'guru/usulan', 'match' => ['guru/usulan*'], 'peran' => 'wali_kelas'],
        ['label' => 'Kelas Wali', 'icon' => 'person-hearts', 'route' => 'guru/wali', 'match' => ['guru/wali*'], 'peran' => 'wali_kelas', 'modul' => 'absensi'],
        ['label' => 'Jadwal Mengajar', 'icon' => 'calendar3', 'route' => 'guru/jadwal', 'match' => ['guru/jadwal*']],
        ['label' => 'Kelas', 'icon' => 'door-open', 'route' => 'guru/kelas', 'match' => ['guru/kelas*']],
        ['label' => 'Mata Pelajaran', 'icon' => 'journal-bookmark', 'route' => 'guru/mapel', 'match' => ['guru/mapel*']],

        ['header' => 'Penilaian'],
        ['label' => 'Absensi', 'icon' => 'clipboard2-check', 'route' => 'guru/absensi', 'match' => ['guru/absensi*'], 'modul' => 'absensi'],
        ['label' => 'Nilai & Rapor', 'icon' => 'journal-check', 'route' => 'guru/nilai', 'match' => ['guru/nilai*', 'guru/rapor*'], 'modul' => 'nilai_rapor'],

        ['header' => 'Konten'],
        ['label' => 'Berita', 'icon' => 'newspaper', 'route' => 'guru/berita', 'match' => ['guru/berita*', 'guru/kategori*'], 'modul' => 'konten_web'],

        ['header' => 'E-Learning'],
        ['label' => 'Buka E-Learning', 'icon' => 'box-arrow-up-right', 'route' => 'elearning.buka', 'match' => ['elearning'], 'syarat' => 'elearning'],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'kepsek' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'kepsek/dashboard', 'match' => ['kepsek/dashboard']],

        ['header' => 'Pantauan'],
        ['label' => 'Rombel', 'icon' => 'door-open', 'route' => 'kepsek/kelas', 'match' => ['kepsek/kelas*', 'kepsek/rapor*']],
        ['label' => 'Data Alumni', 'icon' => 'award', 'route' => 'kepsek/alumni', 'match' => ['kepsek/alumni*']],
        ['label' => 'Kehadiran', 'icon' => 'clipboard2-check', 'route' => 'kepsek/kehadiran', 'match' => ['kepsek/kehadiran*'], 'modul' => 'absensi'],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'ortu' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'ortu/dashboard', 'match' => ['ortu/dashboard']],

        ['header' => 'Anak Saya'],
        ['label' => 'Kehadiran', 'icon' => 'clipboard2-check', 'route' => 'ortu/kehadiran', 'match' => ['ortu/kehadiran*'], 'modul' => 'portal_orang_tua'],
        ['label' => 'Nilai & Rapor', 'icon' => 'journal-check', 'route' => 'ortu/nilai', 'match' => ['ortu/nilai*'], 'modul' => 'portal_orang_tua'],
        ['label' => 'Tagihan & Pembayaran', 'icon' => 'wallet2', 'route' => 'ortu/tagihan', 'match' => ['ortu/tagihan*'], 'modul' => 'keuangan'],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'siswa' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'siswa/dashboard', 'match' => ['siswa/dashboard']],

        ['header' => 'Belajar'],
        ['label' => 'Kelas Saya', 'icon' => 'door-open', 'route' => 'siswa/kelas', 'match' => ['siswa/kelas*']],
        ['label' => 'Jadwal Pelajaran', 'icon' => 'calendar3', 'route' => 'siswa/jadwal', 'match' => ['siswa/jadwal*']],
        ['label' => 'Kehadiran Saya', 'icon' => 'clipboard2-check', 'route' => 'siswa/kehadiran', 'match' => ['siswa/kehadiran*'], 'modul' => 'absensi'],
        ['label' => 'Nilai Saya', 'icon' => 'journal-check', 'route' => 'siswa/nilai', 'match' => ['siswa/nilai*'], 'modul' => 'nilai_rapor'],
        ['label' => 'Buka E-Learning', 'icon' => 'box-arrow-up-right', 'route' => 'elearning.buka', 'match' => ['elearning'], 'syarat' => 'elearning'],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'bendahara' => [
        ['label' => 'Ringkasan', 'icon' => 'speedometer2', 'route' => 'bendahara/dashboard', 'match' => ['bendahara/dashboard']],

        ['header' => 'Keuangan'],
        ['label' => 'Tagihan', 'icon' => 'receipt', 'route' => 'bendahara/keuangan/tagihan', 'match' => ['bendahara/keuangan/tagihan*']],
        ['label' => 'Pembayaran', 'icon' => 'cash-coin', 'route' => 'bendahara/keuangan/pembayaran', 'match' => ['bendahara/keuangan/pembayaran*']],
        ['label' => 'Jenis Biaya', 'icon' => 'tags', 'route' => 'bendahara/keuangan/biaya', 'match' => ['bendahara/keuangan/biaya*']],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],

    'platform' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'platform/dashboard', 'match' => ['platform/dashboard']],

        ['header' => 'Penyewa'],
        ['label' => 'Sekolah', 'icon' => 'buildings', 'route' => 'platform/sekolah', 'match' => ['platform/sekolah*']],
        ['label' => 'Langganan', 'icon' => 'calendar-check', 'route' => 'platform/langganan', 'match' => ['platform/langganan*']],
        ['label' => 'Invoice', 'icon' => 'receipt', 'route' => 'platform/invoice', 'match' => ['platform/invoice*']],

        ['header' => 'Produk'],
        ['label' => 'Pengaturan Platform', 'icon' => 'gear', 'route' => 'platform/pengaturan', 'match' => ['platform/pengaturan*']],
        ['label' => 'Paket & Modul', 'icon' => 'box-seam', 'route' => 'platform/paket', 'match' => ['platform/paket*']],

        ['header' => 'Sistem'],
        ['label' => 'Pemantauan', 'icon' => 'activity', 'route' => 'platform/pemantauan', 'match' => ['platform/pemantauan*']],
        ['label' => 'Backup', 'icon' => 'archive', 'route' => 'platform/backup', 'match' => ['platform/backup*']],
        ['label' => 'Log Aktivitas', 'icon' => 'clock-history', 'route' => 'platform/log', 'match' => ['platform/log*']],

        ['header' => 'Bantuan'],
        ['label' => 'Panduan', 'icon' => 'life-preserver', 'route' => 'panduan', 'match' => ['panduan*']],
    ],
];
