<?php

namespace App\Http\Controllers;

use App\Absensi;
use App\Jadwal;
use App\Pembayaran\GerbangException;
use App\Pembayaran\SppOnline;
use App\PembayaranSpp;
use App\PengaturanPembayaranSekolah;
use App\TransaksiSpp;
use App\Support\SppPdf;
use App\Tagihan;
use App\Pengumuman;
use App\Siswa;
use App\Support\Akademik;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Portal orang tua: memilih anak, melihat kehadiran, nilai dan rapor anak yang terhubung dengan akunnya. */
class OrtuController extends Controller
{
    private const SESI = 'ortu_anak_id';

    public function dashboard(): View
    {
        $anak = $this->anakDipilih();
        $kehadiran = null;
        $jadwal = collect();

        if ($anak) {
            $kehadiran = AbsensiController::hitung(
                Absensi::where('siswa_id', $anak->id)->whereBetween('tanggal', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->get()
            );
            $hari = (int) now()->dayOfWeekIso;
            if ($anak->kelas_id && $hari <= 6) {
                $jadwal = Jadwal::with(['mapel:id,mapel_nama', 'guru:id,nama_guru'])->where('kelas_id', $anak->kelas_id)->where('hari', $hari)
                    ->where('semester_id', Akademik::aktif()['semester_id'] ?? 0)->orderBy('jam_mulai')->get();
            }
        }

        return view('ortu.dashboard', [
            'daftarAnak' => $this->daftarAnak(),
            'anak' => $anak,
            'wali' => $anak?->kelas?->wali,
            'kehadiran' => $kehadiran,
            'jadwalHariIni' => $jadwal,
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(3)->get(),
        ]);
    }

    /** Pilih anak yang sedang dilihat (disimpan di sesi). */
    public function pilihAnak(Request $request, Siswa $siswa): RedirectResponse
    {
        abort_unless($this->daftarAnak()->contains('id', $siswa->id), 403, 'Siswa ini tidak terhubung dengan akun Anda.');

        $request->session()->put(self::SESI, $siswa->id);

        return back()->with('toast_success', 'Menampilkan data '.$siswa->nama_siswa.'.');
    }

    public function kehadiran(): View
    {
        $anak = $this->anakWajib();

        return view('absensi.saya', [
            'dataUrl' => route('ortu/kehadiran.data'), 'judul' => 'Kehadiran Anak',
            'subjudul' => 'Rekap kehadiran '.$anak->nama_siswa.' per bulan.', 'daftarAnak' => $this->daftarAnak(), 'anak' => $anak,
        ]);
    }

    public function kehadiranData(Request $request): JsonResponse
    {
        return AbsensiController::rekapBulan($this->anakWajib(), $request);
    }

    public function nilai(Request $request): View
    {
        $anak = $this->anakWajib()->loadMissing('kelas', 'user');

        return app(NilaiController::class)->tampilkanRapor($request, $anak)->with(['daftarAnak' => $this->daftarAnak(), 'anak' => $anak]);
    }

    /** Tagihan dan riwayat pembayaran anak yang dipilih (modul Keuangan). */
    public function tagihan(): View
    {
        $anak = $this->anakWajib();
        $tagihan = Tagihan::where('siswa_id', $anak->id)->where('status', '!=', 'batal')->orderByRaw("status = 'lunas'")->orderBy('jatuh_tempo')->get();

        $pg = PengaturanPembayaranSekolah::first();

        return view('ortu.tagihan', [
            'bayarOnline' => (bool) $pg?->gerbangAktif(),
            'biaya' => $pg ? ['ditanggung' => $pg->biaya_admin_ditanggung, 'tetap' => $pg->biaya_admin_tetap, 'persen' => $pg->biaya_admin_persen] : null,
            'menunggu' => TransaksiSpp::where('user_id', auth()->id())->where('status', 'menunggu')->latest('id')->get()->first(fn ($t) => $t->masihBerlaku()),
            'baruKembali' => request()->query('bayar') === 'kembali',
            'daftarAnak' => $this->daftarAnak(), 'anak' => $anak, 'tagihan' => $tagihan,
            'sisa' => (int) $tagihan->sum('sisa'), 'terbayar' => (int) $tagihan->sum('terbayar'),
            'pembayaran' => PembayaranSpp::aktif()->whereIn('tagihan_id', $tagihan->pluck('id'))->with('tagihan:id,nama')->orderByDesc('tanggal')->orderByDesc('id')->get(),
        ]);
    }

    /** Bayar online tagihan yang dipilih (boleh beberapa sekaligus): membuat transaksi di gerbang milik sekolah lalu membuka halaman bayar. */
    public function bayar(Request $request): RedirectResponse
    {
        $data = $request->validate(['tagihan_id' => ['required', 'array', 'min:1', 'max:60'], 'tagihan_id.*' => ['integer']], ['tagihan_id.required' => 'Pilih dulu tagihan yang ingin dibayar.', 'tagihan_id.min' => 'Pilih dulu tagihan yang ingin dibayar.']);

        try {
            $transaksi = SppOnline::mulai($request->user(), $data['tagihan_id']);
        } catch (GerbangException $e) {
            return redirect()->route('ortu/tagihan')->with('toast_error', $e->getMessage());
        }

        return redirect()->away($transaksi->url_bayar);
    }

    /** Kuitansi hanya untuk pembayaran milik anak yang terhubung dengan akun ini. */
    public function kuitansi(PembayaranSpp $pembayaran)
    {
        $pembayaran->loadMissing('tagihan');
        abort_unless($pembayaran->tagihan && $this->daftarAnak()->contains('id', $pembayaran->tagihan->siswa_id), 404);

        return SppPdf::kuitansi($pembayaran)->download(SppPdf::namaBerkas($pembayaran));
    }

    public function profil(): View
    {
        return view('ortu.profil', ['ortu' => auth()->user()->orangTua]);
    }

    public function updateProfil(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ], [], ['no_hp' => 'nomor HP']);

        $user = auth()->user();
        DB::transaction(function () use ($user, $data) {
            $user->orangTua?->update($data);
            $user->update(['name' => $data['nama']]);
        });

        return response()->json(['message' => 'Profil diperbarui.']);
    }

    public function ubahPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ], ['password.confirmed' => 'Konfirmasi password tidak sama.'], ['password_lama' => 'password lama']);

        $user = auth()->user();
        if (! Hash::check($data['password_lama'], $user->password)) {
            return response()->json(['message' => 'Password lama salah.', 'errors' => ['password_lama' => ['Password lama salah.']]], 422);
        }

        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password diubah.']);
    }

    /** @return \Illuminate\Support\Collection<int, Siswa> anak yang terhubung dengan akun ini */
    private function daftarAnak()
    {
        return auth()->user()->orangTua?->anak()->with('kelas.wali:id,nama_guru')->get() ?? collect();
    }

    private function anakDipilih(): ?Siswa
    {
        $daftar = $this->daftarAnak();
        $id = session(self::SESI);

        return $daftar->firstWhere('id', $id) ?? $daftar->first();
    }

    private function anakWajib(): Siswa
    {
        $anak = $this->anakDipilih();
        abort_unless($anak, 404, 'Belum ada siswa yang terhubung dengan akun Anda. Hubungi admin sekolah.');

        return $anak;
    }
}
