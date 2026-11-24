<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('school.short_name') }}</title>
    @if (config('school.logo'))
        <link rel="icon" href="{{ asset(config('school.logo')) }}">
    @endif
    @include('layouts._tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
    @if (session(\App\Http\Controllers\ImpersonasiController::SESI))
        <div class="fixed inset-x-0 top-0 z-[60] flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-1.5 text-sm font-semibold text-ink print:hidden">
            <span><i class="bi bi-person-badge"></i> Mode Super Admin: Anda login sebagai {{ auth()->user()->name }}. Semua tindakan tercatat.</span>
            <form method="POST" action="{{ route('impersonasi.keluar') }}">@csrf<button type="submit" class="rounded-lg bg-ink px-2.5 py-0.5 text-xs font-bold text-white">Kembali ke Platform</button></form>
        </div>
        <div class="h-8 print:hidden"></div>
    @endif
<?php $akses = auth()->user()->panel === 'admin' ? \App\Support\AksesSekolah::ambil() : null; ?>
@if ($akses && ! $akses['bebas'] && ($akses['baca_saja'] || $akses['status'] === 'tenggang' || ($akses['sisa_hari'] !== null && $akses['sisa_hari'] <= 7)))
    @php
        [$warnaBanner, $teksBanner] = match (true) {
            $akses['status'] === 'belum_ada' => ['bg-red-600 text-white', 'Sekolah belum memiliki langganan aktif. Situs dalam mode baca-saja: perubahan data ditolak.'],
            $akses['status'] === 'baca_saja' => ['bg-red-600 text-white', 'Masa tenggang langganan telah habis. Situs dalam mode baca-saja: data bisa dilihat, perubahan ditolak sampai langganan diperpanjang.'],
            $akses['status'] === 'tenggang' => ['bg-amber-500 text-ink', 'Langganan berakhir '.$akses['berakhir']->translatedFormat('d F Y').'. Masa tenggang sampai '.$akses['tenggang_sampai']->translatedFormat('d F Y').' — setelah itu situs menjadi baca-saja.'],
            default => ['bg-sky-600 text-white', 'Langganan berakhir '.$akses['berakhir']->translatedFormat('d F Y').' ('.($akses['sisa_hari'] <= 0 ? 'hari ini' : $akses['sisa_hari'].' hari lagi').').'],
        };
    @endphp
    <div class="{{ $warnaBanner }} print:hidden" role="alert">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-2 text-sm font-semibold lg:pl-72">
            <span><i class="bi bi-exclamation-triangle-fill"></i> {{ $teksBanner }}</span>
            <a href="{{ route('admin/langganan') }}" class="rounded-lg bg-black/20 px-2.5 py-0.5 text-xs font-bold text-inherit no-underline hover:bg-black/30">Langganan Saya</a>
        </div>
    </div>
@endif
    @include('layouts.admin._sidebar')

    <div class="lg:pl-72 print:pl-0">
        @include('layouts.admin._topbar')

        <main class="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            @yield('content')
        </main>

        <footer class="px-6 pb-8 text-center text-xs text-slate-400 print:hidden">
            &copy; {{ date('Y') }} {{ config('school.name') }} · {{ config('school.tagline') }}
        </footer>
    </div>

    @if (Session::has('alert.config'))
        <script>window.__flash = {!! Session::pull('alert.config') !!};</script>
    @endif
    @stack('scripts')
</body>
</html>
