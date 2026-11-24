<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Langganan;
use App\LogAktivitas;
use App\Paket;
use App\Sekolah;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Panel platform: daftar langganan semua sekolah dan koreksi manual (paket, tanggal, status). */
class LanggananController extends Controller
{
    public function index(): View
    {
        return view('platform.langganan.index', ['paket' => Paket::orderBy('urutan')->get(['id', 'nama'])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Langganan::query()->with(['sekolah:id,nama,subdomain,status', 'paket:id,nama']);

        if ($status = $request->input('status')) {
            match ($status) {
                'aktif' => $query->where('status', 'aktif')->whereDate('berakhir', '>=', today()),
                'berakhir' => $query->where('status', 'aktif')->whereDate('berakhir', '<', today()),
                'dibatalkan' => $query->where('status', 'dibatalkan'),
                default => null,
            };
        }
        if ($request->boolean('segera')) {
            $query->where('status', 'aktif')->whereBetween('berakhir', [today()->toDateString(), today()->addDays(30)->toDateString()]);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: [],
            orderable: ['berakhir', 'mulai', 'created_at'],
            map: function (Langganan $l) {
                $sisa = (int) today()->diffInDays($l->berakhir, false);

                return [
                    'id' => $l->id,
                    'sekolah' => $l->sekolah?->nama ?? '-',
                    'sekolah_id' => $l->sekolah_id,
                    'paket' => $l->paket?->nama ?? '-',
                    'paket_id' => $l->paket_id,
                    'durasi_bulan' => $l->durasi_bulan.' bulan',
                    'mulai' => $l->mulai->format('Y-m-d'),
                    'berakhir' => $l->berakhir->format('Y-m-d'),
                    'periode' => $l->mulai->translatedFormat('d M Y').' – '.$l->berakhir->translatedFormat('d M Y'),
                    'status_raw' => $l->status,
                    'catatan' => $l->catatan,
                    'status' => match (true) {
                        $l->status === 'dibatalkan' => 'Dibatalkan|muted',
                        $sisa < 0 => 'Berakhir|danger',
                        $sisa <= 30 => "Sisa {$sisa} hari|warning",
                        default => 'Aktif|success',
                    },
                ];
            },
            searchRelations: ['sekolah' => ['nama', 'subdomain'], 'paket' => ['nama']],
            defaultOrder: 'berakhir',
            defaultDirection: 'asc',
        );
    }

    public function update(Request $request, Langganan $langganan): JsonResponse
    {
        $data = $request->validate([
            'paket_id' => ['required', 'integer', Rule::exists('paket', 'id')],
            'mulai' => ['required', 'date'],
            'berakhir' => ['required', 'date', 'after_or_equal:mulai'],
            'status_raw' => ['required', Rule::in(['aktif', 'dibatalkan'])],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], ['berakhir.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.'], ['paket_id' => 'paket', 'status_raw' => 'status']);

        $langganan->update([
            'paket_id' => $data['paket_id'], 'mulai' => $data['mulai'], 'berakhir' => $data['berakhir'],
            'status' => $data['status_raw'], 'catatan' => $data['catatan'] ?? null,
        ]);

        $sekolah = Sekolah::find($langganan->sekolah_id);
        LogAktivitas::catat('langganan.ubah', "Langganan {$sekolah?->nama} dikoreksi: {$data['mulai']} s.d. {$data['berakhir']} ({$data['status_raw']}).", $langganan->sekolah_id);

        return response()->json(['message' => 'Langganan diperbarui.']);
    }
}
