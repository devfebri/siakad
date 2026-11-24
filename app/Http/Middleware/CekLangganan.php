<?php

namespace App\Http\Middleware;

use App\Support\AksesSekolah;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan langganan sekolah:
 *  - modul yang tidak termasuk paket: alamatnya ditolak (halaman publik 404, panel dialihkan, JSON 403);
 *  - mode baca-saja (lewat masa tenggang / belum berlangganan): POST/PUT/PATCH/DELETE ditolak dengan pesan jelas,
 *    kecuali alamat pada config modul-akses.boleh_saat_baca_saja. Halaman publik tetap tampil.
 * Sekolah "tanpa batas" dan panel platform tidak dibatasi.
 */
class CekLangganan
{
    public function handle(Request $request, Closure $next): Response
    {
        $akses = AksesSekolah::ambil();
        if ($akses === null) {
            return $next($request);
        }

        foreach (config('modul-akses.modul') as $kode => $pola) {
            // "a|b" = cukup salah satu modul tersedia
            $alternatif = explode('|', $kode);
            if ($pola && $request->is(...$pola) && ! collect($alternatif)->contains(fn ($k) => AksesSekolah::modul($k))) {
                return $this->tolakModul($request, $alternatif[0]);
            }
        }

        if ($akses['baca_saja'] && ! $request->isMethodSafe() && ! $request->is(...config('modul-akses.boleh_saat_baca_saja'))) {
            return $this->tolakBacaSaja($request, $akses);
        }

        return $next($request);
    }

    private function tolakModul(Request $request, string $kode): Response
    {
        $pesan = 'Fitur '.AksesSekolah::namaModul($kode).' tidak termasuk dalam paket langganan sekolah Anda.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan], 403);
        }

        // Halaman publik: seolah tidak ada. Panel: kembali ke dashboard dengan penjelasan.
        $user = $request->user();
        if (! $user || ! $request->is('admin/*', 'guru/*', 'siswa/*', 'kepsek/*', 'ortu', 'ortu/*')) {
            abort(404);
        }
        // panel orang tua seluruhnya satu modul: dialihkan ke dashboard-nya sendiri akan berputar
        if ($request->is('ortu', 'ortu/*')) {
            abort(403, $pesan.' Hubungi admin sekolah.');
        }

        return redirect($user->dashboardUrl())->with('toast_error', $pesan.' Hubungi admin sekolah untuk meningkatkan paket.');
    }

    /** @param array<string, mixed> $akses */
    private function tolakBacaSaja(Request $request, array $akses): Response
    {
        $pesan = $akses['status'] === 'belum_ada'
            ? 'Sekolah belum memiliki langganan aktif sehingga situs dalam mode baca-saja. Perubahan data ditolak sampai langganan diaktifkan.'
            : 'Langganan sekolah berakhir pada '.$akses['berakhir']->translatedFormat('d F Y').' dan masa tenggang telah habis. Situs dalam mode baca-saja: perubahan data ditolak sampai langganan diperpanjang.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan, 'baca_saja' => true], 403);
        }

        return back()->with('toast_error', $pesan);
    }
}
