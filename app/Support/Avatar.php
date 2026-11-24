<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Foto profil guru/siswa. Lokasi sama dengan data lama: public/storage/{guru|siswa}/{email}/{nama-file},
 * jadi avatar yang sudah ada tetap tampil.
 */
class Avatar
{
    public static function store(UploadedFile $file, string $role, string $email): string
    {
        $name = Str::random(20).'.'.$file->extension();
        $file->move(self::dir($role, $email), $name);

        return $name;
    }

    public static function delete(string $role, string $email, ?string $name): void
    {
        if ($name && File::isFile($path = self::dir($role, $email).'/'.$name)) {
            File::delete($path);
        }
    }

    public static function deleteAll(string $role, string $email): void
    {
        File::deleteDirectory(self::dir($role, $email));
    }

    public static function url(string $role, ?string $email, ?string $name): string
    {
        return $name && $email
            ? asset("storage/$role/$email/$name")
            : asset('images/default.png');
    }

    private static function dir(string $role, string $email): string
    {
        return public_path("storage/$role/$email");
    }
}
