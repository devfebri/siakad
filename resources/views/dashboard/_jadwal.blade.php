@php($tampilKelas = $tampilKelas ?? true)
<div class="card h-full">
    <div class="card-header">Jadwal Hari Ini <span class="text-xs font-normal text-slate-500">{{ now()->translatedFormat('l, d F') }}</span></div>
    <div class="divide-y divide-slate-100">
        @forelse ($jadwalHariIni as $j)
            <div class="flex items-center gap-3 px-5 py-3">
                <span class="w-24 shrink-0 font-display text-sm font-bold text-primary">{{ $j->mulai }}–{{ $j->selesai }}</span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{ $j->mapel?->mapel_nama ?? '-' }}</span>
                    <span class="text-xs text-slate-500">
                        @if ($tampilKelas){{ $j->kelas?->nama_kelas }} · @endif{{ $j->guru?->nama_guru ?? 'Guru belum diatur' }}@if ($j->ruang) · {{ $j->ruang }}@endif
                    </span>
                </span>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada jadwal pelajaran hari ini.</p>
        @endforelse
    </div>
</div>
