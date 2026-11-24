@extends('layouts.admin')

@section('title', 'Panduan')

@section('content')
    <x-admin.page-header title="Panduan" :subtitle="'Cara memakai aplikasi sebagai '.$namaPanel.'. Pilih yang ingin dipelajari, atau ketik kata kunci.'" />

    <form method="GET" action="{{ route('panduan') }}" class="mb-6" role="search">
        <label for="cariPanduan" class="sr-only">Cari panduan</label>
        <div class="relative max-w-2xl">
            <i class="bi bi-search pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-slate-400"></i>
            <input id="cariPanduan" type="search" name="q" value="{{ $q }}" placeholder="Contoh: absensi, tagihan, password, impor..." class="input !rounded-2xl !py-3.5 !pl-11 !text-base" autocomplete="off">
        </div>
    </form>

    @if ($q !== '')
        <p class="mb-4 text-sm text-slate-600">{{ $artikel->count() }} panduan ditemukan untuk <strong>"{{ $q }}"</strong>. <a href="{{ route('panduan') }}">Tampilkan semua</a></p>
    @endif

    {{-- ============================================================ mulai dari sini --}}
    @if ($mulai->isNotEmpty())
        <section class="mb-8" aria-labelledby="mulai-judul">
            <h2 id="mulai-judul" class="mb-3 font-display text-lg font-bold"><i class="bi bi-stars text-accent"></i> Mulai dari sini</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($mulai as $a)
                    <a href="{{ route('panduan.show', $a['slug']) }}" class="card card-lift flex gap-4 p-5 no-underline">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-accent-soft text-2xl text-accent-dark"><i class="bi bi-{{ $a['ikon'] }}"></i></span>
                        <span class="min-w-0"><span class="block font-display font-bold text-ink">{{ $a['judul'] }}</span><span class="mt-1 block text-sm text-slate-600">{{ $a['ringkas'] }}</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============================================================ semua panduan per kelompok --}}
    @forelse ($artikel->groupBy('kategori') as $nama => $daftar)
        <section class="mb-8" aria-labelledby="kat-{{ \Illuminate\Support\Str::slug($nama) }}">
            <h2 id="kat-{{ \Illuminate\Support\Str::slug($nama) }}" class="mb-3 font-display text-lg font-bold"><i class="bi bi-{{ $kategori[$nama] ?? 'book' }} text-primary"></i> {{ $nama }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($daftar as $a)
                    <a href="{{ route('panduan.show', $a['slug']) }}" class="card flex items-start gap-4 p-4 no-underline transition hover:border-primary/40 hover:shadow-soft">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-xl text-primary"><i class="bi bi-{{ $a['ikon'] }}"></i></span>
                        <span class="min-w-0"><span class="block font-semibold text-ink">{{ $a['judul'] }}</span><span class="mt-0.5 block text-sm text-slate-500">{{ $a['ringkas'] }}</span></span>
                        <i class="bi bi-chevron-right ml-auto self-center text-slate-300"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card p-10 text-center text-slate-500">
            <i class="bi bi-search text-4xl text-slate-300"></i>
            <p class="mt-3 font-semibold text-ink">Tidak ada panduan yang cocok.</p>
            <p class="text-sm">Coba kata lain, atau <a href="{{ route('panduan') }}">tampilkan semua panduan</a>.</p>
        </div>
    @endforelse
@endsection
