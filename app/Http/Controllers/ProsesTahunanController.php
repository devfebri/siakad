<?php

namespace App\Http\Controllers;

use App\Kelas;
use App\Kenaikan;
use App\LogAktivitas;
use App\Siswa;
use App\Support\ProsesTahunan;
use App\Support\SekolahAktif;
use App\Support\Tingkat;
use App\TahunAjaran;
use App\UsulanKenaikan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Proses akhir tahun: buka tahun ajaran baru (draf), kenaikan/kelulusan per rombel atau per siswa, aktivasi. Hanya admin. */
class ProsesTahunanController extends Controller
{
    public function index(): View
    {
        $aktif = ProsesTahunan::tahunAktif();
        $baru = ProsesTahunan::tahunBaru();
        $data = ['aktif' => $aktif, 'baru' => $baru, 'rombel' => collect(), 'rombelBaru' => collect(), 'tingkatAkhir' => Tingkat::akhir()];

        if ($aktif && $baru) {
            $rombel = Kelas::where('tahun_ajaran_id', $aktif->id)->with('wali:id,nama_guru')->orderBy('nama_kelas')->get();
            $kenaikan = Kenaikan::where('tahun_ajaran_tujuan_id', $baru->id)->with('siswa:id')->get()->keyBy('siswa_id');
            $usulan = UsulanKenaikan::where('tahun_ajaran_id', $aktif->id)->get()->keyBy('siswa_id');
            $rombelBaru = Kelas::where('tahun_ajaran_id', $baru->id)->orderBy('nama_kelas')->get();

            $data['rombel'] = $rombel->map(function (Kelas $k) use ($kenaikan, $usulan, $rombelBaru, $baru) {
                $siswa = $k->anggota()->where('siswa.status', 'aktif')->orderBy('nama_siswa')->get(['siswa.id', 'nisn', 'nama_siswa']);

                return [
                    'kelas' => $k,
                    'akhir' => ProsesTahunan::tingkatAkhir($k),
                    'siswa' => $siswa->map(fn ($s) => ['model' => $s, 'usulan' => $usulan->get($s->id), 'kenaikan' => $kenaikan->get($s->id)]),
                    'diproses' => $siswa->filter(fn ($s) => $kenaikan->has($s->id))->count(),
                    'tujuanNaik' => ProsesTahunan::tujuanBawaan($k, $baru, 'naik')?->id,
                    'tujuanTinggal' => ProsesTahunan::tujuanBawaan($k, $baru, 'tinggal')?->id,
                ];
            });
            $data['rombelBaru'] = $rombelBaru;
            $data['belum'] = ProsesTahunan::belumDiproses($aktif, $baru)->count();
        }

        return view('proses-tahunan.index', $data + ['hasil' => Kenaikan::HASIL]);
    }

    public function buka(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'regex:/^\d{4}\/\d{4}$/', SekolahAktif::unik('tahun_ajaran', 'nama')],
            'salin_wali' => ['nullable', 'boolean'],
            'salin_jadwal' => ['nullable', 'boolean'],
        ], ['nama.regex' => 'Format tahun ajaran harus seperti 2027/2028.', 'nama.unique' => 'Tahun ajaran ini sudah ada.'], ['nama' => 'tahun ajaran']);

        [$awal, $akhir] = array_map('intval', explode('/', $data['nama']));
        $aktif = ProsesTahunan::tahunAktif();
        if ($akhir !== $awal + 1 || ($aktif && $awal <= (int) substr($aktif->nama, 0, 4))) {
            throw ValidationException::withMessages(['nama' => 'Tahun ajaran baru harus berurutan dan setelah tahun ajaran aktif.']);
        }

        $baru = ProsesTahunan::bukaTahunBaru($data['nama'], $request->boolean('salin_wali'), $request->boolean('salin_jadwal'));

        return $this->selesai("Tahun ajaran {$baru->nama} dibuka sebagai draf dengan {$baru->semester->count()} semester dan rombel salinan.", 201);
    }

    /** Proses seluruh siswa satu rombel sesuai usulan wali kelas. */
    public function prosesRombel(Request $request, Kelas $kelas): JsonResponse
    {
        $baru = $this->tahunBaru();
        $data = $request->validate([
            'tanpa_usulan' => ['nullable', 'boolean'],
            'tujuan_naik' => ['nullable', 'integer', SekolahAktif::ada('rombel')],
            'tujuan_tinggal' => ['nullable', 'integer', SekolahAktif::ada('rombel')],
        ]);
        $hasil = ProsesTahunan::prosesRombel(
            $kelas, $baru, $request->boolean('tanpa_usulan'),
            isset($data['tujuan_naik']) ? Kelas::find($data['tujuan_naik']) : null,
            isset($data['tujuan_tinggal']) ? Kelas::find($data['tujuan_tinggal']) : null,
        );

        $pesan = "{$hasil['diproses']} siswa diproses".($hasil['dilewati'] ? ", {$hasil['dilewati']} dilewati (belum ada usulan)" : '').'.';
        if ($hasil['gagal']) {
            return response()->json(['message' => $pesan.' Bermasalah: '.implode(' ', array_slice($hasil['gagal'], 0, 3)), 'gagal' => $hasil['gagal'], 'redirect' => route('admin/proses-tahunan')], 207);
        }

        return $this->selesai($pesan);
    }

    /** Proses atau ubah hasil satu siswa. */
    public function prosesSiswa(Request $request, Siswa $siswa): JsonResponse
    {
        $baru = $this->tahunBaru();
        $data = $request->validate([
            'hasil' => ['required', Rule::in(array_keys(Kenaikan::HASIL))],
            'tujuan_id' => ['nullable', 'integer', SekolahAktif::ada('rombel')],
            'no_ijazah' => ['nullable', 'string', 'max:60'],
        ], [], ['tujuan_id' => 'rombel tujuan', 'no_ijazah' => 'nomor ijazah']);

        $asal = $this->rombelAsal($siswa);
        $tujuan = isset($data['tujuan_id']) ? Kelas::find($data['tujuan_id']) : ProsesTahunan::tujuanBawaan($asal, $baru, $data['hasil']);
        ProsesTahunan::prosesSiswa($siswa, $asal, $baru, $data['hasil'], $tujuan, $data['no_ijazah'] ?? null);

        return $this->selesai("{$siswa->nama_siswa}: ".Kenaikan::HASIL[$data['hasil']].'.');
    }

    public function batalSiswa(Siswa $siswa): JsonResponse
    {
        $ok = ProsesTahunan::batalSiswa($siswa, $this->tahunBaru());
        if ($ok) {
            LogAktivitas::catat('kenaikan.batal', "Proses kenaikan {$siswa->nama_siswa} dibatalkan.", null, ['siswa_id' => $siswa->id]);
        }

        return $this->selesai($ok ? "Proses {$siswa->nama_siswa} dibatalkan." : 'Siswa ini belum diproses.');
    }

    public function batalSemua(): JsonResponse
    {
        $jumlah = ProsesTahunan::batalSemua($this->tahunBaru());

        return $this->selesai("{$jumlah} hasil proses dibatalkan.");
    }

    /** Batalkan tahun ajaran draf sepenuhnya (rombel salinan dan jadwal salinan ikut dihapus bila belum dipakai). */
    public function batalTahun(): JsonResponse
    {
        $baru = $this->tahunBaru();
        DB::transaction(function () use ($baru) {
            ProsesTahunan::batalSemua($baru);
            $rombel = Kelas::where('tahun_ajaran_id', $baru->id)->get();
            if ($rombel->contains(fn (Kelas $k) => $k->anggota()->exists())) {
                abort(409, 'Tahun ajaran draf sudah berisi siswa (mis. dari PPDB). Keluarkan siswanya dulu.');
            }
            $semesterId = $baru->semester->pluck('id');
            \App\Jadwal::whereIn('semester_id', $semesterId)->delete();
            Kelas::where('tahun_ajaran_id', $baru->id)->delete();
            Kelas::sinkronkanPeranWali();
            $baru->semester()->delete();
            $baru->delete();
            LogAktivitas::catat('tahun.batal', "Tahun ajaran draf {$baru->nama} dibatalkan.", null, ['tahun' => $baru->nama]);
        });

        return $this->selesai("Tahun ajaran draf {$baru->nama} dibatalkan.");
    }

    public function aktifkan(): JsonResponse
    {
        $baru = $this->tahunBaru();
        $baru->load('semester');
        ProsesTahunan::aktifkan($baru->semester->firstWhere('jenis', 'Ganjil') ?? $baru->semester->first());

        return $this->selesai("Tahun ajaran {$baru->nama} aktif. Tahun ajaran sebelumnya ditutup (baca-saja).");
    }

    private function tahunBaru(): TahunAjaran
    {
        return ProsesTahunan::tahunBaru() ?? abort(409, 'Belum ada tahun ajaran draf. Buka tahun ajaran baru dulu.');
    }

    private function rombelAsal(Siswa $siswa): Kelas
    {
        $aktif = ProsesTahunan::tahunAktif();
        $id = DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->where('tahun_ajaran_id', $aktif?->id)->value('rombel_id');

        return Kelas::find($id) ?? throw ValidationException::withMessages(['siswa' => "{$siswa->nama_siswa} tidak punya rombel di tahun ajaran aktif."]);
    }

    private function selesai(string $pesan, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $pesan, 'redirect' => route('admin/proses-tahunan')], $status);
    }
}
