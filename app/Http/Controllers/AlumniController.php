<?php

namespace App\Http\Controllers;

use App\Alumni;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Data alumni per angkatan dengan pencarian dan ekspor Excel (admin dan kepala sekolah). */
class AlumniController extends Controller
{
    public function index(): View
    {
        return view('alumni.index', ['angkatan' => Alumni::query()->distinct()->orderByDesc('angkatan')->pluck('angkatan')]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            $this->saring($request)->with('siswa:id,nisn,nama_siswa,jenis_kelamin'),
            searchable: [],
            orderable: ['angkatan', 'tanggal_lulus', 'created_at'],
            map: fn (Alumni $a) => [
                'id' => $a->id,
                'nama' => $a->siswa?->nama_siswa ?? '-',
                'nisn' => $a->siswa?->nisn ?? '-',
                'angkatan' => $a->angkatan,
                'tanggal_lulus' => $a->tanggal_lulus->translatedFormat('d M Y'),
                'no_ijazah' => $a->no_ijazah ?: '-',
                'status' => (Alumni::STATUS[$a->status] ?? $a->status).'|'.($a->status === 'lulus' ? 'success' : 'warning'),
            ],
            defaultOrder: 'angkatan',
            searchRelations: ['siswa' => ['nama_siswa', 'nisn']],
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'alumni').'.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['Angkatan', 'NISN', 'Nama', 'Jenis Kelamin', 'Tanggal Lulus', 'Status', 'No. Ijazah']));
        $this->saring($request)->with('siswa')->orderByDesc('angkatan')->get()->sortBy(fn ($a) => $a->angkatan.'|'.$a->siswa?->nama_siswa)->each(fn (Alumni $a) => $w->addRow(Row::fromValues([
            $a->angkatan, $a->siswa?->nisn, $a->siswa?->nama_siswa, $a->siswa?->jenis_kelamin, $a->tanggal_lulus->format('Y-m-d'), Alumni::STATUS[$a->status] ?? $a->status, $a->no_ijazah ?? '',
        ])));
        $w->close();

        return response()->download($path, 'data-alumni.xlsx')->deleteFileAfterSend();
    }

    private function saring(Request $request)
    {
        return Alumni::query()->when($request->filled('angkatan'), fn ($q) => $q->where('angkatan', (int) $request->input('angkatan')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('siswa', fn ($s) => $s->where(fn ($w) => $w->where('nama_siswa', 'like', '%'.$request->input('q').'%')->orWhere('nisn', 'like', '%'.$request->input('q').'%'))));
    }
}
