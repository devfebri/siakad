@extends('layouts.public')

@section('title', 'Profil Sekolah')
@section('description', 'Profil, visi misi, sejarah, struktur organisasi, dan tenaga pendidik '.config('school.name'))

@php
    // bagian profil -> jangkar halaman
    $jangkar = [1 => 'tentangkami', 2 => 'visimisi', 3 => 'sejarah', 4 => 'sosialmedia', 5 => 'administrasi', 6 => 'perpustakaan', 7 => 'labkom', 8 => 'struktur'];
    $isi = collect($sections)->filter(fn ($s, $id) => filled(strip_tags((string) ($rows[$id]->deskripsi ?? ''))) || filled($rows[$id]->gambar ?? null));
@endphp

@section('content')
    <x-public.page-title title="Profil Sekolah" subtitle="Mengenal lebih dekat {{ config('school.name') }}." crumb="Profil" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
        <aside class="lg:col-span-1">
            <nav class="card sticky top-24 p-2" aria-label="Isi halaman">
                @foreach ($isi as $id => $section)
                    <a href="#{{ $jangkar[$id] }}" class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-600 no-underline transition hover:bg-primary-soft hover:text-primary"><i class="bi bi-{{ $section['icon'] }} w-5 text-center text-accent-dark"></i> {{ $section['label'] }}</a>
                @endforeach
                <a href="#guru" class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-600 no-underline transition hover:bg-primary-soft hover:text-primary"><i class="bi bi-person-workspace w-5 text-center text-accent-dark"></i> Guru & Tenaga Pendidik</a>
            </nav>
        </aside>

        <div class="space-y-12 lg:col-span-3">
            @forelse ($isi as $id => $section)
                @php $row = $rows[$id]; @endphp
                <article id="{{ $jangkar[$id] }}" class="card scroll-mt-24 overflow-hidden">
                    @if ($section['dir'] && $row->gambar)
                        <img src="{{ asset('storage/pengaturan/'.$section['dir'].'/'.$row->gambar) }}" alt="{{ $row->judul }}" loading="lazy" class="max-h-96 w-full object-cover">
                    @endif
                    <div class="p-6 sm:p-8">
                        <h2 class="mb-4 flex items-center gap-3 font-display text-2xl font-extrabold"><span class="grid size-10 place-items-center rounded-xl bg-primary text-lg text-accent"><i class="bi bi-{{ $section['icon'] }}"></i></span> {{ $row->judul ?: $section['label'] }}</h2>
                        <div class="prose-content">{!! \App\Support\Html::clean($row->deskripsi) !!}</div>
                    </div>
                </article>
            @empty
                <p class="card p-10 text-center text-slate-500">Isi profil sekolah belum ditambahkan.</p>
            @endforelse

            <section id="guru" class="scroll-mt-24">
                <h2 class="mb-6 font-display text-2xl font-extrabold"><i class="bi bi-person-workspace text-accent-dark"></i> Guru & Tenaga Pendidik</h2>
                @if ($guru->isEmpty())
                    <p class="card p-8 text-center text-slate-500">Data guru belum tersedia.</p>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($guru as $g)
                            <div class="card card-lift p-4 text-center">
                                <img src="{{ \App\Support\Avatar::url('guru', $g->user?->email, $g->avatar) }}" alt="" loading="lazy" class="mx-auto mb-3 size-20 rounded-full bg-slate-100 object-cover ring-4 ring-primary-soft">
                                <p class="font-display text-sm leading-tight font-bold">{{ $g->nama_guru }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $g->mapel?->mapel_nama ?? 'Guru' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
