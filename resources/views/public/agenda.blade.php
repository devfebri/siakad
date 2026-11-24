@extends('layouts.public')

@section('title', 'Agenda')

@section('content')
    <x-public.page-title title="Agenda Sekolah" subtitle="Jadwal kegiatan dan acara sekolah." />

    <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('berita') }}" class="btn btn-outline btn-sm">Berita</a>
            <a href="{{ route('pengumuman') }}" class="btn btn-outline btn-sm">Pengumuman</a>
            <a href="{{ route('agenda') }}" class="btn btn-primary btn-sm">Agenda</a>
        </div>

        @forelse ($data as $a)
            <article class="card mb-4 flex gap-5 p-5 sm:p-6">
                <span class="grid size-16 shrink-0 place-items-center rounded-2xl bg-primary text-center text-accent"><i class="bi bi-calendar-event text-3xl"></i></span>
                <div class="min-w-0">
                    <h2 class="font-display text-xl font-bold">{{ $a->agenda_nama }}</h2>
                    <p class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-500">
                        <span><i class="bi bi-clock"></i> {{ $a->agenda_waktu }}</span>
                        <span><i class="bi bi-geo-alt"></i> {{ $a->agenda_tempat }}</span>
                    </p>
                    <p class="mt-2 text-slate-600">{{ $a->agenda_deskripsi }}</p>
                    @if ($a->agenda_keterangan && $a->agenda_keterangan !== '-')<p class="mt-1 text-sm text-slate-500">{{ $a->agenda_keterangan }}</p>@endif
                </div>
            </article>
        @empty
            <div class="card p-12 text-center text-slate-500">Belum ada agenda.</div>
        @endforelse

        <div class="mt-8">{{ $data->links() }}</div>
    </div>
@endsection
