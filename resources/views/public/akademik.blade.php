@extends('layouts.public')

@section('title', 'Akademik')
@section('description', 'Program unggulan, mata pelajaran, dan agenda akademik '.config('school.name'))

@section('content')
    <x-public.page-title title="Akademik" subtitle="Program, mata pelajaran, dan kegiatan yang mendukung tumbuh kembang siswa." />

    @if ($program->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Program Unggulan" title="Pilihan Program Belajar" />
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($program as $p)
                    <article class="card card-lift group flex gap-5 p-6">
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary text-3xl text-accent transition group-hover:bg-accent group-hover:text-ink"><i class="bi bi-{{ $p->ikon }}"></i></span>
                        <div><h3 class="font-display text-lg font-bold">{{ $p->judul }}</h3><p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $p->deskripsi }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Kurikulum" title="Mata Pelajaran" subtitle="Mata pelajaran yang diajarkan di sekolah kami." />
            @if ($mapel->isEmpty())
                <p class="text-center text-slate-500">Daftar mata pelajaran belum tersedia.</p>
            @else
                <div class="flex flex-wrap justify-center gap-2.5">
                    @foreach ($mapel as $m)
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-surface px-4 py-2 text-sm font-semibold text-slate-700"><i class="bi bi-journal-bookmark text-accent-dark"></i> {{ $m->mapel_nama }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-calendar-event text-accent-dark"></i> Agenda Sekolah</span><a href="{{ route('agenda') }}" class="text-sm font-semibold no-underline">Semua</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($agenda as $a)
                    <div class="px-5 py-4"><p class="font-semibold">{{ $a->agenda_nama }}</p><p class="mt-0.5 text-sm text-slate-500"><i class="bi bi-geo-alt"></i> {{ $a->agenda_tempat }} · {{ $a->agenda_waktu }}</p></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada agenda.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span><i class="bi bi-megaphone text-accent-dark"></i> Pengumuman</span><a href="{{ route('pengumuman') }}" class="text-sm font-semibold no-underline">Semua</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($pengumuman as $p)
                    <div class="px-5 py-4"><p class="font-semibold">{{ $p->pengumuman_judul }}</p><p class="mt-0.5 line-clamp-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit(strip_tags($p->pengumuman_deskripsi), 120) }}</p></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada pengumuman.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
