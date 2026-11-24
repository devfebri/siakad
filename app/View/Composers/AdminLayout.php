<?php

namespace App\View\Composers;

use App\ChatSession;
use App\Pengumuman;
use App\PpdbPendaftar;
use App\Support\Akademik;
use Illuminate\View\View;

/** Data bersama untuk layout panel: sapaan, tahun ajaran aktif, notifikasi, dan lencana menu. */
class AdminLayout
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        // Notifikasi dan lencana menu cukup segar dalam 60 detik: tanpa cache, tiap halaman memicu 4-5 query tambahan.
        $sekolah = \App\Support\SekolahAktif::instance()->get()?->id ?? 0;
        [$notifikasi, $badges] = \Illuminate\Support\Facades\Cache::remember(
            'layout_admin_'.$sekolah.'_'.($user?->panel ?? '-'),
            60,
            fn () => $this->notifikasiDanLencana($user),
        );

        $view->with([
            'sapaan' => Akademik::sapaan(),
            'akademik' => Akademik::aktif(),
            'notifikasi' => $notifikasi,
            'navBadges' => $badges,
        ]);
    }

    /** @return array{0: array<int, array<string, string>>, 1: array<string, int>} */
    private function notifikasiDanLencana($user): array
    {
        $notifikasi = [];

        if ($user) {
            $notifikasi = Pengumuman::latest('pengumuman_id')->limit(3)->get(['pengumuman_id', 'pengumuman_judul', 'pengumuman_tanggal', 'created_at'])
                ->map(fn ($p) => [
                    'icon' => 'megaphone',
                    'title' => $p->pengumuman_judul,
                    'text' => ($p->created_at ?? $p->pengumuman_tanggal)?->diffForHumans() ?? '',
                    'url' => $user->panel === 'admin' ? route('admin/pengumuman') : $user->dashboardUrl(),
                ])->all();
        }

        // Bendahara/admin: pembayaran SPP online yang masuk dalam 24 jam terakhir.
        if (in_array($user?->panel, ['admin', 'bendahara'], true) && \App\Support\AksesSekolah::modul('keuangan')) {
            try {
                $online = \App\PembayaranSpp::aktif()->where('metode', 'online')->where('created_at', '>=', now()->subDay())->count();
                if ($online > 0) {
                    array_unshift($notifikasi, ['icon' => 'cash-coin', 'title' => $online.' pembayaran SPP online baru', 'text' => 'Dalam 24 jam terakhir', 'url' => route($user->panel.'/keuangan/pembayaran')]);
                }
            } catch (\Throwable) {
                // tabel keuangan belum dimigrasi: jangan sampai panel ikut error
            }
        }

        $badges = [];
        if ($user?->panel === 'admin') {
            $menunggu = PpdbPendaftar::where('status', 'menunggu')->count();
            $badges['ppdb'] = $menunggu;
            try {
                $badges['chat'] = ChatSession::where('status', 'menunggu')->count();
            } catch (\Throwable) {
                // tabel chat belum dimigrasi: jangan sampai seluruh panel admin ikut error
            }
            if ($menunggu > 0) {
                array_unshift($notifikasi, [
                    'icon' => 'person-plus',
                    'title' => $menunggu.' pendaftar PPDB menunggu',
                    'text' => 'Perlu diverifikasi',
                    'url' => route('admin/ppdb'),
                ]);
            }
        }

        return [$notifikasi, $badges];
    }
}
