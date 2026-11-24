<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Support\DataTableResponse;
use App\Peran;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manajemen akun pengguna. Akun guru dan siswa dibuat dari menu Data Guru/Data Siswa;
 * di sini admin membuat akun admin, mengubah nama/username/email, mengatur ulang password, dan menghapus akun yang tidak terkait data lain.
 */
class PenggunaController extends Controller
{
    public function index(): View
    {
        return view('pengguna.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = User::query()->with(['peran' => fn ($q) => $q->select('peran.id', 'kode', 'nama')->orderBy('peran.id')]);
        if (in_array($role = $request->input('role'), Peran::DAPAT_DIBERIKAN, true)) {
            $query->denganPeran($role);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: ['name', 'username', 'email'],
            orderable: ['name', 'username', 'email', 'created_at'],
            map: fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'role' => ($u->peran->pluck('nama')->implode(', ') ?: 'Tanpa peran').'|'.(Peran::WARNA[$u->peran->first()?->kode] ?? 'muted'),
                'peran' => $u->peran->pluck('kode')->values(),
                'bergabung' => $u->created_at?->translatedFormat('d M Y') ?? '-',
                'saya' => $u->id === auth()->id() ? 1 : 0,
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')],
            'email' => ['required', 'email', 'max:191', SekolahAktif::unik('users', 'email')],
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ], ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.'], ['name' => 'nama', 'username' => 'username', 'email' => 'email', 'password' => 'password']);

        User::create([
            'role' => 'admin',
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ]);

        return response()->json(['message' => 'Akun admin berhasil dibuat.'], 201);
    }

    public function update(Request $request, User $pengguna): JsonResponse
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', SekolahAktif::unik('users', 'username')->ignore($pengguna->id)],
            'email' => ['required', 'email', 'max:191', SekolahAktif::unik('users', 'email')->ignore($pengguna->id)],
        ], ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.'], ['name' => 'nama', 'username' => 'username', 'email' => 'email']);

        $pengguna->update($data);

        return response()->json(['message' => 'Data pengguna berhasil diperbarui.']);
    }

    /** Atur ulang password. Kosong = password awal untuk guru/siswa (admin wajib mengisi). */
    public function resetPassword(Request $request, User $pengguna): JsonResponse
    {
        // Akun admin wajib memilih password sendiri; guru/siswa boleh dikembalikan ke password awal.
        $default = match (true) {
            $pengguna->punyaPeran(Peran::ADMIN, Peran::SUPER_ADMIN) => null,
            $pengguna->punyaPeran(Peran::GURU, Peran::WALI_KELAS) => GuruController::DEFAULT_PASSWORD,
            $pengguna->punyaPeran(Peran::SISWA) => SiswaController::DEFAULT_PASSWORD,
            $pengguna->punyaPeran(Peran::ORANG_TUA) => OrangTuaController::DEFAULT_PASSWORD,
            default => null,
        };

        $data = $request->validate([
            'password' => [$default ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ], [], ['password' => 'password baru']);

        $baru = $data['password'] ?? $default;
        $pengguna->update(['password' => $baru]);

        return response()->json(['message' => empty($data['password'])
            ? "Password {$pengguna->name} dikembalikan ke password awal ({$default})."
            : "Password {$pengguna->name} berhasil diubah."]);
    }

    /** Ubah peran akun (satu akun boleh punya banyak peran). Peran di luar daftar yang dapat diberikan (mis. super_admin) tidak tersentuh. */
    public function updatePeran(Request $request, User $pengguna): JsonResponse
    {
        $data = $request->validate([
            'peran' => ['required', 'array', 'min:1'],
            'peran.*' => ['string', Rule::in(Peran::DAPAT_DIBERIKAN)],
        ], ['peran.required' => 'Pilih minimal satu peran.', 'peran.min' => 'Pilih minimal satu peran.'], ['peran' => 'peran']);

        $baru = array_values(array_unique($data['peran']));
        $dicabut = array_diff(array_intersect($pengguna->kodePeran(), Peran::DAPAT_DIBERIKAN), $baru);

        if (in_array(Peran::ADMIN, $dicabut, true)) {
            if ($pengguna->id === auth()->id()) {
                return response()->json(['message' => 'Anda tidak dapat mencabut peran admin dari akun Anda sendiri.'], 409);
            }
            if (User::denganPeran(Peran::ADMIN)->count() <= 1) {
                return response()->json(['message' => 'Tidak dapat mencabut peran admin terakhir.'], 409);
            }
        }
        if (in_array(Peran::GURU, $dicabut, true) && $pengguna->guru()->exists()) {
            return response()->json(['message' => 'Peran guru tidak dapat dicabut karena akun ini terhubung dengan data guru.'], 409);
        }
        if (in_array(Peran::SISWA, $dicabut, true) && $pengguna->siswa()->exists()) {
            return response()->json(['message' => 'Peran siswa tidak dapat dicabut karena akun ini terhubung dengan data siswa.'], 409);
        }

        if (in_array(Peran::ORANG_TUA, $dicabut, true) && $pengguna->orangTua()->exists()) {
            return response()->json(['message' => 'Peran orang tua tidak dapat dicabut karena akun ini terhubung dengan data orang tua.'], 409);
        }

        $dipertahankan = array_diff($pengguna->kodePeran(), Peran::DAPAT_DIBERIKAN);
        $pengguna->setPeran(array_merge($dipertahankan, $baru));

        return response()->json(['message' => "Peran {$pengguna->name} diperbarui."]);
    }

    public function destroy(User $pengguna): JsonResponse
    {
        if ($pengguna->id === auth()->id()) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 409);
        }

        if ($pengguna->guru()->exists() || $pengguna->siswa()->exists()) {
            return response()->json(['message' => 'Akun ini terhubung dengan data guru/siswa. Hapus lewat menu Data Guru atau Data Siswa.'], 409);
        }

        if ($pengguna->punyaPeran(Peran::ADMIN) && User::denganPeran(Peran::ADMIN)->count() <= 1) {
            return response()->json(['message' => 'Tidak dapat menghapus admin terakhir.'], 409);
        }

        $pengguna->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
