@extends('layouts.public')

@section('title', 'Unduhan')
@section('description', 'Unduh formulir, panduan, brosur, dan dokumen resmi '.config('school.name').'.')

@section('content')
    <x-public.page-title title="Unduhan" subtitle="Formulir, panduan, brosur, dan dokumen resmi sekolah." />

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('unduhan') }}" class="mb-6 flex flex-col gap-3 sm:flex-row" role="search">
            <div class="relative flex-1">
                <i class="bi bi-search pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="Cari berkas..." aria-label="Cari berkas" class="input !pl-11">
            </div>
            @if ($aktif)<input type="hidden" name="kategori" value="{{ $aktif }}">@endif
            <button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button>
        </form>

        @if ($kategori->isNotEmpty())
            <div class="mb-8 flex flex-wrap gap-2" aria-label="Kategori">
                <a href="{{ route('unduhan', array_filter(['q' => $q])) }}" @class(['btn btn-sm', 'btn-primary' => $aktif === '', 'btn-outline' => $aktif !== ''])>Semua</a>
                @foreach ($kategori as $nama => $total)
                    <a href="{{ route('unduhan', array_filter(['q' => $q, 'kategori' => $nama])) }}" @class(['btn btn-sm', 'btn-primary' => $aktif === $nama, 'btn-outline' => $aktif !== $nama])>{{ $nama }} <span class="opacity-70">{{ $total }}</span></a>
                @endforeach
            </div>
        @endif

        @if ($data->isEmpty())
            <div class="card p-12 text-center text-slate-500">
                <i class="bi bi-cloud-download text-5xl text-slate-300"></i>
                <p class="mt-3">{{ $q || $aktif ? 'Tidak ada berkas yang cocok dengan pencarian Anda.' : 'Belum ada berkas untuk diunduh.' }}</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($data as $u)
                    <article class="card card-lift flex flex-col p-5">
                        <div class="flex items-start gap-4">
                            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary-soft text-3xl text-primary"><i class="bi bi-{{ $u->ikon }}"></i></span>
                            <div class="min-w-0">
                                <span class="badge badge-accent">{{ $u->kategori }}</span>
                                <h2 class="mt-1.5 font-display text-base leading-snug font-bold text-ink">{{ $u->judul }}</h2>
                            </div>
                        </div>
                        @if ($u->deskripsi)<p class="mt-3 flex-1 text-sm text-slate-600">{{ $u->deskripsi }}</p>@else<div class="flex-1"></div>@endif
                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <p class="text-xs text-slate-500">
                                <span class="font-semibold uppercase">{{ $u->ekstensi }}</span> · {{ $u->ukuran_teks }}<br>
                                {{ number_format($u->jumlah_unduh, 0, ',', '.') }}× diunduh · {{ $u->updated_at?->translatedFormat('d M Y') }}
                            </p>
                            <a href="{{ route('unduhan.unduh', $u) }}" class="btn btn-primary btn-sm shrink-0" rel="nofollow" aria-label="Unduh {{ $u->judul }}"><i class="bi bi-download"></i> Unduh</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-10">{{ $data->links() }}</div>
        @endif
    </div>
@endsection
