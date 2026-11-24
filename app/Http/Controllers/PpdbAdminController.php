<?php

namespace App\Http\Controllers;

use App\Kelas;
use App\PpdbPendaftar;
use App\Support\PpdbKonversi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Ppdb;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin PPDB: daftar pendaftar, detail, verifikasi (terima/tolak), ekspor. */
class PpdbAdminController extends Controller
{
    public function index(): View
    {
        return view('ppdb.index', [
            'siapKonversi' => PpdbKonversi::jumlahSiap(),
            'kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'hasilKonversi' => session('hasil_konversi'),
            'ringkasan' => PpdbPendaftar::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PpdbPendaftar::query();

        if (in_array($status = $request->input('status'), array_keys(PpdbPendaftar::STATUS), true)) {
            $query->where('status', $status);
        }
        if ($jalur = $request->input('jalur')) {
            $query->where('jalur', $jalur);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: ['no_pendaftaran', 'nama', 'asal_sekolah', 'nisn', 'telepon'],
            orderable: ['no_pendaftaran', 'nama', 'created_at'],
            map: fn (PpdbPendaftar $p) => [
                'id' => $p->id,
                'no_pendaftaran' => $p->no_pendaftaran,
                'nama' => $p->nama,
                'asal_sekolah' => $p->asal_sekolah,
                'jalur' => $p->jalur,
                'status' => $p->status_label.'|'.(PpdbPendaftar::STATUS[$p->status]['badge'] ?? 'muted'),
                'jadi_siswa' => $p->siswa_id ? 'Sudah jadi siswa|primary' : '',
                'tanggal' => $p->created_at?->translatedFormat('d M Y') ?? '-',
            ],
        );
    }

    /** Formulir tambah pendaftar oleh admin (mis. pendaftar yang datang langsung ke sekolah). */
    public function create(): View
    {
        return view('ppdb.create', [
            'jalur' => PpdbPendaftar::JALUR,
            'peminatan' => PpdbPendaftar::PEMINATAN,
            'tahunAjaran' => Ppdb::status()['tahun_ajaran'],
        ]);
    }

    /** Admin boleh menambah pendaftar walau pendaftaran online sedang ditutup. */
    public function store(Request $request): JsonResponse
    {
        [$aturan, $pesan, $atribut] = PpdbPendaftar::aturan();
        $data = $request->validate($aturan + [
            'status' => ['nullable', Rule::in(array_keys(PpdbPendaftar::STATUS))],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], $pesan, $atribut + ['status' => 'status']);

        if ($request->hasFile('berkas')) {
            $file = $request->file('berkas');
            $name = Str::random(24).'.'.$file->extension();
            $file->move(public_path('storage/ppdb'), $name);
            $data['berkas'] = 'storage/ppdb/'.$name;
        }

        $data['status'] = $data['status'] ?? 'menunggu';
        $pendaftar = PpdbPendaftar::daftar($data + ['tahun_ajaran' => Ppdb::status()['tahun_ajaran']]);

        return response()->json([
            'message' => 'Pendaftar '.$pendaftar->nama.' ditambahkan dengan nomor '.$pendaftar->no_pendaftaran.'.',
            'redirect' => route('admin/ppdb.show', $pendaftar),
        ], 201);
    }

    public function show(PpdbPendaftar $ppdb): View
    {
        return view('ppdb.show', ['p' => $ppdb]);
    }

    /** Layar "Jadikan Siswa": isian yang perlu dilengkapi dan pratinjau yang akan dibuat (mengikuti isian pada alamat). */
    public function jadikanSiswa(Request $request, PpdbPendaftar $ppdb): View
    {
        $input = $request->only('agama', 'nisn', 'rombel_id') + ['buat_ortu' => ! $request->has('agama') || $request->boolean('buat_ortu')];

        return view('ppdb.jadikan-siswa', [
            'p' => $ppdb, 'input' => $input, 'r' => PpdbKonversi::rencana($ppdb, $input),
            'kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas']), 'agama' => PpdbPendaftar::AGAMA,
        ]);
    }

    public function simpanSiswa(Request $request, PpdbPendaftar $ppdb): RedirectResponse
    {
        $data = $request->validate([
            'agama' => ['nullable', Rule::in(PpdbPendaftar::AGAMA)], 'nisn' => ['nullable', 'string', 'max:20'], 'rombel_id' => ['nullable', 'integer'],
        ], [], ['rombel_id' => 'rombel']);

        try {
            $siswa = PpdbKonversi::konversi($ppdb, $data + ['buat_ortu' => $request->boolean('buat_ortu')]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('admin/ppdb.show', $ppdb)->with('toast_success', "{$siswa->nama_siswa} sekarang terdaftar sebagai siswa (NISN {$siswa->nisn}). Password awal: ".\App\Http\Controllers\SiswaController::DEFAULT_PASSWORD.'.');
    }

    public function jadikanSiswaMassal(Request $request): RedirectResponse
    {
        $data = $request->validate(['rombel_id' => ['nullable', 'integer']], [], ['rombel_id' => 'rombel']);
        $hasil = PpdbKonversi::massal(isset($data['rombel_id']) ? (int) $data['rombel_id'] : null);

        return redirect()->route('admin/ppdb')->with('hasil_konversi', $hasil);
    }

    public function updateStatus(Request $request, PpdbPendaftar $ppdb): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PpdbPendaftar::STATUS))],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [], ['status' => 'status', 'catatan' => 'catatan']);

        $ppdb->update($data);

        // redirect = halaman detail dimuat ulang (badge, tombol Jadikan Siswa, dan catatan langsung terlihat); toast tetap tampil setelah dimuat
        return response()->json(['message' => 'Status pendaftar diubah menjadi '.$ppdb->status_label.'.', 'label' => $ppdb->status_label, 'redirect' => route('admin/ppdb.show', $ppdb)]);
    }

    public function destroy(PpdbPendaftar $ppdb): JsonResponse
    {
        if ($ppdb->berkas && File::isFile($path = public_path($ppdb->berkas))) {
            File::delete($path);
        }
        $ppdb->delete();

        return response()->json(['message' => 'Data pendaftar berhasil dihapus.']);
    }

    /** Unduh seluruh pendaftar (sesuai filter status) sebagai CSV. */
    public function export(Request $request): StreamedResponse
    {
        $query = PpdbPendaftar::query()->orderBy('id');
        if (in_array($status = $request->input('status'), array_keys(PpdbPendaftar::STATUS), true)) {
            $query->where('status', $status);
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['No Pendaftaran', 'Tahun Ajaran', 'Nama', 'JK', 'Tempat Lahir', 'Tanggal Lahir', 'NISN', 'Asal Sekolah', 'Alamat', 'Nama Orang Tua', 'Telepon', 'Email', 'Jalur', 'Peminatan', 'Nilai Rata-rata', 'Status', 'Catatan', 'Mendaftar']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, array_map([self::class, 'amanCsv'], [
                        $p->no_pendaftaran, $p->tahun_ajaran, $p->nama, $p->jenis_kelamin, $p->tempat_lahir, $p->tanggal_lahir->format('Y-m-d'), $p->nisn, $p->asal_sekolah,
                        $p->alamat, $p->nama_ortu, $p->telepon, $p->email, $p->jalur, $p->peminatan, $p->nilai_rata, $p->status_label, $p->catatan, $p->created_at?->format('Y-m-d H:i'),
                    ]));
                }
            });
            fclose($out);
        }, 'pendaftar-ppdb-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah injeksi rumus spreadsheet (=, +, -, @ di awal sel). */
    public static function amanCsv(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
