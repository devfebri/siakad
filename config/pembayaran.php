<?php

/*
| Pembayaran online. Nilai di sini adalah cadangan dari .env; yang diisi di Pengaturan Platform (kunci terenkripsi di database) lebih diutamakan.
| driver: nonaktif | midtrans | xendit | fake (fake hanya untuk lokal/uji: halaman simulasi tanpa uang sungguhan).
*/
return [
    'driver' => env('PAYMENT_DRIVER', 'nonaktif'),

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'sandbox' => (bool) env('MIDTRANS_SANDBOX', true),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),       // kunci "development" = sandbox, kunci "production" = sungguhan
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],

    // Batas waktu tautan pembayaran sewa (menit).
    'kedaluwarsa_menit' => 1440,
];
