<?php

namespace App\Http\Controllers;

use App\Support\DataTableResponse;
use App\Unduhan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Admin: kelola berkas untuk menu Unduhan di situs publik. */
class UnduhanController extends Controller
{
    public function index(): View
    {
        return view('unduhan.index', ['kategori' => Unduhan::KATEGORI]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Unduhan::query(),
            searchable: ['judul', 'kategori', 'nama_asli', 'deskripsi'],
            orderable: ['judul', 'kategori', 'jumlah_unduh', 'created_at'],
            map: fn (Unduhan $u) => [
                'id' => $u->id,
                'judul' => $u->judul,
                'kategori' => $u->kategori,
                'deskripsi' => $u->deskripsi,
                'nama_asli' => $u->nama_asli,
                'ukuran' => $u->ukuran_teks,
                'jumlah_unduh' => $u->jumlah_unduh,
                'urutan' => $u->urutan,
                'aktif' => $u->aktif ? 1 : 0,
                'status' => $u->aktif ? 'Tampil|success' : 'Disembunyikan|muted',
                'file_url' => asset(Unduhan::DIR.'/'.$u->berkas),
                'tanggal' => $u->created_at?->translatedFormat('d M Y') ?? '-',
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if (! $request->hasFile('berkas')) {
            throw ValidationException::withMessages(['berkas' => 'Pilih berkas yang akan diunggah.']);
        }

        Unduhan::create($data + $this->simpanBerkas($request));

        return response()->json(['message' => 'Berkas berhasil ditambahkan.'], 201);
    }

    public function update(Request $request, Unduhan $unduhan): JsonResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('berkas')) {
            $baru = $this->simpanBerkas($request);
            $this->hapusBerkas($unduhan);
            $data += $baru;
        }

        $unduhan->update($data);

        return response()->json(['message' => 'Berkas berhasil diperbarui.']);
    }

    public function destroy(Unduhan $unduhan): JsonResponse
    {
        $this->hapusBerkas($unduhan);
        $unduhan->delete();

        return response()->json(['message' => 'Berkas berhasil dihapus.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'string', 'max:60'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'berkas' => ['nullable', 'file', 'mimes:'.implode(',', Unduhan::EKSTENSI), 'max:'.Unduhan::MAKS_KB],
        ], [
            'berkas.max' => 'Ukuran berkas maksimal 10 MB.',
            'berkas.mimes' => 'Jenis berkas tidak diizinkan. Gunakan PDF, Word, Excel, PowerPoint, ZIP, atau gambar.',
            'berkas.uploaded' => 'Berkas gagal diunggah. Periksa ukurannya (maks. 10 MB).',
        ], ['judul' => 'judul', 'kategori' => 'kategori', 'berkas' => 'berkas']);

        return [
            'judul' => trim(strip_tags($data['judul'])),
            'kategori' => trim(strip_tags($data['kategori'])),
            'deskripsi' => isset($data['deskripsi']) ? trim(strip_tags($data['deskripsi'])) ?: null : null,
            'urutan' => $data['urutan'] ?? 0,
            'aktif' => $request->boolean('aktif'),
        ];
    }

    /** @return array{berkas: string, nama_asli: string, mime: string, ukuran: int} */
    private function simpanBerkas(Request $request): array
    {
        $file = $request->file('berkas');
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $nama = Str::random(32).'.'.$ext;
        $asli = Str::limit(preg_replace('/[\\\\\/:*?"<>|\p{C}]+/u', '', $file->getClientOriginalName()), 140, '');
        $mime = (string) $file->getMimeType();
        $ukuran = (int) $file->getSize();

        $file->move(public_path(Unduhan::DIR), $nama);

        return ['berkas' => $nama, 'nama_asli' => $asli !== '' ? $asli : $nama, 'mime' => $mime, 'ukuran' => $ukuran];
    }

    private function hapusBerkas(Unduhan $unduhan): void
    {
        if (File::isFile($unduhan->path)) {
            File::delete($unduhan->path);
        }
    }
}
