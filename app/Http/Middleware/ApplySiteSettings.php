<?php

namespace App\Http\Middleware;

use App\Setting;
use App\Support\SekolahAktif;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menimpa config('school.*') dengan pengaturan yang tersimpan di database (menu Pengaturan). */
class ApplySiteSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        self::apply();

        return $next($request);
    }

    public static function apply(): void
    {
        // Tanpa konteks sekolah (panel platform/console) tidak ada pengaturan situs yang diterapkan, supaya tidak tercampur antar sekolah.
        if (SekolahAktif::instance()->id() === null) {
            return;
        }

        $stored = Setting::values();
        if (! $stored) {
            return;
        }

        foreach (config('site-settings') as $group) {
            foreach ($group['fields'] as $key => $field) {
                $value = $stored[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                config([$field['config'] ?? "school.$key" => match ($field['type']) {
                    'checkbox' => (bool) $value,
                    'number' => (int) $value,
                    default => $value,
                }]);
            }
        }
    }
}
