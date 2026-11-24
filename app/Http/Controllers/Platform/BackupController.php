<?php

namespace App\Http\Controllers\Platform;

use App\Backup\Pencadangan;
use App\BackupSekolah;
use App\Http\Controllers\Controller;
use App\LogAktivitas;
use App\Sekolah;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Panel platform: backup semua sekolah. Buat manual, unduh, dan hapus. Backup terjadwal dan arsip dibuat oleh perintah terjadwal. */
class BackupController extends Controller
{
    public function index(Request $request): View
    {
        $sekolahId = (int) $request->input('sekolah_id');

        return view('platform.backup.index', [
            'sekolah' => Sekolah::orderBy('nama')->get(['id', 'nama', 'status', 'dinonaktifkan_pada', 'diarsipkan_pada']),
            'backup' => BackupSekolah::with(['sekolah:id,nama', 'pembuat:id,name'])->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))->latest('id')->limit(60)->get(),
            'sekolahId' => $sekolahId,
        ]);
    }

    public function buat(Request $request): RedirectResponse
    {
        $data = $request->validate(['sekolah_id' => ['required', 'integer', 'exists:sekolah,id']], [], ['sekolah_id' => 'sekolah']);
        $sekolah = Sekolah::findOrFail($data['sekolah_id']);

        if (BackupSekolah::where('sekolah_id', $sekolah->id)->where('status', 'berjalan')->where('created_at', '>=', now()->subHours(2))->exists()) {
            return back()->with('toast_error', "Backup {$sekolah->nama} masih dibuat. Tunggu sampai selesai.");
        }

        Pencadangan::mulai($sekolah, 'manual', auth()->id());

        return redirect()->route('platform/backup')->with('toast_success', "Backup {$sekolah->nama} dimulai. Muat ulang halaman ini beberapa saat lagi untuk melihat hasilnya.");
    }

    public function unduh(BackupSekolah $backup): StreamedResponse
    {
        abort_unless($backup->adaBerkas(), 404, 'Berkas backup tidak ditemukan.');
        LogAktivitas::catat('backup.unduh', "Backup #{$backup->id} diunduh oleh Super Admin.", $backup->sekolah_id, ['backup_id' => $backup->id]);

        return Storage::disk('local')->download($backup->berkas, $backup->namaUnduh());
    }

    public function hapus(BackupSekolah $backup): RedirectResponse
    {
        LogAktivitas::catat('backup.hapus', "Backup #{$backup->id} ({$backup->jenis}) dihapus.", $backup->sekolah_id, ['backup_id' => $backup->id]);
        Pencadangan::hapus($backup);

        return redirect()->route('platform/backup')->with('toast_success', 'Backup dihapus.');
    }
}
