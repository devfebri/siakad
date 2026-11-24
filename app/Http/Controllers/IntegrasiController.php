<?php

namespace App\Http\Controllers;

use App\SsoKlien;
use App\Support\Integrasi;
use App\Support\SekolahAktif;
use App\Support\UrlAman;
use App\WebhookLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/** Menu "Integrasi E-Learning" untuk admin sekolah: aktifkan sambungan, kunci akses, alamat webhook, dan riwayat pengiriman. */
class IntegrasiController extends Controller
{
    public function index(): View
    {
        $klien = SsoKlien::first();

        return view('integrasi.index', [
            'klien' => $klien,
            'kunciBaru' => session('kunci_baru'),
            'hasilUji' => session('hasil_uji'),
            'riwayat' => $klien ? WebhookLog::where('sso_klien_id', $klien->id)->latest('id')->limit(20)->get() : collect(),
            'apiUrl' => url('/api/v1'),
            'tokenUrl' => url('/oauth/token'),
        ]);
    }

    /** Aktifkan sambungan atau buat ulang kunci. Kunci ditampilkan sekali saja. */
    public function aktifkan(): RedirectResponse
    {
        $hasil = Integrasi::siapkan(SekolahAktif::instance()->get());

        return redirect()->route('admin/integrasi')
            ->with('kunci_baru', ['client_id' => $hasil['klien']->client_id, 'client_secret' => $hasil['client_secret'], 'webhook_secret' => $hasil['webhook_secret']])
            ->with('toast_success', 'Sambungan E-Learning aktif. Salin kunci di bawah sekarang, karena tidak akan ditampilkan lagi.');
    }

    public function nonaktifkan(): RedirectResponse
    {
        $klien = SsoKlien::firstOrFail();
        Integrasi::nonaktifkan($klien);

        return redirect()->route('admin/integrasi')->with('toast_success', 'Sambungan E-Learning dimatikan. E-Learning tidak bisa lagi mengambil data.');
    }

    /** Pengaturan login tunggal: alamat E-Learning (tujuan tombol), alamat kembali setelah masuk, dan saklar tombol. */
    public function simpanSso(Request $request): RedirectResponse
    {
        $klien = SsoKlien::firstOrFail();
        $request->validate([
            'elearning_url' => ['nullable', 'string', 'max:500'],
            'redirect_url' => ['nullable', 'string', 'max:500'],
        ], [], ['elearning_url' => 'alamat E-Learning', 'redirect_url' => 'alamat kembali']);

        $url = [];
        foreach (['elearning_url', 'redirect_url'] as $kolom) {
            $nilai = trim((string) $request->input($kolom)) ?: null;
            if ($nilai && ($galat = UrlAman::galat($nilai))) {
                return back()->withInput()->withErrors([$kolom => $galat]);
            }
            $url[$kolom] = $nilai;
        }

        $nyala = $request->boolean('sso_aktif');
        if ($nyala && (! $url['elearning_url'] || ! $url['redirect_url'])) {
            return back()->withInput()->withErrors(['sso_aktif' => 'Isi alamat E-Learning dan alamat kembali dulu sebelum menampilkan tombol.']);
        }

        $klien->update($url + ['sso_aktif' => $nyala]);

        // Alamat kembali harus sama persis dengan yang terdaftar pada klien OAuth, kalau tidak login ditolak.
        if ($oauth = Passport::client()->newQuery()->find($klien->client_id)) {
            $oauth->forceFill(['redirect_uris' => $url['redirect_url'] ? [$url['redirect_url']] : []])->save();
        }

        return redirect()->route('admin/integrasi')->with('toast_success', $nyala ? 'Tombol "Buka E-Learning" sekarang tampil untuk guru dan siswa.' : 'Pengaturan masuk E-Learning disimpan.');
    }

    /** Uji sambungan: kunci, alamat E-Learning, dan kabar percobaan (ping) bertanda tangan. */
    public function uji(): RedirectResponse
    {
        $klien = SsoKlien::firstOrFail();
        $hasil = [];

        $oauth = Passport::client()->newQuery()->find($klien->client_id);
        $hasil[] = ['Kunci akses', (bool) ($oauth && ! $oauth->revoked), $oauth && ! $oauth->revoked ? 'Kunci aktif.' : 'Kunci tidak aktif. Buat ulang kunci.'];

        if ($klien->elearning_url) {
            try {
                $res = Http::timeout(8)->withoutRedirecting()->get($klien->elearning_url);
                $hasil[] = ['Alamat E-Learning', $res->status() < 500, $res->status() < 500 ? 'E-Learning menjawab (kode '.$res->status().').' : 'E-Learning bermasalah (kode '.$res->status().').'];
            } catch (\Throwable) {
                $hasil[] = ['Alamat E-Learning', false, 'Tidak bisa tersambung ke alamat tersebut.'];
            }
        } else {
            $hasil[] = ['Alamat E-Learning', null, 'Belum diisi.'];
        }

        if ($klien->webhook_url && ! UrlAman::galat($klien->webhook_url, periksaDns: true)) {
            $isi = json_encode(['id' => (string) Str::uuid(), 'event' => 'ping', 'waktu' => now()->toIso8601String(), 'sekolah' => SekolahAktif::instance()->get()->subdomain, 'data' => new \stdClass], JSON_UNESCAPED_SLASHES);
            $ts = (string) time();
            try {
                $res = Http::timeout(8)->withoutRedirecting()->withHeaders([
                    'X-Siakad-Event' => 'ping', 'X-Siakad-Timestamp' => $ts, 'X-Siakad-Signature' => Integrasi::tandatangani($ts, $isi, (string) $klien->webhook_secret),
                ])->withBody($isi, 'application/json')->post($klien->webhook_url);
                $hasil[] = ['Penerima kabar', $res->successful(), $res->successful() ? 'Kabar percobaan diterima.' : 'Penerima membalas kode '.$res->status().'.'];
            } catch (\Throwable) {
                $hasil[] = ['Penerima kabar', false, 'Tidak bisa tersambung ke alamat penerima kabar.'];
            }
        } else {
            $hasil[] = ['Penerima kabar', null, $klien->webhook_url ? 'Alamat tidak aman.' : 'Belum diisi (boleh dilewati).'];
        }

        return redirect()->route('admin/integrasi')->with('hasil_uji', $hasil);
    }

    public function simpanWebhook(Request $request): RedirectResponse
    {
        $klien = SsoKlien::firstOrFail();
        $data = $request->validate(['webhook_url' => ['nullable', 'string', 'max:500']], [], ['webhook_url' => 'alamat penerima kabar']);

        $url = trim((string) ($data['webhook_url'] ?? '')) ?: null;
        if ($url && ($galat = UrlAman::galat($url, periksaDns: true))) {
            return back()->withInput()->withErrors(['webhook_url' => $galat]);
        }

        $klien->update(['webhook_url' => $url]);

        return redirect()->route('admin/integrasi')->with('toast_success', $url ? 'Alamat penerima kabar disimpan.' : 'Pengiriman kabar dimatikan.');
    }
}
