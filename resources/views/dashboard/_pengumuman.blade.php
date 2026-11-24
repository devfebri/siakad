<div class="card h-full">
    <div class="card-header">Pengumuman</div>
    <div class="divide-y divide-slate-100">
        @forelse ($pengumuman as $p)
            <div class="px-5 py-4">
                <p class="font-semibold">{{ $p->pengumuman_judul }}</p>
                <p class="mt-0.5 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($p->pengumuman_deskripsi), 120) }}</p>
                <p class="mt-1.5 text-xs text-slate-400"><i class="bi bi-clock"></i> {{ ($p->created_at ?? $p->pengumuman_tanggal)?->diffForHumans() }}</p>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada pengumuman.</p>
        @endforelse
    </div>
</div>
