<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\LogAktivitas;
use App\Sekolah;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel platform: log aktivitas seluruh sekolah dan platform dengan filter sekolah, pengguna, aksi, dan rentang tanggal.
 * Hanya baca; bisa diunduh sebagai CSV (hingga 20.000 baris sesuai filter).
 */
class LogController extends Controller
{
    private const MAKS_EKSPOR = 20000;

    /** Jenis tindakan (awalan kode aksi) dan namanya di saringan. Jenis lain yang ada di database ikut ditampilkan dengan kodenya. */
    private const JENIS = [
        'auth' => 'Masuk/keluar', 'impersonasi' => 'Login-sebagai', 'sekolah' => 'Sekolah', 'langganan' => 'Langganan', 'invoice' => 'Invoice', 'paket' => 'Paket',
        'keuangan' => 'Keuangan sekolah', 'spp_online' => 'SPP online', 'pembayaran' => 'Pembayaran sewa', 'backup' => 'Backup', 'domain' => 'Domain', 'wa' => 'WhatsApp', 'platform' => 'Pengaturan platform',
    ];

    public function index(): View
    {
        return view('platform.log.index', [
            'sekolah' => Sekolah::orderBy('nama')->get(['id', 'nama']),
            'jenisAksi' => collect(self::JENIS)->union(LogAktivitas::query()->distinct()->pluck('aksi')->map(fn ($a) => \Illuminate\Support\Str::before($a, '.'))->unique()->filter()->mapWithKeys(fn ($k) => [$k => $k])->all())->sort(),
        ]);
    }

    /** Lolos-kan karakter khusus LIKE dengan "!" (berlaku sama di MySQL dan SQLite, tidak seperti backslash). */
    private static function like(string $teks): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $teks);
    }

    private function dasar(Request $request): Builder
    {
        $query = LogAktivitas::query()->with(['user' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'username'), 'sekolah:id,nama']);

        if ($request->filled('sekolah_id')) {
            $request->input('sekolah_id') === 'platform' ? $query->whereNull('sekolah_id') : $query->where('sekolah_id', (int) $request->input('sekolah_id'));
        }
        if ($pengguna = trim((string) $request->input('pengguna'))) {
            $like = '%'.self::like($pengguna).'%';
            $query->whereIn('user_id', \App\User::withoutGlobalScopes()->where(fn ($w) => $w->whereRaw("name like ? escape '!'", [$like])->orWhereRaw("username like ? escape '!'", [$like]))->select('id'));
        }
        if ($aksi = trim((string) $request->input('aksi'))) {
            $query->whereRaw("aksi like ? escape '!'", [self::like($aksi).'%']);
        }
        foreach (['dari' => '>=', 'sampai' => '<='] as $kolom => $op) {
            if ($tgl = $request->input($kolom)) {
                try {
                    $t = \Illuminate\Support\Carbon::parse($tgl);
                    $query->where('created_at', $op, $kolom === 'dari' ? $t->startOfDay() : $t->endOfDay());
                } catch (\Throwable) {
                    // tanggal tidak terbaca: filter diabaikan
                }
            }
        }

        return $query;
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            $this->dasar($request),
            searchable: ['aksi', 'deskripsi'],
            orderable: ['created_at', 'aksi'],
            map: fn (LogAktivitas $l) => [
                'id' => $l->id,
                'waktu' => $l->created_at?->translatedFormat('d M Y H:i') ?? '-',
                'pengguna' => $l->user ? "{$l->user->name} ({$l->user->username})" : '-',
                'sekolah' => $l->sekolah?->nama ?? 'Platform',
                'aksi' => $l->aksi,
                'deskripsi' => $l->deskripsi,
                'ip' => $l->ip ?? '-',
            ],
            defaultOrder: 'created_at',
        );
    }

    /** Unduh log (sesuai filter) sebagai CSV. */
    public function ekspor(Request $request): StreamedResponse
    {
        $query = $this->dasar($request)->latest('id')->limit(self::MAKS_EKSPOR);

        return response()->streamDownload(function () use ($query) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($h, ['Waktu', 'Pengguna', 'Sekolah', 'Aksi', 'Deskripsi', 'IP']);
            foreach ($query->cursor() as $l) {
                fputcsv($h, [$l->created_at?->format('Y-m-d H:i:s'), $l->user ? "{$l->user->name} ({$l->user->username})" : '', $l->sekolah?->nama ?? 'Platform', $l->aksi, $l->deskripsi, $l->ip]);
            }
            fclose($h);
        }, 'log-aktivitas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
