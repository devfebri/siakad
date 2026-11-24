<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · {{ config('school.short_name') }}@else{{ config('school.name') }} · {{ config('school.tagline') }}@endif</title>
    <meta name="description" content="@yield('description', config('school.hero_deskripsi'))">
    <meta property="og:title" content="@yield('title', config('school.name'))">
    <meta property="og:description" content="@yield('description', config('school.hero_deskripsi'))">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    @if (config('school.logo'))
        <link rel="icon" href="{{ asset(config('school.logo')) }}">
    @endif
    @include('layouts._tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col bg-surface" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Lewati ke konten</a>

    @include(config('school.tema_depan') === 'portal' ? 'layouts.public._navbar-portal' : 'layouts.public._navbar')

    <main id="konten" class="flex-1">
        @yield('content')
    </main>

    @include(config('school.tema_depan') === 'portal' ? 'layouts.public._footer-portal' : 'layouts.public._footer')

    @if (\App\Support\AksesSekolah::modul('konsultasi_online'))
        @include('chat._widget')
    @endif

    @if (Session::has('alert.config'))
        <script>window.__flash = {!! Session::pull('alert.config') !!};</script>
    @endif
    @stack('scripts')
</body>
</html>
