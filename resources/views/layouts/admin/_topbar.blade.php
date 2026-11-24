@php
    $role = auth()->user()->panel;
    $namaDepan = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
@endphp
<header class="print:hidden sticky top-0 z-30 border-b border-slate-200/70 bg-surface/85 backdrop-blur">
    <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <button type="button" class="btn btn-outline btn-sm lg:hidden" @click="sidebar = true" aria-label="Buka menu" aria-controls="appSidebar">
            <i class="bi bi-list text-lg"></i>
        </button>

        {{-- Sapaan + tahun ajaran --}}
        <div class="hidden min-w-0 sm:block">
            <p class="truncate font-display text-sm font-bold">{{ $sapaan }}, {{ $namaDepan }} 👋</p>
            <p class="truncate text-xs text-slate-500"><i class="bi bi-calendar-check text-accent-dark"></i> {{ $akademik['label'] }}</p>
        </div>

        {{-- Pencarian --}}
        @if ($role === 'admin')
            <form method="GET" action="{{ route('admin/cari') }}" class="relative ml-auto hidden max-w-sm flex-1 md:block" role="search"
                  x-data="cari()" @click.outside="open = false">
                <i class="bi bi-search pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" x-model="q" @input.debounce.300ms="cari" @focus="open = results.length > 0" autocomplete="off"
                       placeholder="Cari siswa, guru, berita..." class="input pl-10" aria-label="Pencarian">
                <div x-cloak x-show="open" class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-lift">
                    <template x-for="item in results" :key="item.url">
                        <a :href="item.url" class="flex items-center gap-3 px-4 py-2.5 text-sm text-ink no-underline hover:bg-primary-soft">
                            <i class="bi text-primary" :class="'bi-' + item.icon"></i>
                            <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                            <span class="badge badge-muted" x-text="item.type"></span>
                        </a>
                    </template>
                    <p x-show="results.length === 0 && q.length >= 2" class="px-4 py-3 text-sm text-slate-500">Tidak ada hasil.</p>
                </div>
            </form>
        @else
            <span class="ml-auto"></span>
        @endif

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            {{-- Notifikasi --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="btn btn-outline btn-sm relative" @click="open = !open" aria-label="Notifikasi" :aria-expanded="open">
                    <i class="bi bi-bell text-base"></i>
                    @if (count($notifikasi) > 0)
                        <span class="absolute -top-1 -right-1 grid size-4 place-items-center rounded-full bg-accent text-[0.6rem] font-bold text-ink">{{ count($notifikasi) }}</span>
                    @endif
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-lift">
                    <p class="border-b border-slate-100 px-4 py-3 font-display text-sm font-bold">Notifikasi</p>
                    @forelse ($notifikasi as $n)
                        <a href="{{ $n['url'] }}" class="flex gap-3 px-4 py-3 text-sm no-underline hover:bg-primary-soft">
                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent-dark"><i class="bi bi-{{ $n['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-ink">{{ $n['title'] }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $n['text'] }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada notifikasi baru.</p>
                    @endforelse
                </div>
            </div>

            {{-- Profil --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pr-3 pl-1.5 transition hover:border-primary/40" @click="open = !open" :aria-expanded="open">
                    <span class="grid size-8 place-items-center rounded-lg bg-primary font-display text-sm font-bold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="hidden max-w-32 truncate text-sm font-semibold sm:block">{{ auth()->user()->name }}</span>
                    <i class="bi bi-chevron-down text-xs text-slate-400"></i>
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-100 bg-white p-1.5 shadow-lift">
                    <p class="px-3 py-2 text-xs text-slate-500 capitalize">Masuk sebagai {{ \App\User::PANEL_NAMA[$role] ?? $role }}</p>
                    @foreach (array_diff(auth()->user()->panelTersedia(), [$role]) as $panelLain)
                        <form method="POST" action="{{ route('panel.ganti', $panelLain) }}">
                            @csrf
                            <button type="submit" class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl border-0 bg-transparent px-3 py-2 text-left text-sm font-medium text-ink hover:bg-primary-soft"><i class="bi bi-arrow-left-right text-primary"></i> Buka panel {{ \App\User::PANEL_NAMA[$panelLain] ?? ucfirst($panelLain) }}</button>
                        </form>
                    @endforeach
                    @if (\Illuminate\Support\Facades\Route::has($role.'/profile'))<a href="{{ route($role.'/profile') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-ink no-underline hover:bg-primary-soft"><i class="bi bi-person-circle text-primary"></i> Profil Saya</a>@endif
                    <a href="{{ url('/') }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-ink no-underline hover:bg-primary-soft"><i class="bi bi-box-arrow-up-right text-primary"></i> Lihat Situs</a>
                    <hr class="my-1.5 border-slate-100">
                    <form method="POST" action="{{ $role === 'platform' ? route('platform.logout') : route('logout.post') }}">
                        @csrf
                        <button type="submit" class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl border-0 bg-transparent px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function cari() {
        return {
            q: '', results: [], open: false, ctrl: null,
            async cari() {
                if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                this.ctrl?.abort();
                this.ctrl = new AbortController();
                try {
                    const res = await fetch(@js(route('admin/cari')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' }, signal: this.ctrl.signal });
                    this.results = (await res.json()).results;
                    this.open = true;
                } catch (e) { /* dibatalkan */ }
            },
        };
    }
</script>
