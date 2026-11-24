@extends('layouts.public')

@section('title', $post->berita_judul)
@section('description', \Illuminate\Support\Str::limit(strip_tags($post->berita_isi), 160))
@section('og_image', $post->gambar_url)

@php $tanggal = $post->created_at ?? $post->berita_tanggal; @endphp

@section('content')
    <x-public.page-title :title="$post->berita_judul" crumb="Berita" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
        <article class="lg:col-span-3">
            <div class="card overflow-hidden">
                <img src="{{ $post->gambar_url }}" alt="{{ $post->berita_judul }}" class="max-h-[28rem] w-full object-cover" fetchpriority="high">
                <div class="p-6 sm:p-10">
                    <p class="mb-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
                        @if ($post->kategori)<a href="{{ route('berita', ['kategori' => $post->kategori->kategori_nama]) }}" class="badge badge-accent no-underline">{{ $post->kategori->kategori_nama }}</a>@endif
                        <span><i class="bi bi-calendar3"></i> {{ $tanggal?->translatedFormat('d F Y') }}</span>
                        <span><i class="bi bi-person"></i> {{ $post->author }}</span>
                        <span><i class="bi bi-eye"></i> {{ number_format($post->berita_views, 0, ',', '.') }} dibaca</span>
                    </p>
                    <div class="prose-content text-base">{!! \App\Support\Html::clean($post->berita_isi) !!}</div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
                        <span class="text-sm font-semibold text-slate-500">Bagikan:</span>
                        <a href="https://wa.me/?text={{ urlencode($post->berita_judul.' '.url()->current()) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i class="bi bi-facebook"></i> Facebook</a>
                    </div>
                </div>
            </div>

            @if ($terkait->isNotEmpty())
                <h2 class="mt-12 mb-5 font-display text-2xl font-extrabold">Berita Terkait</h2>
                <div class="grid gap-6 sm:grid-cols-3">
                    @foreach ($terkait as $b)@include('public._kartu-berita', ['b' => $b])@endforeach
                </div>
            @endif
        </article>

        <aside class="space-y-6">
            <div class="card p-4">
                <h3 class="mb-3 font-display font-bold">Kategori</h3>
                <ul class="space-y-1">
                    @foreach ($kategori as $k)
                        <li><a href="{{ route('berita', ['kategori' => $k->kategori_nama]) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-600 no-underline hover:bg-primary-soft">{{ $k->kategori_nama }} <span class="badge badge-muted">{{ $k->berita_count }}</span></a></li>
                    @endforeach
                </ul>
            </div>
            <div class="card p-4">
                <h3 class="mb-3 font-display font-bold">Terpopuler</h3>
                <ol class="space-y-3">
                    @foreach ($populer as $b)
                        <li class="flex gap-3"><span class="font-display text-xl font-extrabold text-accent">{{ $loop->iteration }}</span><a href="{{ route('berita.show', $b->berita_slug) }}" class="line-clamp-2 text-sm font-semibold text-ink no-underline hover:text-primary">{{ $b->berita_judul }}</a></li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>
@endsection
