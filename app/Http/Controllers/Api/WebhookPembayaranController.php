<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pembayaran\Sewa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Penerima callback gerbang pembayaran (Midtrans, Xendit, simulasi). Tanda tangan diverifikasi oleh driver; tanpa sesi/CSRF. */
class WebhookPembayaranController extends Controller
{
    public function __invoke(Request $request, string $driver): JsonResponse
    {
        [$status, $pesan] = Sewa::terima($driver, $request);

        return response()->json(['message' => $pesan], $status);
    }
}
