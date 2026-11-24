<?php

/*
| Pengaturan multi-sekolah (SaaS). Sekolah dikenali dari alamat yang diakses:
|   1. ?sekolah=<subdomain>  (hanya bila diizinkan, untuk lokal/uji; disimpan di sesi)
|   2. domain kustom sekolah (kolom sekolah.domain_kustom)
|   3. subdomain dari domain_utama, mis. smpn1.siakad.id -> smpn1
|   4. sekolah bawaan (SEKOLAH_DEFAULT); bila kosong dan hanya ada satu sekolah, sekolah itu dipakai
*/
return [
    // Domain induk untuk subdomain sekolah, mis. "siakad.id". Kosong = subdomain tidak dipakai.
    'domain_utama' => env('SEKOLAH_DOMAIN_UTAMA'),

    // Subdomain sekolah bawaan untuk lokal/instalasi satu sekolah.
    'default_subdomain' => env('SEKOLAH_DEFAULT'),

    // Tujuan DNS yang diberikan ke sekolah untuk domain kustom: CNAME (domain biasa) atau alamat IP server (A record, untuk domain tanpa "www").
    // Kosongkan cname_target bila memakai domain_utama: otomatis "<subdomain>.<domain_utama>".
    'cname_target' => env('SEKOLAH_CNAME_TARGET'),
    'server_ip' => env('SEKOLAH_SERVER_IP'),

    // Alamat panel platform (Super Admin), dipakai tombol "Kembali ke Platform" setelah login-sebagai. Kosong = /platform/login di alamat ini.
    'platform_url' => env('SEKOLAH_PLATFORM_URL'),

    // Teks petunjuk pembayaran yang tampil di menu Langganan Saya (rekening, kontak pengelola, dll).
    'petunjuk_pembayaran' => env('SEKOLAH_PETUNJUK_PEMBAYARAN', 'Setelah membuat invoice, lakukan pembayaran sesuai nominal lalu hubungi pengelola platform untuk konfirmasi.'),

    // Izinkan ?sekolah=<subdomain>. Aktif otomatis di lingkungan local dan testing; JANGAN aktifkan di produksi.
    'izinkan_query' => (bool) env('SEKOLAH_QUERY', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
];
