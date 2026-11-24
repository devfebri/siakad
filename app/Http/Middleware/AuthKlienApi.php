<?php

namespace App\Http\Middleware;

use App\Sekolah;
use App\SsoKlien;
use App\Support\Integrasi;
use App\Support\SekolahAktif;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengaman API data induk (dipasang setelah middleware "client" dari Passport yang memeriksa token client_credentials):
 * klien harus aktif, sekolahnya aktif, dan paketnya mencakup integrasi E-Learning. Konteks sekolah diisi dari klien,
 * sehingga semua query otomatis terbatas pada sekolah pemilik klien. Dibatasi 120 request per menit per klien.
 */
class AuthKlienApi
{
    public const BATAS_PER_MENIT = 120;

    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards(); // guard dibuat baru agar klien tidak terbawa dari request sebelumnya (proses yang hidup lama)
        $client = Auth::guard('api')->client();
        $klien = $client ? SsoKlien::withoutGlobalScopes()->where('client_id', $client->getKey())->first() : null;
        if (! $klien || ! $klien->aktif) {
            return $this->tolak('Klien tidak dikenal atau tidak aktif.', 403);
        }

        $sekolah = Sekolah::find($klien->sekolah_id);
        if (! $sekolah || ! Integrasi::tersedia($sekolah)) {
            return $this->tolak('Sekolah tidak aktif atau paketnya tidak mencakup integrasi E-Learning.', 403);
        }

        $kunci = 'api-klien:'.$klien->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_MENIT)) {
            return $this->tolak('Terlalu banyak request. Coba lagi sebentar lagi.', 429)->withHeaders(['Retry-After' => RateLimiter::availableIn($kunci)]);
        }
        RateLimiter::hit($kunci, 60);

        SekolahAktif::instance()->set($sekolah);
        $request->attributes->set('sso_klien', $klien);

        $this->catat($klien);

        return $next($request);
    }

    /** Hitung request harian per sekolah (untuk monitoring) dan tandai waktu pakai terakhir (paling sering sekali per menit). */
    private function catat(SsoKlien $klien): void
    {
        $tanggal = today()->toDateString();
        $sudah = DB::table('api_request_log')->where('sekolah_id', $klien->sekolah_id)->where('tanggal', $tanggal)->increment('jumlah', 1, ['updated_at' => now()]);
        if (! $sudah) {
            DB::table('api_request_log')->insertOrIgnore(['sekolah_id' => $klien->sekolah_id, 'tanggal' => $tanggal, 'jumlah' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        if (! $klien->terakhir_dipakai || $klien->terakhir_dipakai->lt(now()->subMinute())) {
            SsoKlien::withoutGlobalScopes()->whereKey($klien->id)->update(['terakhir_dipakai' => now()]);
        }
    }

    private function tolak(string $pesan, int $status): Response
    {
        return response()->json(['message' => $pesan], $status);
    }
}
