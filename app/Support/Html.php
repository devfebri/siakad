<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Membersihkan HTML dari editor teks sebelum disimpan. Halaman publik menampilkan isi ini apa adanya
 * ({!! !!}), jadi skrip, atribut onclick, dan sejenisnya harus dibuang di sini.
 */
class Html
{
    private const ALLOWED = 'p,br,strong,b,em,i,u,s,h2,h3,h4,ul,ol,li,blockquote,a[href|target|rel],hr,img[src|alt]';

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        static $purifier = null;

        if (! $purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', self::cachePath());
            $config->set('HTML.Allowed', self::ALLOWED);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.Nofollow', true);
            $config->set('AutoFormat.RemoveEmpty', true);
            $purifier = new HTMLPurifier($config);
        }

        return trim($purifier->purify($html));
    }

    private static function cachePath(): string
    {
        $path = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }
}
