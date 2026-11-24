@php
    $menu = [
        ['Beranda', route('beranda'), ['/']],
        ['Profil', route('profil'), ['profil*']],
        ['Akademik', route('akademik'), ['akademik*']],
        ['Berita', route('berita'), ['berita*', 'pengumuman*', 'agenda*']],
        ['Galeri', route('galeri'), ['galeri*']],
        ['Unduhan', route('unduhan'), ['unduhan*']],
        ['PPDB', route('ppdb'), ['ppdb*']],
        ['Kontak', route('kontak'), ['kontak*']],
    ];

    $menu = array_values(array_filter($menu, fn ($m) => match ($m[0]) {
        'Berita', 'Galeri' => \App\Support\AksesSekolah::modul('konten_web'),
        'Unduhan' => \App\Support\AksesSekolah::modul('unduhan'),
        'PPDB' => \App\Support\AksesSekolah::modul('ppdb'),
        default => true,
    }));
@endphp
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('beranda') }}" class="flex min-w-0 items-center gap-3 no-underline">
            <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary text-2xl text-accent shadow-sm">
                @if (config('school.logo'))
                    <img src="{{ asset(config('school.logo')) }}" alt="" class="size-full bg-white object-contain">
                @else
                    <i class="bi bi-mortarboard-fill"></i>
                @endif
            </span>
            <span class="min-w-0 leading-tight">
                <span class="block truncate font-display text-[0.95rem] font-extrabold text-primary">{{ config('school.name') }}</span>
                <span class="hidden truncate text-xs text-slate-500 sm:block">{{ config('school.tagline') }}</span>
            </span>
        </a>

        <nav class="mx-auto hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
            @foreach ($menu as [$label, $url, $match])
                @php $aktif = request()->is(...$match); @endphp
                <a href="{{ $url }}" @if ($aktif) aria-current="page" @endif
                   @class([
                       'relative rounded-lg px-3.5 py-2 text-sm font-semibold no-underline transition',
                       'text-primary after:absolute after:inset-x-3.5 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-accent' => $aktif,
                       'text-slate-600 hover:bg-primary-soft hover:text-primary' => ! $aktif,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            @auth
                <a href="{{ auth()->user()->dashboardUrl() }}" class="btn btn-outline btn-sm hidden sm:inline-flex"><i class="bi bi-speedometer2"></i> Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-slate-600 no-underline hover:text-primary sm:block">Masuk</a>
            @endauth
            @if (\App\Support\AksesSekolah::modul('konsultasi_online'))<button type="button" data-chat-open class="btn btn-soft btn-sm hidden xl:inline-flex"><i class="bi bi-chat-dots"></i> Konsultasi</button>@endif
            @if (\App\Support\AksesSekolah::modul('ppdb'))<a href="{{ route('ppdb') }}" class="btn btn-accent btn-sm sm:text-sm"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>@endif
            <button type="button" class="btn btn-outline btn-sm lg:hidden" @click="menu = !menu" :aria-expanded="menu" aria-controls="menu-mobile" aria-label="Menu">
                <i class="bi text-lg" :class="menu ? 'bi-x-lg' : 'bi-list'"></i>
            </button>
        </div>
    </div>

    <nav id="menu-mobile" x-cloak x-show="menu" x-transition class="border-t border-slate-100 bg-white px-4 py-3 lg:hidden" aria-label="Navigasi seluler">
        @foreach ($menu as [$label, $url, $match])
            <a href="{{ $url }}" @class(['block rounded-lg px-3 py-2.5 text-sm font-semibold no-underline', 'bg-primary-soft text-primary' => request()->is(...$match), 'text-slate-700' => ! request()->is(...$match)])>{{ $label }}</a>
        @endforeach
        @if (\App\Support\AksesSekolah::modul('konsultasi_online'))<button type="button" data-chat-open class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-700" @click="menu = false"><i class="bi bi-chat-dots"></i> Konsultasi Online</button>@endif
        <a href="{{ auth()->check() ? auth()->user()->dashboardUrl() : route('login') }}" class="mt-1 block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-500 no-underline">{{ auth()->check() ? 'Dashboard' : 'Masuk' }}</a>
    </nav>
</header>
