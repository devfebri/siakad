@extends('layouts.public')

@section('title', $kategoriAktif ? 'Berita '.$kategoriAktif : 'Berita')
@section('description', 'Berita dan kegiatan terbaru '.config('school.name'))

@section('content')
    <x-public.page-title title="Berita" subtitle="Kabar, prestasi, dan kegiatan terbaru dari sekolah." />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-3">
            <div class="mb-6 flex flex-wrap gap-2" aria-label="Jenis informasi">
                <a href="{{ route('berita') }}" class="btn btn-primary btn-sm">Berita</a>
                <a href="{{ route('pengumuman') }}" class="btn btn-outline btn-sm">Pengumuman</a>
                <a href="{{ route('agenda') }}" class="btn btn-outline btn-sm">Agenda</a>
            </div>

            @if ($q !== '' || $kategoriAktif)
                <p class="mb-5 text-sm text-slate-600">
                    Menampilkan {{ $berita->total() }} berita @if ($q !== '') untuk "<strong>{{ $q }}</strong>" @endif @if ($kategoriAktif) di kategori <strong>{{ $kategoriAktif }}</strong> @endif
                    · <a href="{{ route('berita') }}" class="font-semibold no-underline">Hapus filter</a>
                </p>
            @endif

            @if ($berita->isEmpty())
                <div class="card p-12 text-center"><i class="bi bi-newspaper text-5xl text-slate-300"></i><p class="mt-3 text-slate-500">Belum ada berita yang cocok.</p></div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($berita as $b)
                        @include('public._kartu-berita', ['b' => $b])
                    @endforeach
                </div>
                <div class="mt-10">{{ $berita->links() }}</div>
            @endif
        </div>

        <aside class="space-y-6">
            <form method="GET" action="{{ route('berita') }}" role="search" class="card p-4">
                @if ($kategoriAktif)<input type="hidden" name="kategori" value="{{ $kategoriAktif }}">@endif
                <label for="q" class="label">Cari berita</label>
                <div class="relative"><i class="bi bi-search pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"></i><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Kata kunci..." class="input pl-10"></div>
            </form>

            <div class="card p-4">
                <h3 class="mb-3 font-display font-bold">Kategori</h3>
                <ul class="space-y-1">
                    @foreach ($kategori as $k)
                        <li><a href="{{ route('berita', ['kategori' => $k->kategori_nama]) }}" @class(['flex items-center justify-between rounded-lg px-3 py-2 text-sm no-underline hover:bg-primary-soft', 'bg-primary-soft font-semibold text-primary' => $kategoriAktif === $k->kategori_nama, 'text-slate-600' => $kategoriAktif !== $k->kategori_nama])>{{ $k->kategori_nama }} <span class="badge badge-muted">{{ $k->berita_count }}</span></a></li>
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
