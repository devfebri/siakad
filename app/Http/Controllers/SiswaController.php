<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Kelas;
use App\Siswa;
use App\Support\Avatar;
use App\Support\Dates;
use App\Support\Gender;
use App\Support\DataTableResponse;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    /** Password awal akun siswa baru (siswa diminta menggantinya lewat halaman profil). */
    public const DEFAULT_PASSWORD = 'siswa123';

    public function index(): View
    {
        return view('siswa.index', ['kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas'])]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Siswa::query()->aktif()->with(['user:id,email,username', 'kelas:id,nama_kelas']),
            searchable: ['nama_siswa', 'nisn'],
            orderable: ['nama_siswa', 'nisn', 'created_at'],
            map: fn (Siswa $siswa) => [
                'id' => $siswa->id,
                'nisn' => $siswa->nisn,
                'nama_siswa' => $siswa->nama_siswa,
                'username' => $siswa->user?->username,
                'email' => $siswa->user?->email,
                'kelas_id' => $siswa->kelas_id,
                'kelas' => $siswa->kelas?->nama_kelas ?? '-',
                'agama' => $siswa->agama,
                'jenis_kelamin' => Gender::label($siswa->jenis_kelamin),
                'tanggal_lahir' => Dates::iso($siswa->getRawOriginal('tanggal_lahir')),
                'avatar_url' => Avatar::url('siswa', $siswa->user?->email, $siswa->avatar),
            ],
            searchRelations: ['user' => ['username', 'email'], 'kelas' => ['nama_kelas']],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'role' => 'siswa',
                'name' => $data['nama_siswa'],
                'username' => $data['username'] ?? User::usernameUnik($data['nisn']),
                'email' => $data['email'],
                'password' => self::DEFAULT_PASSWORD,
                'remember_token' => Str::random(60),
            ]);

            $siswa = Siswa::create(['user_id' => $user->id] + collect($data)->except('avatar', 'password', 'email', 'username')->all());

            if ($request->hasFile('avatar')) {
                $siswa->update(['avatar' => Avatar::store($request->file('avatar'), 'siswa', $user->email)]);
            }
        });

        return response()->json(['message' => 'Siswa berhasil ditambahkan. Password awal: '.self::DEFAULT_PASSWORD], 201);
    }

    public function update(Request $request, Siswa $siswa): JsonResponse
    {
        $data = $this->validated($request, $siswa);
        $user = $siswa->user;

        DB::transaction(function () use ($request, $data, $siswa, $user) {
            if ($user) {
                $user->name = $data['nama_siswa'];
                if (! empty($data['username'])) {
                    $user->username = $data['username'];
                }
                if (! empty($data['password'])) {
                    $user->password = $data['password'];
                }
                $user->save();
            }

            $attributes = collect($data)->except('avatar', 'password', 'email', 'username')->all();
            if ($request->hasFile('avatar') && $user) {
                Avatar::delete('siswa', $user->email, $siswa->avatar);
                $attributes['avatar'] = Avatar::store($request->file('avatar'), 'siswa', $user->email);
            }

            $siswa->update($attributes);
        });

        return response()->json(['message' => 'Data siswa berhasil diperbarui.']);
    }

    public function destroy(Siswa $siswa): JsonResponse
    {

        $user = $siswa->user;

        DB::transaction(function () use ($siswa, $user) {
            if ($user) {
                Avatar::deleteAll('siswa', $user->email);
            }
            $siswa->delete();
            $user?->delete();
        });

        return response()->json(['message' => 'Siswa berhasil dihapus.']);
    }

    private function validated(Request $request, ?Siswa $siswa = null): array
    {
        if ($request->filled('username')) {
            $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        }

        return $request->validate([
            // Username untuk login. Kosong saat tambah = memakai NISN.
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')->ignore($siswa?->user_id)],
            // Email hanya diisi saat tambah (menjadi login); tidak bisa diubah lewat edit.
            ...($siswa ? [] : ['email' => ['required', 'email', 'max:191', 'regex:/^[^\/\\\\]+$/', SekolahAktif::unik('users', 'email')]]),
            'nisn' => ['required', 'string', 'min:5', 'max:100', SekolahAktif::unik('siswa', 'nisn')->ignore($siswa?->id)],
            'nama_siswa' => ['required', 'string', 'min:2', 'max:100'],
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'agama' => ['required', Rule::in(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'])],
            'jenis_kelamin' => ['required', Rule::in(['Laki-Laki', 'Perempuan'])],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.'], [
            'username' => 'username',
            'nisn' => 'NISN',
            'nama_siswa' => 'nama siswa',
            'kelas_id' => 'kelas',
            'jenis_kelamin' => 'jenis kelamin',
            'tanggal_lahir' => 'tanggal lahir',
            'avatar' => 'foto',
        ]);
    }
}
