<?php

namespace App\Http\Controllers;

use App\Support\Avatar;
use App\Support\Dates;
use App\Support\Gender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Profil milik pengguna yang sedang login (admin/guru memakai data guru, siswa memakai data siswa). */
class ProfileController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $profil = $this->profil();

        return view('profile.index', [
            'user' => $user,
            'profil' => $profil,
            'tanggalLahir' => $profil ? Dates::iso($profil->getRawOriginal('tanggal_lahir')) : '',
            'jenisKelamin' => $profil ? Gender::label($profil->jenis_kelamin) : '',
            'avatarUrl' => $profil ? Avatar::url($this->folder(), $user->email, $profil->avatar) : null,
            'extra' => $this->extraInfo($profil),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();
        $profil = $this->profil();
        $siswa = $user->panel === 'siswa';
        $namaKolom = $siswa ? 'nama_siswa' : 'nama_guru';

        $data = $request->validate([
            $namaKolom => ['required', 'string', 'min:2', 'max:'.($siswa ? 100 : 200)],
            'jenis_kelamin' => [$profil ? 'required' : 'nullable', Rule::in(['Laki-Laki', 'Perempuan'])],
            'tanggal_lahir' => [$profil ? 'required' : 'nullable', 'date', 'before:today'],
            'no_hp' => [$profil && ! $siswa ? 'required' : 'nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], [], [
            $namaKolom => 'nama',
            'jenis_kelamin' => 'jenis kelamin',
            'tanggal_lahir' => 'tanggal lahir',
            'no_hp' => 'nomor HP',
            'avatar' => 'foto',
        ]);

        $preview = null;

        DB::transaction(function () use ($request, $data, $user, $profil, $siswa, $namaKolom, &$preview) {
            $user->update(['name' => $data[$namaKolom]]);

            if (! $profil) {
                return;
            }

            $attributes = [$namaKolom => $data[$namaKolom], 'jenis_kelamin' => $data['jenis_kelamin'], 'tanggal_lahir' => $data['tanggal_lahir']];
            if (! $siswa) {
                $attributes['no_hp'] = $data['no_hp'];
            }

            if ($request->hasFile('avatar')) {
                Avatar::delete($this->folder(), $user->email, $profil->avatar);
                $attributes['avatar'] = Avatar::store($request->file('avatar'), $this->folder(), $user->email);
                $preview = Avatar::url($this->folder(), $user->email, $attributes['avatar']);
            }

            $profil->update($attributes);
        });

        return response()->json(['message' => 'Profil berhasil diperbarui.', 'preview' => $preview]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ], [], ['password_lama' => 'password lama', 'password' => 'password baru']);

        $user = auth()->user();

        if (! Hash::check($request->input('password_lama'), $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
                'errors' => ['password_lama' => ['Password lama tidak sesuai.']],
            ], 422);
        }

        $user->update(['password' => $request->input('password')]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    private function profil(): \App\Guru|\App\Siswa|null
    {
        $user = auth()->user();

        return $user->panel === 'siswa' ? $user->siswa : $user->guru;
    }

    private function folder(): string
    {
        return auth()->user()->panel === 'siswa' ? 'siswa' : 'guru';
    }

    /** Data yang hanya bisa diubah admin, ditampilkan sebagai keterangan. */
    private function extraInfo(\App\Guru|\App\Siswa|null $profil): array
    {
        if (! $profil) {
            return [];
        }

        return auth()->user()->panel === 'siswa'
            ? ['NISN' => $profil->nisn, 'Kelas' => $profil->kelas?->nama_kelas ?? '-', 'Agama' => $profil->agama]
            : ['NIP' => $profil->nip_guru, 'Mata Pelajaran' => $profil->mapel?->mapel_nama ?? '-'];
    }
}
