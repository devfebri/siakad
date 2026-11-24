<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\LogAktivitas;
use App\Paket;
use App\Sekolah;
use App\Support\DataTableResponse;
use App\Support\Penyewaan;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** Panel platform: sekolah penyewa (CRUD, aktif/nonaktif, akun admin pertama, invoice, login-sebagai). */
class SekolahController extends Controller
{
    public function index(): View
    {
        return view('platform.sekolah.index', ['jenjang' => Sekolah::JENJANG]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Sekolah::query(),
            searchable: ['nama', 'npsn', 'subdomain', 'domain_kustom'],
            orderable: ['nama', 'subdomain', 'jenjang', 'created_at'],
            map: function (Sekolah $s) {
                $l = $s->langgananTerkini();

                return [
                    'id' => $s->id,
                    'nama' => $s->nama,
                    'npsn' => $s->npsn,
                    'jenjang' => $s->jenjang,
                    'subdomain' => $s->subdomain,
                    'domain_kustom' => $s->domain_kustom,
                    'domain' => ($s->domain_kustom ?: $s->subdomain.(config('sekolah.domain_utama') ? '.'.config('sekolah.domain_utama') : '')).'|'.$s->url('/'),
                    'status' => $s->status,
                    'tanpa_batas' => $s->tanpa_batas ? 1 : 0,
                    'status_label' => Sekolah::STATUS[$s->status].'|'.($s->isAktif() ? 'success' : 'muted'),
                    'langganan' => $l ? "{$l->paket?->nama} s.d. {$l->berakhir->translatedFormat('d M Y')}" : 'Belum ada',
                    'status_sewa' => $s->tanpa_batas ? 'Tanpa batas|primary' : match ($s->statusLangganan()) {
                        'aktif' => 'Aktif|success', 'tenggang' => 'Tenggang|warning', 'baca_saja' => 'Baca-saja|danger', default => 'Belum ada|muted',
                    },
                ];
            },
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalisasi($request);
        $data = $request->validate($this->aturan() + [
            'admin_name' => ['required', 'string', 'min:2', 'max:100'],
            'admin_username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'],
            'admin_email' => ['required', 'email', 'max:191'],
            'admin_password' => ['required', 'string', 'min:6', 'max:100'],
        ], $this->pesan(), $this->atribut());

        \App\Support\DomainKustom::lepaskanKlaimBelumTerbukti($data['domain_kustom'] ?? null);
        $sekolah = Penyewaan::buatSekolah(
            ['nama' => trim(strip_tags($data['nama'])), 'npsn' => $data['npsn'] ?? null, 'jenjang' => $data['jenjang'], 'subdomain' => $data['subdomain'], 'domain_kustom' => $data['domain_kustom'] ?? null, 'status' => $data['status'] ?? 'aktif', 'tanpa_batas' => $request->boolean('tanpa_batas')],
            ['name' => $data['admin_name'], 'username' => mb_strtolower($data['admin_username']), 'email' => $data['admin_email'], 'password' => $data['admin_password']],
        );

        return response()->json(['message' => "Sekolah {$sekolah->nama} dibuat. Admin: {$data['admin_username']}.", 'redirect' => route('platform/sekolah.show', $sekolah)], 201);
    }

    public function update(Request $request, Sekolah $sekolah): JsonResponse
    {
        $this->normalisasi($request);
        $data = $request->validate($this->aturan($sekolah), $this->pesan(), $this->atribut());

        $sekolah->update([
            'nama' => trim(strip_tags($data['nama'])), 'npsn' => $data['npsn'] ?? null, 'jenjang' => $data['jenjang'],
            'subdomain' => $data['subdomain'], 'status' => $data['status'] ?? $sekolah->status,
            'tanpa_batas' => $request->boolean('tanpa_batas'),
        ]);
        \App\Support\DomainKustom::klaim($sekolah, $data['domain_kustom'] ?? null); // domain diganti = verifikasi diulang
        LogAktivitas::catat('sekolah.ubah', "Data sekolah {$sekolah->nama} diubah.", $sekolah->id);

        return response()->json(['message' => 'Data sekolah diperbarui.']);
    }

    /** Cek DNS domain kustom sekolah ini. */
    public function domainPeriksa(Sekolah $sekolah): RedirectResponse
    {
        return redirect()->route('platform/sekolah.show', $sekolah)->with('hasil_cek_domain', \App\Support\DomainKustom::periksa($sekolah));
    }

    /** Tandai terverifikasi tanpa cek DNS (bukti kepemilikan lewat jalur lain). */
    public function domainManual(Sekolah $sekolah): RedirectResponse
    {
        \App\Support\DomainKustom::verifikasiManual($sekolah);

        return redirect()->route('platform/sekolah.show', $sekolah)->with('toast_success', 'Domain ditandai terverifikasi.');
    }

    public function domainHapus(Sekolah $sekolah): RedirectResponse
    {
        \App\Support\DomainKustom::klaim($sekolah, null);

        return redirect()->route('platform/sekolah.show', $sekolah)->with('toast_success', 'Domain kustom dihapus.');
    }

    public function show(Sekolah $sekolah): View
    {
        return view('platform.sekolah.show', [
            'instruksiDomain' => \App\Support\DomainKustom::instruksi($sekolah),
            'hasilCekDomain' => session('hasil_cek_domain'),
            'sekolah' => $sekolah,
            'langganan' => $sekolah->langganan()->with('paket:id,nama')->orderByDesc('berakhir')->get(),
            'invoice' => $sekolah->invoice()->with('paket:id,nama')->latest('id')->get(),
            'paket' => Paket::with('harga')->where('aktif', true)->orderBy('urutan')->get(),
            'admin' => $sekolah->adminPertama(),
            'statusSewa' => $sekolah->statusLangganan(),
            'terkini' => $sekolah->langgananTerkini(),
            'jumlah' => [
                'siswa' => \App\Siswa::withoutGlobalScopes()->where('sekolah_id', $sekolah->id)->count(),
                'guru' => \App\Guru::withoutGlobalScopes()->where('sekolah_id', $sekolah->id)->count(),
                'pengguna' => User::withoutGlobalScopes()->where('sekolah_id', $sekolah->id)->count(),
            ],
        ]);
    }

    /** Aktifkan atau nonaktifkan sekolah (situs publik dan login sekolah ditutup saat nonaktif). */
    public function status(Request $request, Sekolah $sekolah): JsonResponse
    {
        $baru = $request->validate(['status' => ['required', Rule::in(array_keys(Sekolah::STATUS))]])['status'];
        $sekolah->update(['status' => $baru]);
        LogAktivitas::catat('sekolah.status', "Sekolah {$sekolah->nama} diubah menjadi {$baru}.", $sekolah->id);

        return response()->json(['message' => "Sekolah {$sekolah->nama} sekarang ".strtolower(Sekolah::STATUS[$baru]).'.', 'status' => $baru]);
    }

    /** Buat invoice sewa dari halaman detail sekolah. */
    public function buatInvoice(Request $request, Sekolah $sekolah): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'paket_id' => ['required', 'integer', Rule::exists('paket', 'id')],
            'durasi_bulan' => ['required', 'integer', Rule::in(Paket::DURASI)],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], [], ['paket_id' => 'paket', 'durasi_bulan' => 'durasi']);

        $invoice = Penyewaan::buatInvoice($sekolah, Paket::with('harga')->findOrFail($data['paket_id']), (int) $data['durasi_bulan'], $data['catatan'] ?? null);

        return response()->json(['message' => "Invoice {$invoice->nomor} dibuat.", 'redirect' => route('platform/sekolah.show', $sekolah)], 201);
    }

    /**
     * Login-sebagai admin sekolah: membuat tautan bertanda tangan (5 menit) ke alamat situs sekolah, tempat sesi admin dibuat.
     * Dicatat di log aktivitas.
     */
    public function masuk(Sekolah $sekolah): RedirectResponse
    {
        abort_unless($sekolah->isAktif(), 409, 'Sekolah nonaktif tidak dapat dimasuki.');
        $admin = $sekolah->adminPertama();
        abort_unless($admin, 409, 'Sekolah ini belum punya akun admin.');

        LogAktivitas::catat('impersonasi.mulai', "Login sebagai {$admin->username} di {$sekolah->nama}.", $sekolah->id, ['admin_id' => $admin->id]);

        $tujuan = $sekolah->url('/');
        $akar = strtok($tujuan, '?');
        $akar = preg_replace('#^(https?://[^/]+).*$#', '$1', $akar);

        URL::forceRootUrl($akar);
        try {
            $url = URL::temporarySignedRoute('impersonasi.masuk', now()->addMinutes(5), array_filter([
                'user' => $admin->id, 'oleh' => auth()->id(), 'sekolah' => (config('sekolah.domain_utama') || $sekolah->domain_kustom) ? null : $sekolah->subdomain,
            ]));
        } finally {
            URL::forceRootUrl(null);
        }

        return redirect()->away($url);
    }

    /** Subdomain, domain, dan username admin disimpan huruf kecil; "www." di depan domain kustom dibuang (sama seperti pencocokan alamat). */
    private function normalisasi(Request $request): void
    {
        $domain = mb_strtolower(trim((string) $request->input('domain_kustom')));
        $request->merge([
            'subdomain' => mb_strtolower(trim((string) $request->input('subdomain'))),
            'domain_kustom' => $domain === '' ? null : preg_replace('/^www\./', '', $domain),
            'admin_username' => mb_strtolower(trim((string) $request->input('admin_username'))),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function aturan(?Sekolah $sekolah = null): array
    {
        return [
            'nama' => ['required', 'string', 'min:3', 'max:150'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'jenjang' => ['required', Rule::in(Sekolah::JENJANG)],
            'subdomain' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn(\App\Sekolah::SUBDOMAIN_DICADANGKAN), Rule::unique('sekolah', 'subdomain')->ignore($sekolah?->id)],
            'domain_kustom' => ['nullable', 'string', 'max:150', 'regex:/^(?!-)[a-z0-9.-]+\.[a-z]{2,}$/', fn ($atribut, $nilai, $gagal) => ($g = \App\Support\DomainKustom::galat($nilai, $sekolah)) ? $gagal($g) : null],
            'status' => ['nullable', Rule::in(array_keys(Sekolah::STATUS))],
        ];
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return [
            'subdomain.regex' => 'Subdomain hanya boleh huruf kecil, angka, dan strip (tidak di awal/akhir).',
            'subdomain.not_in' => 'Subdomain ini dicadangkan.',
            'domain_kustom.regex' => 'Domain tidak valid, contoh: www.sekolah.sch.id.',
            'admin_username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.',
        ];
    }

    /** @return array<string, string> */
    private function atribut(): array
    {
        return ['nama' => 'nama sekolah', 'domain_kustom' => 'domain kustom', 'admin_name' => 'nama admin', 'admin_username' => 'username admin', 'admin_email' => 'email admin', 'admin_password' => 'password admin'];
    }
}
