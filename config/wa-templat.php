<?php

/*
| Kejadian yang dikabarkan lewat WhatsApp, templat pesan bawaan, dan placeholder yang boleh dipakai.
| Sekolah dapat mengubah teks tiap templat di menu Notifikasi WhatsApp; placeholder ditulis {seperti_ini}.
| Kejadian "rapor_terbit" belum ada karena alur rapor belum dibangun.
*/
return [
    'alpa' => [
        'label' => 'Siswa alpa',
        'penerima' => 'Orang tua',
        'placeholder' => ['nama_ortu', 'nama_siswa', 'kelas', 'tanggal', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nPutra/putri Anda *{nama_siswa}* (kelas {kelas}) tercatat *tidak hadir tanpa keterangan (alpa)* pada {tanggal}.\nMohon konfirmasi ke wali kelas. Terima kasih.\n— {nama_sekolah}",
    ],
    'tagihan_terbit' => [
        'label' => 'Tagihan baru terbit',
        'penerima' => 'Orang tua',
        'placeholder' => ['nama_ortu', 'nama_siswa', 'nama_tagihan', 'nominal', 'jatuh_tempo', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nTagihan baru untuk *{nama_siswa}*: {nama_tagihan}.\nJumlah: *{nominal}*, jatuh tempo {jatuh_tempo}.\nBayar lewat aplikasi sekolah atau langsung ke bendahara. Terima kasih.\n— {nama_sekolah}",
    ],
    'jatuh_tempo' => [
        'label' => 'Pengingat jatuh tempo (3 hari lagi)',
        'penerima' => 'Orang tua',
        'placeholder' => ['nama_ortu', 'nama_siswa', 'nama_tagihan', 'nominal', 'jatuh_tempo', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nPengingat: tagihan *{nama_tagihan}* untuk {nama_siswa} sebesar *{nominal}* jatuh tempo pada {jatuh_tempo}.\nTerima kasih.\n— {nama_sekolah}",
    ],
    'tunggakan' => [
        'label' => 'Pengingat tunggakan',
        'penerima' => 'Orang tua',
        'placeholder' => ['nama_ortu', 'nama_siswa', 'nominal', 'jumlah_tagihan', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nMasih ada {jumlah_tagihan} tagihan {nama_siswa} yang lewat jatuh tempo, total *{nominal}*.\nMohon segera diselesaikan. Jika sudah membayar, abaikan pesan ini.\n— {nama_sekolah}",
    ],
    'pembayaran_diterima' => [
        'label' => 'Pembayaran diterima',
        'penerima' => 'Orang tua',
        'placeholder' => ['nama_ortu', 'nama_siswa', 'nominal', 'nomor_kuitansi', 'tanggal', 'metode', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nPembayaran *{nominal}* untuk {nama_siswa} sudah kami terima pada {tanggal} ({metode}).\nNomor kuitansi: {nomor_kuitansi}. Terima kasih.\n— {nama_sekolah}",
    ],
    'ppdb' => [
        'label' => 'Hasil seleksi PPDB',
        'penerima' => 'Pendaftar PPDB',
        'placeholder' => ['nama_pendaftar', 'nama_ortu', 'status', 'tahun_ajaran', 'nama_sekolah'],
        'templat' => "Yth. {nama_ortu},\nPendaftaran *{nama_pendaftar}* di {nama_sekolah} (PPDB {tahun_ajaran}) dinyatakan *{status}*.\nInformasi selanjutnya dapat dilihat di website sekolah. Terima kasih.",
    ],
    'langganan' => [
        'label' => 'Pengingat langganan aplikasi',
        'penerima' => 'Admin sekolah',
        'placeholder' => ['nama_sekolah', 'paket', 'tanggal_berakhir', 'sisa_hari'],
        'templat' => "Pengingat: langganan paket *{paket}* {nama_sekolah} berakhir pada {tanggal_berakhir} ({sisa_hari} hari lagi).\nPerpanjang di menu Langganan Saya agar layanan tidak terhenti.",
    ],
];
