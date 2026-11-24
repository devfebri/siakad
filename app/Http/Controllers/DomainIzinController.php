<?php

namespace App\Http\Controllers;

use App\Support\DomainKustom;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoint "ask" untuk Caddy on-demand TLS: sertifikat SSL hanya diminta untuk domain yang terdaftar dan terverifikasi
 * (subdomain domain utama atau domain kustom sekolah aktif). Hanya mengembalikan 200 atau 404, tanpa isi.
 * Contoh Caddyfile ada di README.
 */
class DomainIzinController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return response('', DomainKustom::diizinkan((string) $request->query('domain')) ? 200 : 404);
    }
}
