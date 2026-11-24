<?php

namespace App\Http\Controllers;

use App\Pengaturan;
use App\ProgramUnggulan;
use App\Setting;
use App\Support\Html;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Menu Pengaturan: identitas, kontak, beranda, PPDB (tabel site_settings),
 * program unggulan, testimoni, serta delapan halaman profil sekolah (config/profil-sekolah.php).
 */
class PengaturanController extends Controller
{
    public function index(): View
    {
        $sections = config('profil-sekolah');

        // Pastikan setiap halaman punya baris (instalasi baru belum punya), tanpa menimpa yang sudah ada.
        foreach ($sections as $slot => $section) {
            Pengaturan::firstOrCreate(['slot' => $slot], ['judul' => $section['label'], 'deskripsi' => '']);
        }

        return view('pengaturan.index', [
            'sections' => $sections,
            'rows' => Pengaturan::whereIn('slot', array_keys($sections))->get()->keyBy('slot'),
            'groups' => config('site-settings'),
            'values' => Setting::values(),
            'ikon' => ProgramUnggulan::IKON,
        ]);
    }

    /** Simpan satu grup pengaturan situs (identitas, kontak, beranda, PPDB). */
    public function updateSitus(Request $request, string $group): JsonResponse
    {
        $definition = config("site-settings.$group");
        abort_unless($definition, 404);

        $fields = $definition['fields'];
        $data = $request->validate(
            collect($fields)->mapWithKeys(fn ($f, $key) => [$key => $f['rules']])->all(),
            [],
            collect($fields)->mapWithKeys(fn ($f, $key) => [$key => strtolower($f['label'])])->all(),
        );

        $stored = Setting::values();
        $save = [];
        $preview = null;

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'image') {
                if (! $request->hasFile($key)) {
                    continue; // tidak diganti
                }
                $this->deleteImage($stored[$key] ?? null);
                $save[$key] = $this->storeImage($request->file($key));
                $preview ??= asset($save[$key]);

                continue;
            }

            $value = $data[$key] ?? null;
            $save[$key] = match ($field['type']) {
                'checkbox' => $request->boolean($key) ? '1' : '0',
                'datetime' => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null,
                default => is_string($value) ? trim($value) : $value,
            };
        }

        Setting::put($save);

        return response()->json(['message' => $definition['label'].' berhasil disimpan.', 'preview' => $preview]);
    }

    private function storeImage(UploadedFile $file): string
    {
        $name = Str::random(24).'.'.$file->extension();
        $file->move(public_path('storage/situs'), $name);

        return 'storage/situs/'.$name;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/situs/') && File::isFile($full = public_path($path))) {
            File::delete($full);
        }
    }

    // ---------------------------------------------------------------- halaman profil sekolah (8 bagian)

    public function update(Request $request, Pengaturan $pengaturan): JsonResponse
    {
        $section = config("profil-sekolah.{$pengaturan->slot}");
        abort_unless($section, 404);

        $rules = [
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
        ];
        if ($section['dir']) {
            $rules['gambar'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        }

        $data = $request->validate($rules, [], ['judul' => 'judul', 'deskripsi' => 'isi halaman', 'gambar' => 'gambar']);

        $attributes = ['judul' => $data['judul'], 'deskripsi' => Html::clean($data['deskripsi'] ?? '')];

        if ($section['dir'] && $request->hasFile('gambar')) {
            $dir = public_path('storage/pengaturan/'.$section['dir']);
            if ($pengaturan->gambar && File::isFile($old = $dir.'/'.$pengaturan->gambar)) {
                File::delete($old);
            }

            $file = $request->file('gambar');
            $name = Str::random(24).'.'.$file->extension();
            $file->move($dir, $name);
            $attributes['gambar'] = $name;
        }

        $pengaturan->update($attributes);

        return response()->json([
            'message' => $section['label'].' berhasil disimpan.',
            'preview' => $section['dir'] && $pengaturan->gambar
                ? asset('storage/pengaturan/'.$section['dir'].'/'.$pengaturan->gambar)
                : null,
        ]);
    }
}
