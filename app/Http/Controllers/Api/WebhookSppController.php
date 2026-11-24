<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pembayaran\SppOnline;
use App\PengaturanPembayaranSekolah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Penerima callback gerbang pembayaran SPP milik sekolah. Sekolah dikenali dari token acak di alamat,
 * lalu tanda tangan diverifikasi dengan kunci sekolah itu sendiri. Tanpa sesi/CSRF.
 */
class WebhookSppController extends Controller
{
    public function __invoke(Request $request, string $token, string $driver): JsonResponse
    {
        $pg = PengaturanPembayaranSekolah::withoutGlobalScopes()->where('webhook_token', $token)->first();
        if (! $pg) {
            return response()->json(['message' => 'Tidak ditemukan.'], 404);
        }

        [$status, $pesan] = SppOnline::terima($pg, $driver, $request);

        return response()->json(['message' => $pesan], $status);
    }
}
