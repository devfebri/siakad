{{-- Tema "portal": bilah info atas + navbar putih. Tema lama ada di _navbar.blade.php. --}}
@php
    $menu = [
        ['Beranda', route('beranda'), ['/']],
        ['Profil', route('profil'), ['profil*']],
        ['Akademik', route('akademik'), ['akademik*']],
        ['Berita', route('berita'), ['berita*', 'pengumuman*', 'agenda*']],
        ['Galeri', route('galeri'), ['galeri*']],
        ['Unduhan', route('unduhan'), ['unduhan*']],
        ['Kontak', route('kontak'), ['kontak*']],
    ];

    $menu = array_values(array_filter($menu, fn ($m) => match ($m[0]) {
        'Berita', 'Galeri' => \App\Support\AksesSekolah::modul('konten_web'),
        'Unduhan' => \App\Support\AksesSekolah::modul('unduhan'),
        default => true,
    }));

    $sosmed = array_filter([
        'facebook' => config('school.facebook'),
        'instagram' => config('school.instagram'),
        'youtube' => config('school.youtube'),
        'tiktok' => config('school.tiktok'),
    ]);
@endphp
<header class="sticky top-0 z-40 shadow-sm">
    <div class="hidden bg-primary-deep text-xs text-white/80 md:block">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                @if (config('school.telepon'))<span><i class="bi bi-telephone-fill mr-1 text-accent"></i>{{ config('school.telepon') }}</span>@endif
                @if (config('school.email'))<span><i class="bi bi-envelope-fill mr-1 text-accent"></i>{{ config('school.email') }}</span>@endif
                @if (config('school.jam_layanan'))<span><i class="bi bi-clock-fill mr-1 text-accent"></i>{{ config('school.jam_layanan') }}</span>@endif
            </div>
            <div class="flex items-center gap-4">
                @foreach ($sosmed as $nama => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($nama) }}" class="text-white/80 no-underline hover:text-accent"><i class="bi bi-{{ $nama }}"></i></a>
                @endforeach
                @auth
                    <a href="{{ auth()->user()->dashboardUrl() }}" class="font-semibold text-white no-underline hover:text-accent"><i class="bi bi-speedometer2"></i> Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="font-semibold text-white no-underline hover:text-accent"><i class="bi bi-box-arrow-in-right"></i> Masuk</a>
                @endauth
            </div>
        </div>
    </div>

    <div class="bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('beranda') }}" class="flex min-w-0 items-center gap-3 no-underline">
                <span class="grid size-12 shrink-0 place-items-center overflow-hidden text-3xl text-primary">
                    @if (config('school.logo'))
                        <img src="{{ asset(config('school.logo')) }}" alt="" class="size-full object-contain">
                    @else
                        <i class="bi bi-mortarboard-fill"></i>
                    @endif
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate font-display text-base font-extrabold text-primary uppercase sm:text-lg">{{ config('school.name') }}</span>
                    <span class="hidden truncate text-xs text-slate-500 sm:block">{{ config('school.tagline') }}</span>
                </span>
            </a>

            <nav class="ml-auto hidden items-center lg:flex" aria-label="Navigasi utama">
                @foreach ($menu as [$label, $url, $match])
                    @php $aktif = request()->is(...$match); @endphp
                    <a href="{{ $url }}" @if ($aktif) aria-current="page" @endif
                       @class([
                           'border-b-2 px-3.5 py-2 text-sm font-semibold no-underline transition',
                           'border-accent text-primary' => $aktif,
                           'border-transparent text-slate-700 hover:border-accent/60 hover:text-primary' => ! $aktif,
                       ])>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-2 lg:ml-3">
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
    </div>
</header>
