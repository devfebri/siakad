@extends('layouts.public')

@section('title', 'Pengumuman')

@section('content')
    <x-public.page-title title="Pengumuman" subtitle="Informasi resmi dari sekolah." />

    <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('berita') }}" class="btn btn-outline btn-sm">Berita</a>
            <a href="{{ route('pengumuman') }}" class="btn btn-primary btn-sm">Pengumuman</a>
            <a href="{{ route('agenda') }}" class="btn btn-outline btn-sm">Agenda</a>
        </div>

        @forelse ($data as $p)
            <article class="card mb-4 p-6">
                <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span class="badge badge-accent"><i class="bi bi-megaphone"></i> Pengumuman</span>
                    <span><i class="bi bi-calendar3"></i> {{ ($p->created_at ?? $p->pengumuman_tanggal)?->translatedFormat('d F Y') }}</span>
                    <span><i class="bi bi-person"></i> {{ $p->pengumuman_author }}</span>
                </div>
                <h2 class="font-display text-xl font-bold">{{ $p->pengumuman_judul }}</h2>
                <p class="mt-2 whitespace-pre-line text-slate-600">{{ $p->pengumuman_deskripsi }}</p>
            </article>
        @empty
            <div class="card p-12 text-center text-slate-500">Belum ada pengumuman.</div>
        @endforelse

        <div class="mt-8">{{ $data->links() }}</div>
    </div>
@endsection
