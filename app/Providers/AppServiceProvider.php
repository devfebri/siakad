<?php

namespace App\Providers;

use App\View\Composers\AdminLayout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Konteks sekolah (tenant) aktif: satu instance per request/proses.
        $this->app->singleton(\App\Support\SekolahAktif::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paginasi publik memakai tampilan Tailwind bawaan Laravel.
        Paginator::defaultView('pagination::tailwind');
        View::composer('layouts.admin', AdminLayout::class);

        // Perubahan data induk dikabarkan ke E-Learning (webhook) bila sekolah mengaktifkan integrasi.
        foreach (\App\Support\Api\Sumber::semua() as $d) {
            $d['model']::observe(\App\Observers\DataIndukObserver::class);
        }
        \App\Semester::observe(\App\Observers\DataIndukObserver::class);

        // Notifikasi WhatsApp: kejadian data (alpa, hasil PPDB) dan batas laju kirim per sekolah.
        \App\Absensi::observe(\App\Observers\WaObserver::class);
        \App\PpdbPendaftar::observe(\App\Observers\WaObserver::class);
        \Illuminate\Support\Facades\RateLimiter::for('wa', fn (object $job) => \Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, (int) ($job->batasPerMenit ?? 20)))->by('wa:'.($job->sekolahId ?? 0)));

        // SSO E-Learning (Passport): klien tepercaya milik sekolah, token akses singkat + refresh token.
        \Laravel\Passport\Passport::useClientModel(\App\Support\KlienOauth::class);
        \Laravel\Passport\Passport::tokensExpireIn(now()->addMinutes(15));
        \Laravel\Passport\Passport::refreshTokensExpireIn(now()->addDays(14));
        \Laravel\Passport\Passport::clientCredentialsTokensExpireIn(now()->addHour());
        \Laravel\Passport\Passport::authorizationView('sekolah.sso-otorisasi');

        /*
         * Route CRUD AJAX standar (lihat resources/js/crud.js):
         *   Route::ajaxCrud('admin/mapel', MapelController::class, 'mapel');
         * membuat   GET  /mapel          -> index    (admin/mapel)
         *           GET  /mapel/data     -> data     (admin/mapel.data)
         *           POST /mapel          -> store    (admin/mapel.store)
         *           PUT  /mapel/{mapel}  -> update   (admin/mapel.update)
         *           DELETE /mapel/{mapel}-> destroy  (admin/mapel.destroy)
         * Dengan $readOnly = true hanya index dan data (mis. guru yang hanya melihat daftar).
         * $except menghilangkan route tertentu, mis. ['update'] untuk galeri yang hanya tambah/hapus.
         * $pages = true menambah halaman penuh GET /create (name.create) dan GET /{param}/edit (name.edit).
         */
        Route::macro('ajaxCrud', function (string $name, string $controller, string $param, bool $readOnly = false, array $except = [], bool $pages = false) {
            $uri = '/'.Str::after($name, '/');

            $this->get($uri, [$controller, 'index'])->name($name);
            $this->get("$uri/data", [$controller, 'data'])->name("$name.data");

            if ($pages && ! $readOnly) {
                $this->get("$uri/create", [$controller, 'create'])->name("$name.create");
                $this->get("$uri/{{$param}}/edit", [$controller, 'edit'])->name("$name.edit");
            }

            if (! $readOnly) {
                in_array('store', $except) || $this->post($uri, [$controller, 'store'])->name("$name.store");
                in_array('update', $except) || $this->put("$uri/{{$param}}", [$controller, 'update'])->name("$name.update");
                in_array('destroy', $except) || $this->delete("$uri/{{$param}}", [$controller, 'destroy'])->name("$name.destroy");
            }
        });
    }
}
