@extends('layouts.public')

@section('title', 'Galeri')

@section('content')
    <x-public.page-title title="Galeri Kegiatan" subtitle="Momen belajar dan kegiatan di sekolah." />

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8" x-data="{ foto: null }" @keydown.escape.window="foto = null">
        @if ($data->isEmpty())
            <div class="card p-12 text-center text-slate-500"><i class="bi bi-images text-5xl text-slate-300"></i><p class="mt-3">Belum ada foto.</p></div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($data as $g)
                    <button type="button" @click="foto = @js(asset('storage/gallery/'.$g->foto))" class="group relative cursor-zoom-in overflow-hidden rounded-2xl border-0 bg-slate-100 p-0">
                        <img src="{{ asset('storage/gallery/'.$g->foto) }}" alt="Foto kegiatan sekolah" loading="lazy" class="aspect-square size-full object-cover transition duration-500 group-hover:scale-105">
                        <span class="absolute inset-0 bg-gradient-to-t from-primary/50 to-transparent opacity-0 transition group-hover:opacity-100"></span>
                    </button>
                @endforeach
            </div>
            <div class="mt-10">{{ $data->links() }}</div>
        @endif

        <div x-cloak x-show="foto" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-900/85 p-4" @click="foto = null" role="dialog" aria-modal="true" aria-label="Pratinjau foto">
            <img :src="foto" alt="" class="max-h-[88vh] max-w-full rounded-2xl shadow-lift" @click.stop>
            <button type="button" class="absolute top-4 right-4 grid size-10 place-items-center rounded-full border-0 bg-white/15 text-xl text-white hover:bg-white/25" @click="foto = null" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
@endsection
