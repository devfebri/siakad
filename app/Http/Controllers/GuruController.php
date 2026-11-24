<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Guru;
use App\Mapel;
use App\Support\Avatar;
use App\Support\Dates;
use App\Support\Gender;
use App\Support\DataTableResponse;
use App\Jadwal;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuruController extends Controller
{
    /** Password awal akun guru baru (guru diminta menggantinya lewat halaman profil). */
    public const DEFAULT_PASSWORD = 'guru123';

    public function index(): View
    {
        return view('guru.index', ['mapel' => Mapel::orderBy('mapel_nama')->get(['id', 'mapel_nama'])]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Guru::query()->with(['user:id,email,username', 'mapel:id,mapel_nama']),
            searchable: ['nama_guru', 'nip_guru', 'no_hp'],
            orderable: ['nama_guru', 'nip_guru', 'created_at'],
            map: fn (Guru $guru) => [
                'id' => $guru->id,
                'nip_guru' => $guru->nip_guru,
                'nama_guru' => $guru->nama_guru,
                'username' => $guru->user?->username,
                'email' => $guru->user?->email,
                'mapel_id' => $guru->mapel_id,
                'mapel' => $guru->mapel?->mapel_nama ?? '-',
                'jenis_kelamin' => Gender::label($guru->jenis_kelamin),
                'no_hp' => $guru->no_hp,
                'tanggal_lahir' => Dates::iso($guru->getRawOriginal('tanggal_lahir')),
                'avatar_url' => Avatar::url('guru', $guru->user?->email, $guru->avatar),
            ],
            searchRelations: ['user' => ['username', 'email'], 'mapel' => ['mapel_nama']],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'role' => 'guru',
                'name' => $data['nama_guru'],
                'username' => $data['username'] ?? User::usernameUnik($data['nip_guru']),
                'email' => $data['email'],
                'password' => self::DEFAULT_PASSWORD,
                'remember_token' => Str::random(60),
            ]);

            $guru = Guru::create(['user_id' => $user->id] + collect($data)->except('avatar', 'password', 'email', 'username')->all());

            if ($request->hasFile('avatar')) {
                $guru->update(['avatar' => Avatar::store($request->file('avatar'), 'guru', $user->email)]);
            }
        });

        return response()->json(['message' => 'Guru berhasil ditambahkan. Password awal: '.self::DEFAULT_PASSWORD], 201);
    }

    public function update(Request $request, Guru $guru): JsonResponse
    {
        $data = $this->validated($request, $guru);
        $user = $guru->user;

        DB::transaction(function () use ($request, $data, $guru, $user) {
            if ($user) {
                $user->name = $data['nama_guru'];
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
                Avatar::delete('guru', $user->email, $guru->avatar);
                $attributes['avatar'] = Avatar::store($request->file('avatar'), 'guru', $user->email);
            }

            $guru->update($attributes);
        });

        return response()->json(['message' => 'Data guru berhasil diperbarui.']);
    }

    public function destroy(Guru $guru): JsonResponse
    {
        $user = $guru->user;

        if ($user && ($user->punyaPeran('admin') || $user->id === auth()->id())) {
            return response()->json(['message' => 'Akun admin atau akun Anda sendiri tidak dapat dihapus dari sini.'], 409);
        }

        $jadwal = Jadwal::where('guru_id', $guru->id)->count();
        if ($jadwal) {
            return response()->json(['message' => "Tidak dapat dihapus: guru ini masih tercatat mengajar di {$jadwal} jadwal pelajaran. Ubah atau hapus jadwalnya dulu."], 409);
        }

        DB::transaction(function () use ($guru, $user) {
            if ($user) {
                Avatar::deleteAll('guru', $user->email);
            }
            $guru->delete();
            $user?->delete();
        });

        return response()->json(['message' => 'Guru berhasil dihapus.']);
    }

    private function validated(Request $request, ?Guru $guru = null): array
    {
        if ($request->filled('username')) {
            $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        }

        return $request->validate([
            // Username untuk login. Kosong saat tambah = memakai NIP.
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')->ignore($guru?->user_id)],
            // Email hanya diisi saat tambah (menjadi login); tidak bisa diubah lewat edit.
            ...($guru ? [] : ['email' => ['required', 'email', 'max:191', 'regex:/^[^\/\\\\]+$/', SekolahAktif::unik('users', 'email')]]),
            'nip_guru' => ['required', 'string', 'max:100', SekolahAktif::unik('guru', 'nip_guru')->ignore($guru?->id)],
            'nama_guru' => ['required', 'string', 'min:2', 'max:200'],
            'mapel_id' => ['required', 'integer', SekolahAktif::ada('mapel')],
            'jenis_kelamin' => ['required', Rule::in(['Laki-Laki', 'Perempuan'])],
            'no_hp' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.'], [
            'username' => 'username',
            'email' => 'email',
            'nip_guru' => 'NIP',
            'nama_guru' => 'nama guru',
            'mapel_id' => 'mata pelajaran',
            'jenis_kelamin' => 'jenis kelamin',
            'no_hp' => 'nomor HP',
            'tanggal_lahir' => 'tanggal lahir',
            'avatar' => 'foto',
        ]);
    }
}
