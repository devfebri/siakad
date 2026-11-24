{{-- Beranda tema "portal" (gaya situs sekolah kejuruan). Beranda lama ada di beranda.blade.php. --}}
@extends('layouts.public')

@section('description', config('school.hero_deskripsi'))
@if (config('school.hero_gambar'))
    @section('og_image', asset(config('school.hero_gambar')))
@endif

@section('content')
    @php
        $slide = collect();
        if (config('school.hero_gambar')) {
            $slide->push(asset(config('school.hero_gambar')));
        }
        foreach ($galeri->take(3) as $g) {
            $slide->push(asset('storage/gallery/'.$g->foto));
        }
        $wa = config('school.whatsapp') ? 'https://wa.me/'.preg_replace('/\D/', '', config('school.whatsapp')) : null;
        $ppdbAktif = \App\Support\AksesSekolah::modul('ppdb');
        $konten = \App\Support\AksesSekolah::modul('konten_web');
    @endphp

    {{-- ======================================================= HERO SLIDER --}}
    <section class="relative isolate overflow-hidden bg-primary-deep text-white" x-data="{ i: 0, n: {{ max($slide->count(), 1) }}, t: null }" x-init="if (n > 1) t = setInterval(() => i = (i + 1) % n, 6000)">
        <div class="absolute inset-0 -z-10">
            @forelse ($slide as $url)
                <img src="{{ $url }}" alt="" class="absolute inset-0 size-full object-cover transition-opacity duration-1000" :class="i === {{ $loop->index }} ? 'opacity-100' : 'opacity-0'" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
            @empty
                <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary to-primary-deep"></div>
                <div class="bg-grid absolute inset-0"></div>
            @endforelse
            <div class="absolute inset-0 bg-gradient-to-r from-primary-deep/95 via-primary-deep/70 to-primary-deep/30"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8 lg:py-36">
            <div class="max-w-3xl">
                @if ($ppdb['state'] === 'buka' && $ppdbAktif)
                    <a href="{{ route('ppdb') }}" class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 py-1.5 pr-4 pl-1.5 text-sm font-semibold text-white no-underline ring-1 ring-white/25 transition hover:bg-white/20">
                        <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-bold text-ink">PPDB</span> Pendaftaran {{ $ppdb['tahun_ajaran'] }} dibuka <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
                <p class="animate__animated animate__fadeInDown mb-3 text-sm font-bold tracking-widest text-accent uppercase">Selamat Datang di</p>
                <h1 style="--animate-delay:200ms" class="animate__animated animate__fadeInLeft animate__delay-custom font-display text-4xl leading-[1.1] font-extrabold sm:text-5xl lg:text-6xl">{{ config('school.name') }}</h1>
                <p style="--animate-delay:400ms" class="animate__animated animate__fadeInLeft animate__delay-custom mt-5 max-w-2xl text-lg leading-relaxed text-white/85">{{ config('school.hero_judul') }}</p>
                <div style="--animate-delay:600ms" class="animate__animated animate__fadeInUp animate__delay-custom mt-8 flex flex-wrap gap-3">
                    @if ($ppdbAktif)<a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>@endif
                    <a href="{{ route('profil') }}" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-building"></i> Profil Sekolah</a>
                </div>
            </div>
        </div>

        @if ($slide->count() > 1)
            <div class="absolute inset-x-0 bottom-5 flex justify-center gap-2">
                @foreach ($slide as $url)
                    <button type="button" @click="i = {{ $loop->index }}; clearInterval(t)" class="h-2 rounded-full border-0 p-0 transition-all" :class="i === {{ $loop->index }} ? 'w-8 bg-accent' : 'w-2 bg-white/50'" aria-label="Slide {{ $loop->iteration }}"></button>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ======================================================= PENGANTAR + STATISTIK --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div data-animate="fadeInUp" class="mx-auto max-w-3xl text-center">
                <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">{{ config('school.tagline') }}</p>
                <h2 class="mt-2 font-display text-3xl font-extrabold text-primary sm:text-4xl">{{ config('school.short_name') }}, Tempat Tumbuh Generasi Hebat</h2>
                <p class="mt-4 leading-relaxed text-slate-600">{{ config('school.hero_deskripsi') }}</p>
            </div>
            <dl class="mt-12 grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-slate-200 lg:grid-cols-4">
                @foreach ([
                    ['Siswa Aktif', $stat['siswa'], 'mortarboard-fill'],
                    ['Guru & Tenaga Pendidik', $stat['guru'], 'person-workspace'],
                    ['Prestasi', $stat['prestasi'], 'trophy-fill'],
                    ['Alumni', $stat['alumni'], 'people-fill'],
                ] as [$label, $nilai, $ikon])
                    <div data-animate="zoomIn" data-delay="{{ $loop->index * 120 }}" class="bg-white px-4 py-8 text-center">
                        <i class="bi bi-{{ $ikon }} text-3xl text-accent"></i>
                        <dd class="mt-2 font-display text-4xl font-extrabold text-primary">{{ $nilai ? number_format($nilai, 0, ',', '.') : '-' }}</dd>
                        <dt class="mt-1 text-sm font-medium text-slate-500">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ======================================================= PROGRAM UNGGULAN --}}
    @if ($program->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Program" title="Program Unggulan" subtitle="Program yang dirancang agar setiap siswa menemukan dan mengembangkan potensinya." />
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($program as $p)
                    <article data-animate="fadeInUp" data-delay="{{ $loop->index * 120 }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-lift">
                        <div class="grid h-32 place-items-center bg-gradient-to-br from-primary to-primary-deep text-5xl text-accent"><i class="bi bi-{{ $p->ikon }}"></i></div>
                        <div class="p-5">
                            <h3 class="font-display text-lg font-bold text-primary">{{ $p->judul }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $p->deskripsi }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-8 text-center"><a href="{{ route('akademik') }}" class="btn btn-outline">Lihat Semua <i class="bi bi-arrow-right"></i></a></div>
        </section>
    @endif

    {{-- ======================================================= SELAYANG PANDANG --}}
    <section class="bg-primary-soft py-20">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
            <div data-animate="fadeInLeft" class="lg:col-span-2">
                <div class="mx-auto aspect-[4/5] max-w-sm overflow-hidden rounded-xl border-4 border-white bg-white shadow-lift">
                    @if (config('school.kepsek_foto'))
                        <img src="{{ asset(config('school.kepsek_foto')) }}" alt="{{ config('school.kepsek_nama') }}" class="size-full object-cover" loading="lazy">
                    @else
                        <div class="grid size-full place-items-center text-primary/25"><i class="bi bi-person-fill text-[9rem]"></i></div>
                    @endif
                </div>
            </div>
            <div data-animate="fadeInRight" class="lg:col-span-3">
                <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Selayang Pandang</p>
                <h2 class="mt-2 font-display text-3xl font-extrabold text-primary sm:text-4xl">Sambutan Kepala Sekolah</h2>
                <blockquote class="mt-6 border-l-4 border-accent pl-5 text-lg leading-relaxed text-slate-700 italic">{{ config('school.kepsek_sambutan') }}</blockquote>
                <p class="mt-5 font-display font-bold text-primary">{{ config('school.kepsek_nama') ?: 'Kepala Sekolah' }}</p>
                <p class="text-sm text-slate-500">{{ config('school.kepsek_jabatan') }}, {{ config('school.name') }}</p>
                <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-4">
                    @if (config('school.akreditasi'))<div><dd class="font-display text-3xl font-extrabold text-primary">{{ config('school.akreditasi') }}</dd><dt class="text-sm text-slate-500">Akreditasi</dt></div>@endif
                    @if (config('school.tahun_berdiri'))<div><dd class="font-display text-3xl font-extrabold text-primary">{{ config('school.tahun_berdiri') }}</dd><dt class="text-sm text-slate-500">Berdiri sejak</dt></div>@endif
                    @if (config('school.npsn'))<div><dd class="font-display text-3xl font-extrabold text-primary">{{ config('school.npsn') }}</dd><dt class="text-sm text-slate-500">NPSN</dt></div>@endif
                </dl>
            </div>
        </div>
    </section>

    {{-- ======================================================= BERITA --}}
    @if ($konten)
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Berita" title="Berita Terbaru" />
            @if ($berita->isEmpty())
                <p class="card p-10 text-center text-slate-500">Belum ada berita.</p>
            @else
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($berita as $b)
                        <div data-animate="fadeInUp" data-delay="{{ $loop->index * 150 }}" class="[&>article]:h-full">@include('public._kartu-berita', ['b' => $b])</div>
                    @endforeach
                </div>
            @endif
            <div class="mt-8 text-center"><a href="{{ route('berita') }}" class="btn btn-primary">Lihat Semua Berita <i class="bi bi-arrow-right"></i></a></div>

            @if ($pengumuman->isNotEmpty())
                <div data-animate="fadeInUp" class="mt-12 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between bg-primary px-5 py-3 text-white"><h3 class="font-display font-bold"><i class="bi bi-megaphone-fill text-accent"></i> Pengumuman</h3><a href="{{ route('pengumuman') }}" class="text-sm font-semibold text-white no-underline hover:text-accent">Lihat semua</a></div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($pengumuman as $p)
                            <li class="flex items-baseline justify-between gap-4 px-5 py-3 text-sm"><a href="{{ route('pengumuman') }}" class="font-semibold text-ink no-underline hover:text-primary">{{ $p->pengumuman_judul }}</a><span class="shrink-0 text-xs text-slate-400">{{ ($p->created_at ?? $p->pengumuman_tanggal)?->translatedFormat('d M Y') }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    {{-- ======================================================= GALERI --}}
    @if ($konten && $galeri->isNotEmpty())
        <section class="bg-white py-20" x-data="{ foto: null }" @keydown.escape.window="foto = null">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-public.section eyebrow="Galeri" title="Momen Kegiatan Kami" />
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ($galeri->take(8) as $g)
                        <button type="button" data-animate="zoomIn" data-delay="{{ $loop->index * 80 }}" @click="foto = @js(asset('storage/gallery/'.$g->foto))" class="group relative cursor-zoom-in overflow-hidden rounded-lg border-0 bg-slate-100 p-0">
                            <img src="{{ asset('storage/gallery/'.$g->foto) }}" alt="Foto kegiatan sekolah" loading="lazy" class="aspect-square size-full object-cover transition duration-500 group-hover:scale-105">
                            <span class="absolute inset-0 bg-primary/0 transition group-hover:bg-primary/30"></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-8 text-center"><a href="{{ route('galeri') }}" class="btn btn-outline">Galeri Lengkap <i class="bi bi-arrow-right"></i></a></div>
            </div>
            <div x-cloak x-show="foto" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-900/85 p-4" @click="foto = null" role="dialog" aria-modal="true" aria-label="Pratinjau foto">
                <img :src="foto" alt="" class="max-h-[88vh] max-w-full rounded-lg shadow-lift" @click.stop>
                <button type="button" class="absolute top-4 right-4 grid size-10 place-items-center rounded-full border-0 bg-white/15 text-xl text-white hover:bg-white/25" @click="foto = null" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
        </section>
    @endif

    {{-- ======================================================= TESTIMONI --}}
    @if ($testimoni->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Testimoni" title="Kata Mereka tentang Kami" />
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimoni as $t)
                    <figure data-animate="fadeInUp" data-delay="{{ $loop->index * 120 }}" class="flex flex-col rounded-xl border border-slate-200 bg-white p-6">
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

    {{-- ======================================================= AJAKAN BERGABUNG --}}
    <section class="bg-gradient-to-r from-primary-deep to-primary py-16 text-center text-white">
        <div data-animate="zoomIn" class="mx-auto max-w-3xl px-4">
            <h2 class="font-display text-3xl font-extrabold sm:text-4xl">Raih Mimpimu Bersama Kami</h2>
            <p class="mt-3 text-white/80">Bergabunglah dan tumbuh bersama {{ config('school.name') }}.</p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                @if ($ppdbAktif)<a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>@endif
                @if ($wa)
                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-whatsapp"></i> Hubungi via WhatsApp</a>
                @else
                    <a href="{{ route('kontak') }}" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-envelope"></i> Hubungi Kami</a>
                @endif
            </div>
        </div>
    </section>

    {{-- ======================================================= KONTAK --}}
    <section class="bg-white py-14">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
            @foreach ([
                ['geo-alt-fill', 'Alamat', config('school.alamat')],
                ['envelope-fill', 'Email', config('school.email')],
                ['telephone-fill', 'Telepon', config('school.telepon')],
            ] as [$ikon, $label, $nilai])
                <div data-animate="fadeInUp" data-delay="{{ $loop->index * 120 }}" class="flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary-soft text-xl text-primary"><i class="bi bi-{{ $ikon }}"></i></span>
                    <span><span class="block font-display font-bold text-primary">{{ $label }}</span><span class="text-sm text-slate-600">{{ $nilai ?: '-' }}</span></span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
