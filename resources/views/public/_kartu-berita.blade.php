@php $tanggal = $b->created_at ?? $b->berita_tanggal; @endphp
<article class="card card-lift group flex flex-col overflow-hidden">
    <a href="{{ route('berita.show', $b->berita_slug) }}" class="relative block overflow-hidden no-underline" tabindex="-1" aria-hidden="true">
        <img src="{{ $b->gambar_url }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-105">
        @if ($b->kategori)<span class="badge badge-accent absolute top-3 left-3 shadow-sm">{{ $b->kategori->kategori_nama }}</span>@endif
    </a>
    <div class="flex flex-1 flex-col p-5">
        <p class="mb-2 flex items-center gap-3 text-xs text-slate-500">
            <span><i class="bi bi-calendar3"></i> {{ $tanggal?->translatedFormat('d M Y') }}</span>
            <span><i class="bi bi-eye"></i> {{ number_format($b->berita_views, 0, ',', '.') }}</span>
        </p>
        <h3 class="font-display text-lg leading-snug font-bold"><a href="{{ route('berita.show', $b->berita_slug) }}" class="text-ink no-underline transition hover:text-primary">{{ $b->berita_judul }}</a></h3>
        <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($b->berita_isi), 140) }}</p>
        <a href="{{ route('berita.show', $b->berita_slug) }}" class="mt-auto pt-4 text-sm font-semibold no-underline">Baca selengkapnya <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
