@php
    $role = auth()->user()->panel;
    // item bertanda 'peran' hanya untuk akun yang punya peran itu (mis. menu Kelas Wali)
    $items = \App\Support\AksesSekolah::saringMenu(array_values(array_filter(config("admin-menu.$role", []), fn ($i) => empty($i['peran']) || auth()->user()->punyaPeran($i['peran']))));
    $logo = config('school.logo');
@endphp

{{-- Latar gelap saat menu terbuka di layar kecil --}}
<div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

<aside id="appSidebar" aria-label="Menu utama"
       class="print:hidden fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-gradient-to-b from-primary to-primary-deep text-white shadow-lift transition-transform duration-200 lg:translate-x-0"
       :class="{ 'translate-x-0': sidebar }">
    <a href="{{ route($role . '/dashboard') }}" class="flex items-center gap-3 px-6 py-5 text-white no-underline">
        <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-accent text-2xl text-ink shadow-md">
            @if ($logo)
                <img src="{{ asset($logo) }}" alt="" class="size-full bg-white object-contain">
            @else
                <i class="bi bi-mortarboard-fill"></i>
            @endif
        </span>
        <span class="min-w-0">
            <span class="block truncate font-display text-[0.95rem] leading-tight font-extrabold">{{ config('school.name') }}</span>
            <span class="block truncate text-xs text-white/60">{{ config('school.tagline') }}</span>
        </span>
    </a>

    <nav class="flex-1 overflow-y-auto px-4 pb-4" aria-label="Navigasi">
        @foreach ($items as $item)
            @if (isset($item['header']))
                <p class="mt-5 mb-2 px-3 text-[0.68rem] font-bold tracking-widest text-white/45 uppercase">{{ $item['header'] }}</p>
            @else
                @php $active = request()->is(...$item['match']); @endphp
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'group mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
                       'bg-accent text-ink shadow-md' => $active,
                       'text-white/75 hover:bg-white/10 hover:text-white' => ! $active,
                   ])>
                    <i @class(['bi bi-'.$item['icon'], 'w-5 text-center text-lg', 'text-ink' => $active, 'text-accent group-hover:text-accent' => ! $active])></i>
                    <span class="truncate">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']) && ($n = ($navBadges[$item['badge']] ?? 0)) > 0)
                        <span class="ml-auto rounded-full bg-accent px-2 py-0.5 text-[0.68rem] font-bold text-ink">{{ $n }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    <div class="mx-4 mb-4 flex items-center gap-3 rounded-2xl bg-white/10 p-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent font-display font-bold text-ink">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
            <span class="block text-xs text-white/60 capitalize">{{ \App\User::PANEL_NAMA[$role] ?? $role }}</span>
        </span>
    </div>
</aside>
