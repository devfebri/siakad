<?php

namespace App\Http\Controllers;

use App\Agenda;
use App\Support\DataTableResponse;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function index(): View
    {
        return view('agenda.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Agenda::query(),
            searchable: ['agenda_nama', 'agenda_tempat', 'agenda_deskripsi', 'agenda_author'],
            orderable: ['agenda_nama', 'agenda_tempat', 'agenda_waktu', 'created_at'],
            map: fn (Agenda $agenda) => [
                'id' => $agenda->agenda_id,
                'agenda_nama' => $agenda->agenda_nama,
                'agenda_deskripsi' => $agenda->agenda_deskripsi,
                'agenda_mulai' => $this->isoDate($agenda->agenda_mulai),
                'agenda_selesai' => $this->isoDate($agenda->agenda_selesai),
                'tanggal' => $this->rangeLabel($agenda->agenda_mulai, $agenda->agenda_selesai),
                'agenda_tempat' => $agenda->agenda_tempat,
                'agenda_waktu' => $agenda->agenda_waktu,
                'agenda_keterangan' => $agenda->agenda_keterangan,
                'agenda_author' => $agenda->agenda_author,
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        Agenda::create($this->validated($request) + ['agenda_author' => auth()->user()->name]);

        return response()->json(['message' => 'Agenda berhasil dibuat.'], 201);
    }

    public function update(Request $request, Agenda $agenda): JsonResponse
    {
        $agenda->update($this->validated($request));

        return response()->json(['message' => 'Agenda berhasil diperbarui.']);
    }

    public function destroy(Agenda $agenda): JsonResponse
    {
        $agenda->delete();

        return response()->json(['message' => 'Agenda berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'agenda_nama' => ['required', 'string', 'max:200'],
            'agenda_deskripsi' => ['required', 'string'],
            'agenda_mulai' => ['required', 'date'],
            'agenda_selesai' => ['required', 'date', 'after_or_equal:agenda_mulai'],
            'agenda_tempat' => ['required', 'string', 'max:100'],
            'agenda_waktu' => ['required', 'string', 'max:30'],
            'agenda_keterangan' => ['nullable', 'string', 'max:200'],
        ], [], [
            'agenda_nama' => 'nama agenda',
            'agenda_deskripsi' => 'deskripsi',
            'agenda_mulai' => 'tanggal mulai',
            'agenda_selesai' => 'tanggal selesai',
            'agenda_tempat' => 'tempat',
            'agenda_waktu' => 'waktu',
            'agenda_keterangan' => 'keterangan',
        ]);

        // Kolom keterangan NOT NULL di database; kosong disimpan sebagai "-".
        $data['agenda_keterangan'] = ($data['agenda_keterangan'] ?? null) ?: '-';

        return $data;
    }

    /** Data lama menyimpan tanggal sebagai teks (mis. 05/20/2021); ubah ke Y-m-d untuk input tanggal. */
    private function isoDate(?string $value): string
    {
        return $this->parseDate($value)?->format('Y-m-d') ?? '';
    }

    private function rangeLabel(?string $from, ?string $to): string
    {
        $a = $this->parseDate($from);
        $b = $this->parseDate($to);

        if (! $a || ! $b) {
            return trim(($from ?? '').' – '.($to ?? ''), ' –');
        }

        return $a->isSameDay($b)
            ? $a->translatedFormat('d M Y')
            : $a->translatedFormat('d M Y').' – '.$b->translatedFormat('d M Y');
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
