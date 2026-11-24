<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Akademik;
use App\Support\Api\Sumber;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** API data induk v1 (hanya baca). Semua query otomatis terbatas pada sekolah pemilik klien (SekolahAktif diisi AuthKlienApi). */
class DataIndukController extends Controller
{
    public function sekolah(): JsonResponse
    {
        $s = SekolahAktif::instance()->get();

        return response()->json(['data' => [
            'id' => $s->id, 'nama' => $s->nama, 'npsn' => $s->npsn, 'jenjang' => $s->jenjang, 'subdomain' => $s->subdomain,
            'url' => $s->url('/'), 'updated_at' => $s->updated_at?->toIso8601String(),
        ]]);
    }

    /** Tahun ajaran dan semester yang sedang berjalan. */
    public function aktif(): JsonResponse
    {
        $semester = Akademik::semesterAktif();
        $ta = TahunAjaran::with('semester')->find($semester->tahun_ajaran_id);

        return response()->json(['data' => [
            'tahun_ajaran' => $ta ? Sumber::petakan('tahun-ajaran', $ta) : null,
            'semester' => Sumber::semester($semester),
        ]]);
    }

    public function index(Request $request, string $sumber): JsonResponse
    {
        $def = Sumber::semua()[$sumber] ?? abort(404, 'Data tidak dikenal.');

        $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'updated_since' => ['nullable', 'date'],
        ]);

        $query = $def['model']::query();
        foreach ($def['filter'] as $parameter => $kolom) {
            if ($request->filled($parameter)) {
                $query->where($kolom, $request->query($parameter));
            }
        }
        if ($request->filled('updated_since')) {
            $query->where($query->getModel()->getTable().'.updated_at', '>=', Carbon::parse($request->query('updated_since')));
        }
        if ($def['with']) {
            $query->with($def['with']);
        }

        $hal = $query->orderBy($query->getModel()->getTable().'.id')->paginate((int) $request->query('per_page', 50))->withQueryString();

        return response()->json([
            'data' => collect($hal->items())->map($def['peta'])->all(),
            'meta' => ['page' => $hal->currentPage(), 'per_page' => $hal->perPage(), 'total' => $hal->total(), 'last_page' => $hal->lastPage()],
            'links' => ['next' => $hal->nextPageUrl(), 'prev' => $hal->previousPageUrl()],
        ]);
    }
}
