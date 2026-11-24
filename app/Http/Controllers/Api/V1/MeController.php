<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jadwal;
use App\Kelas;
use App\Support\Akademik;
use App\Support\SekolahAktif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identitas pengguna pemilik token SSO: user_id, sekolah_id, peran, dan rombel.
 * Siswa: rombelnya. Guru: rombel yang diajar pada semester aktif dan rombel yang diwalikan.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $sekolah = SekolahAktif::instance()->get();
        $rombel = collect();
        $siswaId = $guruId = null;

        if ($siswa = $user->siswa) {
            $siswaId = $siswa->id;
            $rombel = Kelas::whereKey($siswa->kelas_id)->get();
        }
        if ($guru = $user->guru) {
            $guruId = $guru->id;
            $diajar = Jadwal::where('guru_id', $guru->id)->where('semester_id', Akademik::semesterAktif()->id)->pluck('kelas_id');
            $rombel = Kelas::whereIn('id', $diajar)->orWhere('wali_guru_id', $guru->id)->get();
        }

        return response()->json(['data' => [
            'user_id' => $user->id,
            'sekolah_id' => $sekolah->id,
            'sekolah' => $sekolah->subdomain,
            'nama' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'peran' => $user->kodePeran(),
            'siswa_id' => $siswaId,
            'guru_id' => $guruId,
            'rombel' => $rombel->map(fn (Kelas $k) => ['id' => $k->id, 'nama' => $k->nama_kelas, 'tingkat' => $k->tingkat, 'tahun_ajaran_id' => $k->tahun_ajaran_id])->values()->all(),
        ]]);
    }
}
