<?php

namespace App\Http\Controllers;

use App\LogAktivitas;
use App\PengaturanWa;
use App\Support\SekolahAktif;
use App\Support\UrlAman;
use App\Wa\NomorWa;
use App\Wa\Wa;
use App\WaLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengaturan notifikasi WhatsApp sekolah (admin): gerbang, kunci, kejadian, templat pesan, uji kirim, dan riwayat. Kunci tidak pernah tampil utuh. */
class PengaturanWaController extends Controller
{
    public function index(): View
    {
        $pg = PengaturanWa::milikSekolahAktif();

        return view('wa.index', [
            'pg' => $pg,
            'driver' => array_filter(PengaturanWa::DRIVER, fn ($k) => $k !== 'fake' || ! app()->isProduction(), ARRAY_FILTER_USE_KEY),
            'kejadian' => config('wa-templat'),
            'kunci' => ['token' => $pg->tersembunyi('token'), 'kunci2' => $pg->tersembunyi('kunci2')],
            'riwayat' => WaLog::latest('id')->limit(30)->get(),
            'ringkas' => WaLog::selectRaw('status, count(*) as n')->where('created_at', '>=', now()->subDays(30))->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $pg = PengaturanWa::milikSekolahAktif();
        $data = $request->validate([
            'driver' => ['required', Rule::in(array_keys(PengaturanWa::DRIVER))],
            'token' => ['nullable', 'string', 'max:300'],
            'kunci2' => ['nullable', 'string', 'max:300'],
            'server_url' => ['nullable', 'string', 'max:200'],
            'nomor_admin' => ['nullable', 'string', 'max:25'],
            'batas_per_menit' => ['required', 'integer', 'between:1,120'],
            'templat' => ['nullable', 'array'],
            'templat.*' => ['nullable', 'string', 'max:1000'],
        ], [], ['batas_per_menit' => 'batas kirim per menit', 'nomor_admin' => 'nomor admin']);

        // templat: hanya placeholder yang dikenal untuk kejadian itu
        $templat = [];
        foreach (config('wa-templat') as $kode => $def) {
            $teks = trim((string) ($data['templat'][$kode] ?? ''));
            if ($teks === '' || $teks === $def['templat']) {
                continue;
            }
            preg_match_all('/\{([a-z_]+)\}/', $teks, $m);
            $asing = array_diff(array_unique($m[1]), $def['placeholder'], ['nama_sekolah']);
            if ($asing) {
                return back()->withInput()->withErrors(["templat.$kode" => 'Pesan "'.$def['label'].'" memakai isian yang tidak dikenal: {'.implode('}, {', $asing).'}. Yang boleh: {'.implode('}, {', $def['placeholder']).'}.']);
            }
            $templat[$kode] = $teks;
        }

        $nomorAdmin = null;
        if (filled($data['nomor_admin'] ?? null)) {
            $nomorAdmin = NomorWa::normalkan($data['nomor_admin']);
            if (! $nomorAdmin) {
                return back()->withInput()->withErrors(['nomor_admin' => 'Nomor WhatsApp tidak sah. Contoh: 0812 3456 7890.']);
            }
        }
        $server = trim((string) ($data['server_url'] ?? '')) ?: null;
        if ($server && ($galat = UrlAman::galat($server))) {
            return back()->withInput()->withErrors(['server_url' => $galat]);
        }

        $pg->fill([
            'driver' => $data['driver'], 'server_url' => $server, 'nomor_admin' => $nomorAdmin, 'batas_per_menit' => (int) $data['batas_per_menit'],
            'aktif' => $request->boolean('aktif'),
            'event_aktif' => collect(config('wa-templat'))->mapWithKeys(fn ($d, $k) => [$k => (bool) ($request->input("event.$k") ?? false)])->all(),
            'templat' => $templat ?: null,
        ]);
        foreach (['token', 'kunci2'] as $kolom) {
            if (filled($data[$kolom] ?? null)) {
                $pg->{$kolom} = trim($data[$kolom]);
            }
        }

        if ($pg->aktif && ! $pg->pengirim()) {
            return back()->withInput()->withErrors(['aktif' => 'Kunci '.PengaturanWa::DRIVER[$pg->driver].' belum lengkap, jadi WhatsApp belum bisa dinyalakan. Lengkapi isian kunci di bawah.']);
        }

        $pg->save();
        LogAktivitas::catat('wa.pengaturan', 'Pengaturan WhatsApp diperbarui ('.($pg->aktif ? 'aktif, '.$pg->driver : 'nonaktif').').');

        return redirect()->route('admin/wa')->with('toast_success', $pg->aktif ? 'Pengaturan disimpan. WhatsApp menyala.' : 'Pengaturan disimpan. WhatsApp masih mati.');
    }

    /** Kirim pesan percobaan ke nomor sendiri untuk memastikan kunci benar. Tidak masuk riwayat kejadian. */
    public function uji(Request $request): RedirectResponse
    {
        $data = $request->validate(['nomor_uji' => ['required', 'string', 'max:25']], ['nomor_uji.required' => 'Isi nomor WhatsApp tujuan.'], ['nomor_uji' => 'nomor tujuan']);
        $nomor = NomorWa::normalkan($data['nomor_uji']);
        if (! $nomor) {
            return back()->withErrors(['nomor_uji' => 'Nomor WhatsApp tidak sah. Contoh: 0812 3456 7890.']);
        }

        $pengirim = PengaturanWa::milikSekolahAktif()->pengirim();
        if (! $pengirim) {
            return back()->withErrors(['nomor_uji' => 'Simpan dulu kunci gerbang WhatsApp, baru coba kirim.']);
        }

        $hasil = $pengirim->kirim($nomor, 'Pesan percobaan dari '.SekolahAktif::instance()->get()->nama.'. Jika Anda menerima pesan ini, WhatsApp sekolah sudah tersambung.');

        return $hasil->ok
            ? redirect()->route('admin/wa')->with('toast_success', 'Pesan percobaan terkirim ke '.$nomor.'. Cek WhatsApp Anda.')
            : redirect()->route('admin/wa')->with('toast_error', 'Gagal mengirim: '.$hasil->galat);
    }
}
