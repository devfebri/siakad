<?php

use App\Http\Middleware\ApplySiteSettings;
use App\Http\Middleware\CekLangganan;
use App\Http\Middleware\IdentifikasiSekolah;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Http\Middleware\ToSweetAlert;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;
use VinkiusLabs\LaravelPageSpeed\Middleware\ElideAttributes;
use VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss;
use VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch;
use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            InlineCss::class,
            ElideAttributes::class,
            InsertDNSPrefetch::class,
            RemoveComments::class,
            CollapseWhitespace::class, // Note: This middleware invokes "RemoveComments::class" before it runs.
        ]);

        $middleware->web(append: [
            IdentifikasiSekolah::class,
            \App\Http\Middleware\CekSso::class,
            CekLangganan::class,
            \App\Http\Middleware\TolakAkunNonaktif::class,
            ApplySiteSettings::class,
            ToSweetAlert::class,
        ]);

        // Konteks sekolah harus terisi SEBELUM route model binding, supaya binding {siswa}, {kelas}, dst. sudah difilter sekolah.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: IdentifikasiSekolah::class);

        $middleware->alias([
            'checkRole' => CheckRole::class,
            'client' => \Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner::class,
            'klienApi' => \App\Http\Middleware\AuthKlienApi::class,
            'penggunaApi' => \App\Http\Middleware\AuthPenggunaApi::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform', 'platform/*') ? route('platform.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role.'/dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
