@extends('layouts.public')

@section('description', config('school.hero_deskripsi'))
@if (config('school.hero_gambar'))
    @section('og_image', asset(config('school.hero_gambar')))
@endif

@section('content')
    {{-- ======================================================= HERO --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-primary-deep text-white">
        <div class="bg-grid pointer-events-none absolute inset-0"></div>
        <div class="pointer-events-none absolute -top-24 -left-24 size-80 rounded-full bg-accent/15 blur-3xl"></div>
        <i class="bi bi-book-half pointer-events-none absolute right-10 bottom-0 hidden text-[18rem] leading-none text-white/[0.05] lg:block"></i>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 pt-16 pb-28 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pt-24 lg:pb-36">
            <div>
                @if ($ppdb['state'] === 'buka' && \App\Support\AksesSekolah::modul('ppdb'))
                    <a href="{{ route('ppdb') }}" class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 py-1.5 pr-4 pl-1.5 text-sm font-semibold text-white no-underline ring-1 ring-white/20 transition hover:bg-white/15">
                        <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-bold text-ink">PPDB</span> Pendaftaran {{ $ppdb['tahun_ajaran'] }} dibuka <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
                <h1 class="font-display text-4xl leading-[1.1] font-extrabold sm:text-5xl lg:text-6xl">{{ config('school.hero_judul') }}</h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-white/80">{{ config('school.hero_deskripsi') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @if (\App\Support\AksesSekolah::modul('ppdb'))<a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg"><i class="bi bi-pencil-square"></i> Daftar PPDB</a>@endif
                    <a href="{{ route('profil') }}" class="btn btn-lg border border-white/30 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-building"></i> Lihat Profil</a>
                </div>
                <dl class="mt-10 flex flex-wrap gap-x-10 gap-y-4 text-sm">
                    @if (config('school.akreditasi'))
                        <div><dt class="text-white/60">Akreditasi</dt><dd class="font-display text-2xl font-extrabold text-accent">{{ config('school.akreditasi') }}</dd></div>
                    @endif
                    @if (config('school.tahun_berdiri'))
                        <div><dt class="text-white/60">Berdiri sejak</dt><dd class="font-display text-2xl font-extrabold">{{ config('school.tahun_berdiri') }}</dd></div>
                    @endif
                    @if (config('school.npsn'))
                        <div><dt class="text-white/60">NPSN</dt><dd class="font-display text-2xl font-extrabold">{{ config('school.npsn') }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                <div class="absolute -top-6 -right-6 size-40 rounded-3xl bg-accent/90"></div>
                <div class="bg-grid absolute -bottom-6 -left-6 size-40 rounded-3xl border border-white/20"></div>
                <div class="relative overflow-hidden rounded-[2rem] bg-white/10 shadow-lift ring-1 ring-white/20">
                    @if (config('school.hero_gambar'))
                        <img src="{{ asset(config('school.hero_gambar')) }}" alt="Siswa {{ config('school.name') }}" class="aspect-[4/5] w-full object-cover" fetchpriority="high">
                    @else
                        <div class="grid aspect-[4/5] place-items-center bg-gradient-to-br from-white/15 to-white/5">
                            <div class="text-center">
                                <i class="bi bi-mortarboard-fill text-[7rem] leading-none text-accent"></i>
                                <div class="mt-4 flex justify-center gap-5 text-4xl text-white/70"><i class="bi bi-book"></i><i class="bi bi-pencil"></i><i class="bi bi-lightbulb"></i></div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="absolute -bottom-5 left-4 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-ink shadow-lift sm:-left-6">
                    <span class="grid size-10 place-items-center rounded-xl bg-primary-soft text-primary"><i class="bi bi-people-fill text-lg"></i></span>
                    <span><span class="block font-display text-lg leading-none font-extrabold">{{ number_format($stat['siswa'], 0, ',', '.') }}+</span><span class="text-xs text-slate-500">Siswa aktif</span></span>
                </div>
            </div>
        </div>
    </section>

    {{-- ======================================================= STATISTIK --}}
    <section class="relative z-10 -mt-14 px-4 sm:px-6 lg:px-8" aria-label="Statistik sekolah">
        <div class="mx-auto grid max-w-6xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Siswa', $stat['siswa'], 'mortarboard', 'bg-primary-soft text-primary'],
                ['Guru & Tenaga Pendidik', $stat['guru'], 'person-workspace', 'bg-accent-soft text-accent-dark'],
                ['Prestasi', $stat['prestasi'], 'trophy', 'bg-emerald-50 text-emerald-600'],
                ['Alumni', $stat['alumni'], 'people', 'bg-sky-50 text-sky-600'],
            ] as [$label, $nilai, $ikon, $warna])
                <div class="card card-lift flex items-center gap-4 p-5">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl text-3xl {{ $warna }}"><i class="bi bi-{{ $ikon }}"></i></span>
                    <span>
                        <span class="block font-display text-3xl leading-none font-extrabold text-ink">{{ $nilai ? number_format($nilai, 0, ',', '.').'+' : '-' }}</span>
                        <span class="mt-1 block text-sm font-medium text-slate-500">{{ $label }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ======================================================= SAMBUTAN --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid items-center gap-12 lg:grid-cols-5">
            <div class="relative lg:col-span-2">
                <div class="absolute -bottom-4 -left-4 size-full rounded-[2rem] bg-accent/80"></div>
                <div class="relative aspect-[4/5] overflow-hidden rounded-[2rem] bg-primary-soft shadow-lift">
                    @if (config('school.kepsek_foto'))
                        <img src="{{ asset(config('school.kepsek_foto')) }}" alt="{{ config('school.kepsek_nama') }}" class="size-full object-cover" loading="lazy">
                    @else
                        <div class="grid size-full place-items-center text-primary/30"><i class="bi bi-person-fill text-[9rem]"></i></div>
                    @endif
                </div>
            </div>
            <div class="lg:col-span-3">
                <x-public.section align="left" eyebrow="Sambutan" title="Dari Kepala Sekolah" />
                <figure>
                    <i class="bi bi-quote text-6xl leading-none text-accent"></i>
                    <blockquote class="-mt-3 font-display text-2xl leading-relaxed font-semibold text-ink sm:text-3xl">{{ config('school.kepsek_sambutan') }}</blockquote>
                    <figcaption class="mt-6 flex items-center gap-3">
                        <span class="h-0.5 w-10 rounded-full bg-accent"></span>
                        <span><span class="block font-display font-bold text-primary">{{ config('school.kepsek_nama') ?: 'Kepala Sekolah' }}</span><span class="text-sm text-slate-500">{{ config('school.kepsek_jabatan') }}, {{ config('school.name') }}</span></span>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- ======================================================= PROGRAM UNGGULAN --}}
    @if ($program->isNotEmpty())
        <section class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-public.section eyebrow="Program Unggulan" title="Tumbuh Lewat Pilihan yang Tepat" subtitle="Program yang dirancang agar setiap siswa menemukan dan mengembangkan potensinya." />
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($program as $p)
                        <article class="card card-lift group p-6">
                            <span class="mb-5 grid size-14 place-items-center rounded-2xl bg-primary text-3xl text-accent transition group-hover:bg-accent group-hover:text-ink"><i class="bi bi-{{ $p->ikon }}"></i></span>
                            <h3 class="font-display text-lg font-bold">{{ $p->judul }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $p->deskripsi }}</p>
                        </article>
                    @endforeach
                </div>
                <div class="mt-8 text-center"><a href="{{ route('akademik') }}" class="btn btn-outline">Selengkapnya <i class="bi bi-arrow-right"></i></a></div>
            </div>
        </section>
    @endif

    {{-- ======================================================= BERITA --}}
    @if (\App\Support\AksesSekolah::modul('konten_web'))
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <x-public.section align="left" eyebrow="Berita" title="Kabar Terbaru dari Sekolah" />
            <a href="{{ route('berita') }}" class="btn btn-outline mb-10">Semua berita <i class="bi bi-arrow-right"></i></a>
        </div>
        @if ($berita->isEmpty())
            <p class="card p-10 text-center text-slate-500">Belum ada berita.</p>
        @else
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($berita as $b)
                    @include('public._kartu-berita', ['b' => $b])
                @endforeach
            </div>
        @endif

        @if ($pengumuman->isNotEmpty())
            <div class="card mt-8 p-5">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-display font-bold"><i class="bi bi-megaphone text-accent-dark"></i> Pengumuman</h3><a href="{{ route('pengumuman') }}" class="text-sm font-semibold no-underline">Lihat semua</a></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($pengumuman as $p)
                        <li class="flex items-baseline justify-between gap-4 py-2.5 text-sm"><a href="{{ route('pengumuman') }}" class="font-semibold text-ink no-underline hover:text-primary">{{ $p->pengumuman_judul }}</a><span class="shrink-0 text-xs text-slate-400">{{ ($p->created_at ?? $p->pengumuman_tanggal)?->translatedFormat('d M Y') }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @endif

    {{-- ======================================================= GALERI --}}
    @if (\App\Support\AksesSekolah::modul('konten_web') && $galeri->isNotEmpty())
        <section class="bg-white py-20" x-data="{ foto: null }" @keydown.escape.window="foto = null">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-public.section eyebrow="Galeri" title="Momen Kegiatan Kami" subtitle="Sekilas suasana belajar dan kegiatan di sekolah." />
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ($galeri as $g)
                        <button type="button" @click="foto = @js(asset('storage/gallery/'.$g->foto))" class="group relative cursor-zoom-in overflow-hidden rounded-2xl border-0 bg-slate-100 p-0 {{ $loop->first ? 'col-span-2 row-span-2' : '' }}">
                            <img src="{{ asset('storage/gallery/'.$g->foto) }}" alt="Foto kegiatan sekolah" loading="lazy" class="aspect-square size-full object-cover transition duration-500 group-hover:scale-105">
                            <span class="absolute inset-0 bg-gradient-to-t from-primary/50 to-transparent opacity-0 transition group-hover:opacity-100"></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-8 text-center"><a href="{{ route('galeri') }}" class="btn btn-outline">Lihat galeri lengkap <i class="bi bi-arrow-right"></i></a></div>
            </div>
            <div x-cloak x-show="foto" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-900/85 p-4" @click="foto = null" role="dialog" aria-modal="true" aria-label="Pratinjau foto">
                <img :src="foto" alt="" class="max-h-[88vh] max-w-full rounded-2xl shadow-lift" @click.stop>
                <button type="button" class="absolute top-4 right-4 grid size-10 place-items-center rounded-full border-0 bg-white/15 text-xl text-white hover:bg-white/25" @click="foto = null" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
        </section>
    @endif

    {{-- ======================================================= TESTIMONI --}}
    @if ($testimoni->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Testimoni" title="Kata Mereka tentang Kami" subtitle="Pengalaman alumni dan orang tua siswa." />
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimoni as $t)
                    <figure class="card flex flex-col p-6">
                        <i class="bi bi-quote text-4xl leading-none text-accent"></i>
                        <blockquote class="mt-1 flex-1 text-slate-700">{{ $t->isi }}</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3 border-t border-slate-100 pt-4">
                            @if ($t->foto_url)
                                <img src="{{ $t->foto_url }}" alt="{{ $t->nama }}" loading="lazy" class="size-11 rounded-full object-cover">
                            @else
                                <span class="grid size-11 place-items-center rounded-full bg-primary font-display font-bold text-white">{{ mb_strtoupper(mb_substr($t->nama, 0, 1)) }}</span>
                            @endif
                            <span><span class="block font-display font-bold">{{ $t->nama }}</span><span class="text-xs text-slate-500">{{ $t->peran }}</span></span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ======================================================= BANNER PPDB --}}
    @if (\App\Support\AksesSekolah::modul('ppdb'))
        @include('public._banner-ppdb', ['ppdb' => $ppdb])
    @endif
@endsection
