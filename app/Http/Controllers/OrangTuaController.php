<?php

namespace App\Http\Controllers;

use App\OrangTua;
use App\Siswa;
use App\Support\DataTableResponse;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin mengelola akun orang tua/wali murid dan menghubungkannya dengan siswa (modul Portal Orang Tua). */
class OrangTuaController extends Controller
{
    public const DEFAULT_PASSWORD = 'ortu123';

    public function index(): View
    {
        return view('orang-tua.index', ['siswa' => Siswa::aktif()->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            OrangTua::query()->with('user:id,username,email')->withCount('anak'),
            searchable: ['nama', 'no_hp'],
            orderable: ['nama', 'anak_count', 'created_at'],
            map: fn (OrangTua $o) => [
                'id' => $o->id,
                'nama' => $o->nama,
                'username' => $o->user?->username ?? '-',
                'no_hp' => $o->no_hp ?: '-',
                'pekerjaan' => $o->pekerjaan,
                'anak_count' => $o->anak_count,
            ],
            searchRelations: ['user' => ['username', 'email']],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalisasi($request);
        $data = $request->validate($this->aturan() + [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')],
            'email' => ['nullable', 'email', 'max:191', SekolahAktif::unik('users', 'email')],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'siswa_id' => ['nullable', 'integer', SekolahAktif::ada('siswa')],
            'hubungan' => ['nullable', Rule::in(array_keys(OrangTua::HUBUNGAN))],
        ], $this->pesan(), $this->atribut());

        DB::transaction(function () use ($data) {
            $user = User::create([
                'role' => 'orang_tua',
                'name' => $data['nama'],
                'username' => $data['username'],
                'email' => $data['email'] ?? $data['username'].'@ortu.invalid', // login memakai username; email boleh kosong
                'password' => $data['password'] ?? self::DEFAULT_PASSWORD,
                'remember_token' => Str::random(60),
            ]);

            $ortu = OrangTua::create(['user_id' => $user->id] + collect($data)->only('nama', 'no_hp', 'pekerjaan', 'alamat')->all());

            if (! empty($data['siswa_id'])) {
                $ortu->hubungkan(Siswa::findOrFail($data['siswa_id']), $data['hubungan'] ?? 'wali');
            }
        });

        return response()->json(['message' => 'Orang tua berhasil ditambahkan. Password awal: '.(($data['password'] ?? null) ? 'sesuai isian' : self::DEFAULT_PASSWORD)], 201);
    }

    public function update(Request $request, OrangTua $orangTua): JsonResponse
    {
        $this->normalisasi($request);
        $data = $request->validate($this->aturan() + [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')->ignore($orangTua->user_id)],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], $this->pesan(), $this->atribut());

        DB::transaction(function () use ($data, $orangTua) {
            $orangTua->update(collect($data)->only('nama', 'no_hp', 'pekerjaan', 'alamat')->all());

            $user = $orangTua->user;
            $user->name = $data['nama'];
            $user->username = $data['username'];
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
        });

        return response()->json(['message' => 'Data orang tua berhasil diperbarui.']);
    }

    public function destroy(OrangTua $orangTua): JsonResponse
    {
        DB::transaction(function () use ($orangTua) {
            $orangTua->anak()->detach();
            $user = $orangTua->user;
            $orangTua->delete();
            $user?->peran()->detach();
            $user?->delete();
        });

        return response()->json(['message' => 'Akun orang tua dihapus.']);
    }

    /** Detail orang tua: daftar anak dan penghubungan dengan siswa. */
    public function show(OrangTua $orangTua): View
    {
        $orangTua->load('user:id,username', 'anak.kelas:id,nama_kelas');

        return view('orang-tua.show', [
            'ortu' => $orangTua,
            'calon' => Siswa::aktif()->whereNotIn('id', $orangTua->anak->pluck('id'))->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa']),
        ]);
    }

    public function tambahAnak(Request $request, OrangTua $orangTua): JsonResponse
    {
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', SekolahAktif::ada('siswa')],
            'hubungan' => ['required', Rule::in(array_keys(OrangTua::HUBUNGAN))],
        ], [], ['siswa_id' => 'siswa']);

        $orangTua->hubungkan(Siswa::findOrFail($data['siswa_id']), $data['hubungan']);

        return response()->json(['message' => 'Siswa dihubungkan.', 'redirect' => route('admin/orang-tua.show', $orangTua)]);
    }

    public function lepasAnak(OrangTua $orangTua, Siswa $siswa): JsonResponse
    {
        $orangTua->anak()->detach($siswa->id);

        return response()->json(['message' => 'Hubungan dengan '.$siswa->nama_siswa.' dilepas.', 'redirect' => route('admin/orang-tua.show', $orangTua)]);
    }

    private function normalisasi(Request $request): void
    {
        foreach (['username', 'email'] as $k) {
            if ($request->filled($k)) {
                $request->merge([$k => mb_strtolower(trim((string) $request->input($k)))]);
            }
        }
    }

    private function aturan(): array
    {
        return [
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function pesan(): array
    {
        return ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.'];
    }

    private function atribut(): array
    {
        return ['nama' => 'nama', 'no_hp' => 'nomor HP', 'siswa_id' => 'siswa'];
    }
}
