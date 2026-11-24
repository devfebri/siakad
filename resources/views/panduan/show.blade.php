@extends('layouts.admin')

@section('title', $a['judul'])

@section('content')
    <article class="mx-auto max-w-3xl">
        <a href="{{ route('panduan') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 no-underline hover:text-primary print:hidden"><i class="bi bi-arrow-left"></i> Semua panduan</a>

        <header class="mb-6 flex items-start gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary text-3xl text-accent print:hidden"><i class="bi bi-{{ $a['ikon'] }}"></i></span>
            <div class="min-w-0">
                <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">{{ $a['kategori'] }}</p>
                <h1 class="font-display text-2xl font-extrabold text-primary sm:text-3xl">{{ $a['judul'] }}</h1>
                <p class="mt-1 text-slate-600">{{ $a['ringkas'] }}</p>
            </div>
        </header>

        <div class="mb-6 flex flex-wrap gap-2 print:hidden">
            @if ($tautan)<a href="{{ $tautan[0] }}" class="btn btn-accent"><i class="bi bi-box-arrow-up-right"></i> {{ $tautan[1] }}</a>@endif
            <button type="button" class="btn btn-outline" onclick="window.print()"><i class="bi bi-printer"></i> Cetak panduan ini</button>
        </div>

        <ol class="space-y-3">
            @foreach ($a['langkah'] as $langkah)
                <li class="card flex gap-4 p-4 sm:p-5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primary font-display text-base font-bold text-white">{{ $loop->iteration }}</span>
                    <p class="min-w-0 pt-1 text-base leading-relaxed text-ink">{!! \App\Support\Panduan::format($langkah) !!}</p>
                </li>
            @endforeach
        </ol>

        @if (! empty($a['tips']))
            <aside class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-label="Catatan">
                <h2 class="mb-2 font-display font-bold text-amber-900"><i class="bi bi-lightbulb-fill"></i> Perlu diketahui</h2>
                <ul class="list-disc space-y-1.5 pl-5 text-amber-900">
                    @foreach ($a['tips'] as $tip)<li>{!! \App\Support\Panduan::format($tip) !!}</li>@endforeach
                </ul>
            </aside>
        @endif

        @if ($serupa->isNotEmpty())
            <section class="mt-8 print:hidden" aria-labelledby="serupa-judul">
                <h2 id="serupa-judul" class="mb-3 font-display text-lg font-bold">Panduan lain di "{{ $a['kategori'] }}"</h2>
                <ul class="space-y-2">
                    @foreach ($serupa as $s)<li><a href="{{ route('panduan.show', $s['slug']) }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-ink no-underline hover:border-primary/40"><i class="bi bi-{{ $s['ikon'] }} text-primary"></i> {{ $s['judul'] }}</a></li>@endforeach
                </ul>
            </section>
        @endif

        <p class="mt-8 text-center text-sm text-slate-500 print:hidden">Masih bingung? Hubungi admin sekolah Anda.</p>
    </article>
@endsection
