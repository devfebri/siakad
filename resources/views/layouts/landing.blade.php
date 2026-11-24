<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ $produk }}</title>
    <meta name="description" content="@yield('description', 'Aplikasi sekolah lengkap: data siswa, absensi, nilai, website, dan PPDB. Mulai tanpa pasang aplikasi atau beli server.')">
    @include('layouts._tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-surface" x-data="{ menu: false }">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('produk') }}" class="flex items-center gap-3 no-underline">
                <span class="grid size-10 place-items-center rounded-xl bg-primary text-xl text-accent"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="font-display text-lg font-extrabold text-primary">{{ $produk }}</span>
            </a>
            <nav class="mx-auto hidden items-center gap-1 text-sm font-semibold md:flex" aria-label="Navigasi">
                <a href="{{ route('produk') }}#fitur" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-primary-soft hover:text-primary">Fitur</a>
                <a href="{{ route('produk') }}#harga" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-primary-soft hover:text-primary">Harga</a>
                <a href="{{ route('produk') }}#faq" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-primary-soft hover:text-primary">Pertanyaan</a>
                <a href="{{ route('produk') }}#kontak" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-primary-soft hover:text-primary">Kontak</a>
            </nav>
            <a href="{{ route('daftar') }}" class="btn btn-accent ml-auto md:ml-0">Daftar Sekolah</a>
        </div>
    </header>

    <main class="flex-1">@yield('content')</main>

    <footer class="border-t border-slate-200 bg-white py-8 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} {{ $produk }}. Semua hak dilindungi.
    </footer>

    @if (Session::has('alert.config'))
        <script>window.__flash = {!! Session::pull('alert.config') !!};</script>
    @endif
</body>
</html>
