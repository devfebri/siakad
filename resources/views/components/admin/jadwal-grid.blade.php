{{-- Grid jadwal mingguan. $url = endpoint JSON, $kelasSelector = selector select kelas (opsional). --}}
@props(['url', 'kelasSelector' => null, 'tampilkanKelas' => false, 'tampilkanGuru' => true])
<div x-data="jadwalGrid(@js($url), @js($kelasSelector))" class="relative">
    <p x-show="memuat" class="py-6 text-center text-sm text-slate-500"><i class="bi bi-hourglass-split"></i> Memuat jadwal...</p>
    <p x-cloak x-show="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Jadwal gagal dimuat. Muat ulang halaman.</p>
    <p x-cloak x-show="!memuat && !error && kosong" class="card py-10 text-center text-sm text-slate-500">Belum ada jadwal pelajaran.</p>

    <div x-cloak x-show="!memuat && !kosong" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <template x-for="(h, idx) in hari" :key="h.nama">
            <section class="card overflow-hidden" x-show="h.items.length > 0">
                <header class="flex items-center justify-between px-4 py-2.5 font-display text-sm font-bold"
                        :class="(idx + 1) === hariIni ? 'bg-accent text-ink' : 'bg-primary text-white'">
                    <span x-text="h.nama"></span>
                    <span x-show="(idx + 1) === hariIni" class="rounded-full bg-ink/10 px-2 py-0.5 text-[0.65rem]">Hari ini</span>
                </header>
                <ul class="divide-y divide-slate-100">
                    <template x-for="i in h.items" :key="i.id">
                        <li class="flex gap-3 px-4 py-3 text-sm">
                            <span class="w-24 shrink-0 font-display font-bold text-primary" x-text="i.mulai + ' – ' + i.selesai"></span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold" x-text="i.mapel"></span>
                                <span class="block truncate text-xs text-slate-500">
                                    @if ($tampilkanKelas)<span x-text="i.kelas"></span>@endif
                                    @if ($tampilkanGuru)<span x-show="i.guru"><span x-show="i.kelas" class="mx-0.5">·</span><span x-text="i.guru"></span></span>@endif
                                    <span x-show="i.ruang"> · Ruang <span x-text="i.ruang"></span></span>
                                </span>
                            </span>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </div>
</div>
