<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Mapel;
use App\Support\Akademik;
use App\Support\Periode;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Jadwal pelajaran: admin mengelola; guru dan siswa melihat jadwalnya sendiri. */
class JadwalController extends Controller
{
    public function index(): View
    {
        return view('jadwal.index', [
            'kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'mapel' => Mapel::orderBy('mapel_nama')->get(['id', 'mapel_nama']),
            'guru' => Guru::orderBy('nama_guru')->get(['id', 'nama_guru', 'mapel_id']),
            'hari' => Jadwal::HARI,
            'akademik' => Akademik::aktif(),
        ]);
    }

    /** Daftar jadwal (tabel admin), disaring per kelas. */
    public function data(Request $request): JsonResponse
    {
        $query = $this->aktif(Jadwal::query()->with(['kelas:id,nama_kelas', 'mapel:id,mapel_nama', 'guru:id,nama_guru']))
            ->orderBy('hari')->orderBy('jam_mulai');

        if ($kelasId = (int) $request->input('kelas_id')) {
            $query->where('kelas_id', $kelasId);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: ['ruang'],
            orderable: ['hari', 'jam_mulai'],
            map: fn (Jadwal $j) => [
                'id' => $j->id,
                'kelas_id' => $j->kelas_id,
                'mapel_id' => $j->mapel_id,
                'guru_id' => $j->guru_id,
                'hari' => $j->hari,
                'nama_hari' => $j->nama_hari,
                'jam_mulai' => $j->mulai,
                'jam_selesai' => $j->selesai,
                'waktu' => $j->mulai.' – '.$j->selesai,
                'kelas' => $j->kelas?->nama_kelas ?? '-',
                'mapel' => $j->mapel?->mapel_nama ?? '-',
                'guru' => $j->guru?->nama_guru ?? '-',
                'ruang' => $j->ruang ?: '-',
            ],
            defaultOrder: 'hari',
            defaultDirection: 'asc',
            searchRelations: ['mapel' => ['mapel_nama'], 'guru' => ['nama_guru'], 'kelas' => ['nama_kelas']],
        );
    }

    /**
     * Jadwal mingguan sebagai JSON {hari: [{...}]}.
     * Admin: ?kelas_id=. Guru: jadwal mengajarnya. Siswa: jadwal kelasnya.
     */
    public function grid(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = $this->aktif(Jadwal::query()->with(['kelas:id,nama_kelas', 'mapel:id,mapel_nama', 'guru:id,nama_guru']))
            ->orderBy('hari')->orderBy('jam_mulai');

        match ($user->panel) {
            'guru' => $query->where('guru_id', $user->guru?->id ?? 0),
            'siswa' => $query->where('kelas_id', $user->siswa?->kelas_id ?? 0),
            default => $query->where('kelas_id', (int) $request->query('kelas_id')),
        };

        $perHari = [];
        foreach (Jadwal::HARI as $no => $nama) {
            $perHari[$no] = ['nama' => $nama, 'items' => []];
        }
        foreach ($query->get() as $j) {
            $perHari[$j->hari]['items'][] = [
                'id' => $j->id,
                'mulai' => $j->mulai,
                'selesai' => $j->selesai,
                'mapel' => $j->mapel?->mapel_nama ?? '-',
                'guru' => $j->guru?->nama_guru,
                'kelas' => $j->kelas?->nama_kelas,
                'ruang' => $j->ruang,
            ];
        }

        return response()->json(['hari' => array_values($perHari), 'hari_ini' => (int) now()->dayOfWeekIso]);
    }

    /** Halaman jadwal untuk guru (mengajar) dan siswa (kelas). */
    public function saya(): View
    {
        return view('jadwal.saya', ['akademik' => Akademik::aktif()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->pastikanTidakBentrok($data);

        Jadwal::create($data + ['semester_id' => Akademik::semesterAktif()->id]);

        return response()->json(['message' => 'Jadwal berhasil ditambahkan.'], 201);
    }

    public function update(Request $request, Jadwal $jadwal): JsonResponse
    {
        $data = $this->validated($request);
        Periode::pastikanTerbuka($jadwal->semester);
        $this->pastikanTidakBentrok($data, $jadwal);

        $jadwal->update($data);

        return response()->json(['message' => 'Jadwal berhasil diperbarui.']);
    }

    public function destroy(Jadwal $jadwal): JsonResponse
    {
        Periode::pastikanTerbuka($jadwal->semester);
        $jadwal->delete();

        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'mapel_id' => ['required', 'integer', SekolahAktif::ada('mapel')],
            'guru_id' => ['nullable', 'integer', SekolahAktif::ada('guru')],
            'hari' => ['required', 'integer', 'between:1,6'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang' => ['nullable', 'string', 'max:40'],
        ], [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ], [
            'kelas_id' => 'kelas', 'mapel_id' => 'mata pelajaran', 'guru_id' => 'guru', 'jam_mulai' => 'jam mulai', 'jam_selesai' => 'jam selesai',
        ]);

        $data['guru_id'] = $data['guru_id'] ?? null;
        // Simpan sebagai H:i:s agar perbandingan jam konsisten di semua database.
        $data['jam_mulai'] .= ':00';
        $data['jam_selesai'] .= ':00';

        return $data;
    }

    /** Satu kelas atau satu guru tidak boleh punya dua pelajaran yang jamnya bertumpuk di hari yang sama. */
    private function pastikanTidakBentrok(array $data, ?Jadwal $abaikan = null): void
    {
        $bertumpuk = fn () => $this->aktif(Jadwal::query())
            ->where('hari', $data['hari'])
            ->where('jam_mulai', '<', $data['jam_selesai'])
            ->where('jam_selesai', '>', $data['jam_mulai'])
            ->when($abaikan, fn ($q) => $q->whereKeyNot($abaikan->id));

        if ($bertumpuk()->where('kelas_id', $data['kelas_id'])->exists()) {
            throw ValidationException::withMessages(['jam_mulai' => 'Bentrok: kelas ini sudah punya pelajaran di jam tersebut.']);
        }

        if ($data['guru_id'] && $bertumpuk()->where('guru_id', $data['guru_id'])->exists()) {
            throw ValidationException::withMessages(['guru_id' => 'Bentrok: guru ini sudah mengajar di kelas lain pada jam tersebut.']);
        }
    }

    /** Hanya jadwal untuk semester aktif. */
    private function aktif($query)
    {
        return $query->where('semester_id', Akademik::aktif()['semester_id'] ?? 0);
    }
}
