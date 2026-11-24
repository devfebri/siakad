<?php

use App\Http\Controllers\Api\V1\DataIndukController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\WebhookPembayaranController;
use App\Http\Controllers\Api\WebhookSppController;
use Illuminate\Support\Facades\Route;

/*
| API data induk v1 untuk aplikasi E-Learning (paket Premium).
| Autentikasi: token OAuth2 client_credentials dari POST /oauth/token (Passport). Hanya baca.
| Dokumentasi: docs/api.md.
*/
// Callback gerbang pembayaran (Midtrans/Xendit/simulasi). Tanda tangan diperiksa oleh driver; tanpa sesi.
Route::post('/webhook/pembayaran/{driver}', WebhookPembayaranController::class)->where('driver', 'midtrans|xendit|fake')->middleware('throttle:300,1');

// Callback gerbang pembayaran SPP milik tiap sekolah (kunci sekolah; sekolah dikenali dari token di alamat).
Route::post('/webhook/spp/{token}/{driver}', WebhookSppController::class)->where('driver', 'midtrans|xendit|fake')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:300,1');

// Identitas pengguna pemilik token SSO (guru/siswa yang masuk lewat tombol "Buka E-Learning").
Route::prefix('v1')->middleware(['auth:api', 'penggunaApi'])->group(function () {
    Route::get('/me', MeController::class);
});

Route::prefix('v1')->middleware(['client', 'klienApi'])->group(function () {
    Route::get('/sekolah', [DataIndukController::class, 'sekolah']);
    Route::get('/tahun-ajaran/aktif', [DataIndukController::class, 'aktif']);
    Route::get('/{sumber}', [DataIndukController::class, 'index'])->where('sumber', 'tahun-ajaran|rombel|rombel-siswa|mapel|guru|siswa|jadwal');
});
