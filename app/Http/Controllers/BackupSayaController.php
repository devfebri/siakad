<?php

namespace App\Http\Controllers;

use App\Backup\Pencadangan;
use App\BackupSekolah;
use App\LogAktivitas;
use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menu "Backup Data" untuk admin sekolah: membuat dan mengunduh backup (zip) data sekolahnya sendiri. */
class BackupSayaController extends Controller
{
    /** Batas pembuatan manual per sekolah per 24 jam (melindungi server dari permintaan berulang). */
    public const BATAS_HARIAN = 3;

    public function index(): View
    {
        $id = SekolahAktif::instance()->id();

        return view('backup.index', [
            'backup' => BackupSekolah::where('sekolah_id', $id)->latest('id')->limit(30)->get(),
            'sisaHariIni' => max(0, self::BATAS_HARIAN - $this->manualHariIni($id)),
            'berjalan' => BackupSekolah::where('sekolah_id', $id)->where('status', 'berjalan')->where('created_at', '>=', now()->subHours(2))->exists(),
        ]);
    }

    public function buat(): RedirectResponse
    {
        $sekolah = SekolahAktif::instance()->get();

        if (BackupSekolah::where('sekolah_id', $sekolah->id)->where('status', 'berjalan')->where('created_at', '>=', now()->subHours(2))->exists()) {
            return back()->with('toast_error', 'Backup sebelumnya masih dibuat. Tunggu sampai selesai.');
        }
        if ($this->manualHariIni($sekolah->id) >= self::BATAS_HARIAN) {
            return back()->with('toast_error', 'Batas backup manual hari ini sudah tercapai ('.self::BATAS_HARIAN.' kali). Coba lagi besok.');
        }

        Pencadangan::mulai($sekolah, 'manual', auth()->id());

        return redirect()->route('admin/backup')->with('toast_success', 'Backup dimulai. Muat ulang halaman ini beberapa saat lagi untuk mengunduhnya.');
    }

    public function unduh(BackupSekolah $backup): StreamedResponse
    {
        abort_unless((int) $backup->sekolah_id === SekolahAktif::instance()->id() && $backup->adaBerkas(), 404, 'Berkas backup tidak ditemukan.');
        LogAktivitas::catat('backup.unduh', "Backup #{$backup->id} diunduh oleh ".auth()->user()->name.'.', null, ['backup_id' => $backup->id]);

        return Storage::disk('local')->download($backup->berkas, $backup->namaUnduh());
    }

    private function manualHariIni(int $sekolahId): int
    {
        return BackupSekolah::where('sekolah_id', $sekolahId)->where('jenis', 'manual')->where('created_at', '>=', now()->subDay())->count();
    }
}
