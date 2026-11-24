<?php

namespace App\Http\Controllers;

use App\ChatSession;
use App\Support\ChatHours;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Statistik konsultasi online: volume per hari, waktu tunggu, durasi layanan, topik, rating, dan kinerja per petugas. */
class ChatStatistikController extends Controller
{
    /** Batas baris yang dihitung agar halaman tetap ringan. */
    private const MAKS_SESI = 50000;

    public function index(Request $request): View
    {
        $f = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $zona = ChatHours::zona();
        $sampai = Carbon::parse($f['sampai'] ?? now($zona)->toDateString(), $zona)->endOfDay();
        $dari = Carbon::parse($f['dari'] ?? $sampai->copy()->subDays(29)->toDateString(), $zona)->startOfDay();
        abort_if($dari->diffInDays($sampai) > 366, 422, 'Rentang tanggal maksimal 1 tahun.');

        // kolom created_at disimpan dalam zona waktu aplikasi
        $tzApp = config('app.timezone');
        $sesi = ChatSession::whereBetween('created_at', [$dari->copy()->setTimezone($tzApp), $sampai->copy()->setTimezone($tzApp)])
            ->limit(self::MAKS_SESI)->get(['id', 'topik', 'status', 'agent_id', 'balas_email', 'rating', 'created_at', 'taken_at', 'closed_at']);

        // waktu tunggu: chat yang diambil petugas dan tidak dialihkan ke email (kalau tidak, tunggunya bisa berhari-hari)
        $tunggu = $sesi->filter(fn ($s) => $s->taken_at && ! $s->balas_email)->map(fn ($s) => $s->created_at->diffInSeconds($s->taken_at) / 60);
        $durasi = $sesi->filter(fn ($s) => $s->taken_at && $s->closed_at && $s->status === 'selesai')->map(fn ($s) => $s->taken_at->diffInSeconds($s->closed_at) / 60);
        $rating = $sesi->whereNotNull('rating');

        // chat per hari (dalam zona waktu layanan), termasuk hari tanpa chat
        $perHari = [];
        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            $perHari[$d->toDateString()] = 0;
        }
        foreach ($sesi as $s) {
            $hari = $s->created_at->copy()->setTimezone($zona)->toDateString();
            if (isset($perHari[$hari])) {
                $perHari[$hari]++;
            }
        }

        $topik = $sesi->countBy('topik')->sortDesc();
        $distribusiRating = collect(range(1, 5))->mapWithKeys(fn ($n) => [$n => $rating->where('rating', $n)->count()]);

        $nama = User::whereIn('id', $sesi->pluck('agent_id')->filter()->unique())->pluck('name', 'id');
        $petugas = $sesi->filter(fn ($s) => $s->agent_id)->groupBy('agent_id')->map(function ($grup, $id) use ($nama) {
            $lama = $grup->filter(fn ($s) => $s->taken_at && $s->closed_at && $s->status === 'selesai')->map(fn ($s) => $s->taken_at->diffInSeconds($s->closed_at) / 60);
            $nilai = $grup->whereNotNull('rating');

            return [
                'nama' => $nama[$id] ?? 'Petugas #'.$id,
                'jumlah' => $grup->count(),
                'selesai' => $grup->where('status', 'selesai')->count(),
                'durasi' => $lama->isNotEmpty() ? round($lama->avg(), 1) : null,
                'rating' => $nilai->isNotEmpty() ? round($nilai->avg('rating'), 2) : null,
                'jumlah_rating' => $nilai->count(),
            ];
        })->sortByDesc('jumlah')->values();

        return view('chat.statistik', [
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
            'ringkas' => [
                'total' => $sesi->count(),
                'selesai' => $sesi->where('status', 'selesai')->count(),
                'diteruskan' => $sesi->where('balas_email', true)->count(),
                'tunggu' => $tunggu->isNotEmpty() ? round($tunggu->avg(), 1) : null,
                'durasi' => $durasi->isNotEmpty() ? round($durasi->avg(), 1) : null,
                'rating' => $rating->isNotEmpty() ? round($rating->avg('rating'), 2) : null,
                'jumlah_rating' => $rating->count(),
            ],
            'perHari' => $perHari,
            'topik' => $topik,
            'distribusiRating' => $distribusiRating,
            'petugas' => $petugas,
            'terpotong' => $sesi->count() >= self::MAKS_SESI,
        ]);
    }
}
