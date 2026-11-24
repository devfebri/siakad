{{-- Beranda tema "modern": hero membulat, statistik melayang, kartu bento, berita unggulan. --}}
@extends('layouts.public')

@section('description', config('school.hero_deskripsi'))
@if (config('school.hero_gambar'))
    @section('og_image', asset(config('school.hero_gambar')))
@endif

@section('content')
    @php
        $wa = config('school.whatsapp') ? 'https://wa.me/'.preg_replace('/\D/', '', config('school.whatsapp')) : null;
        $ppdbAktif = \App\Support\AksesSekolah::modul('ppdb');
        $konten = \App\Support\AksesSekolah::modul('konten_web');
        $utama = $berita->first();
        $lainnya = $berita->skip(1);
    @endphp

    {{-- ======================================================= HERO --}}
    <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary via-primary to-primary-deep text-white">
            <div class="bg-grid absolute inset-0 -z-10 opacity-60"></div>
            <div class="absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-accent/25 blur-3xl"></div>
            <div class="grid items-center gap-10 px-6 py-14 sm:px-12 lg:grid-cols-2 lg:py-20">
                <div>
                    @if ($ppdb['state'] === 'buka' && $ppdbAktif)
                        <a href="{{ route('ppdb') }}" class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 py-1.5 pr-4 pl-1.5 text-sm font-semibold text-white no-underline ring-1 ring-white/25 transition hover:bg-white/20">
                            <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-bold text-ink">PPDB</span> Pendaftaran {{ $ppdb['tahun_ajaran'] }} dibuka <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                    <h1 class="animate__animated animate__fadeInUp font-display text-4xl leading-[1.1] font-extrabold sm:text-5xl">{{ config('school.hero_judul') }}</h1>
                    <p style="--animate-delay:200ms" class="animate__animated animate__fadeInUp animate__delay-custom mt-5 max-w-xl text-lg leading-relaxed text-white/80">{{ config('school.hero_deskripsi') }}</p>
                    <div style="--animate-delay:400ms" class="animate__animated animate__fadeInUp animate__delay-custom mt-8 flex flex-wrap gap-3">
                        @if ($ppdbAktif)<a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>@endif
                        <a href="{{ route('profil') }}" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15">Kenali Kami <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <div data-animate="zoomIn" class="relative mx-auto w-full max-w-md">
                    <div class="aspect-[4/5] overflow-hidden rounded-[2rem] border-4 border-white/20 bg-white/10 shadow-lift">
                        @if (config('school.hero_gambar'))
                            <img src="{{ asset(config('school.hero_gambar')) }}" alt="{{ config('school.name') }}" class="size-full object-cover" fetchpriority="high">
                        @else
                            <div class="grid size-full place-items-center text-white/30"><i class="bi bi-mortarboard-fill text-[9rem]"></i></div>
                        @endif
                    </div>
                    @if (config('school.akreditasi'))
                        <div class="absolute -bottom-4 -left-4 rounded-2xl bg-white px-5 py-3 text-ink shadow-lift">
                            <p class="font-display text-3xl leading-none font-extrabold text-primary">{{ config('school.akreditasi') }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Akreditasi</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ======================================================= STATISTIK MELAYANG --}}
    <section class="relative z-10 mx-auto -mt-0 max-w-6xl px-4 pt-8 sm:px-6 lg:px-8">
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['Siswa Aktif', $stat['siswa'], 'mortarboard-fill'],
                ['Guru & Tenaga Pendidik', $stat['guru'], 'person-workspace'],
                ['Prestasi', $stat['prestasi'], 'trophy-fill'],
                ['Alumni', $stat['alumni'], 'people-fill'],
            ] as [$label, $nilai, $ikon])
                <div data-animate="fadeInUp" data-delay="{{ $loop->index * 100 }}" class="card flex items-center gap-4 p-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary-soft text-xl text-primary"><i class="bi bi-{{ $ikon }}"></i></span>
                    <span>
                        <dd class="font-display text-2xl leading-none font-extrabold text-primary">{{ $nilai ? number_format($nilai, 0, ',', '.') : '-' }}</dd>
                        <dt class="mt-1 text-xs font-medium text-slate-500">{{ $label }}</dt>
                    </span>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ======================================================= PROGRAM (BENTO) --}}
    @if ($program->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Program" title="Program Unggulan" subtitle="Program yang dirancang agar setiap siswa menemukan dan mengembangkan potensinya." />
            <div class="grid gap-4 md:grid-cols-4 md:grid-rows-2">
                @foreach ($program as $p)
                    <article data-animate="fadeInUp" data-delay="{{ $loop->index * 100 }}" @class([
                        'group relative overflow-hidden rounded-3xl p-6 transition hover:-translate-y-1 hover:shadow-lift',
                        'bg-gradient-to-br from-primary to-primary-deep text-white md:col-span-2 md:row-span-2 md:p-8' => $loop->first,
                        'border border-slate-200 bg-white' => ! $loop->first,
                    ])>
                        <span @class(['grid place-items-center rounded-2xl text-2xl', 'size-14 bg-white/15 text-accent md:size-16 md:text-3xl' => $loop->first, 'size-12 bg-primary-soft text-primary' => ! $loop->first])><i class="bi bi-{{ $p->ikon }}"></i></span>
                        <h3 @class(['mt-5 font-display font-bold', 'text-2xl md:text-3xl' => $loop->first, 'text-lg text-primary' => ! $loop->first])>{{ $p->judul }}</h3>
                        <p @class(['mt-2 leading-relaxed', 'text-white/80 md:text-lg' => $loop->first, 'text-sm text-slate-600' => ! $loop->first])>{{ $p->deskripsi }}</p>
                    </article>
                @endforeach
            </div>
            <div class="mt-8 text-center"><a href="{{ route('akademik') }}" class="btn btn-outline">Lihat Semua <i class="bi bi-arrow-right"></i></a></div>
        </section>
    @endif

    {{-- ======================================================= SAMBUTAN --}}
    <section class="bg-primary-soft py-20">
        <div data-animate="fadeInUp" class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <div class="mx-auto size-28 overflow-hidden rounded-full border-4 border-white bg-white shadow-lift">
                @if (config('school.kepsek_foto'))
                    <img src="{{ asset(config('school.kepsek_foto')) }}" alt="{{ config('school.kepsek_nama') }}" class="size-full object-cover" loading="lazy">
                @else
                    <div class="grid size-full place-items-center text-primary/30"><i class="bi bi-person-fill text-6xl"></i></div>
                @endif
            </div>
            <i class="bi bi-quote mt-6 block text-5xl leading-none text-accent"></i>
            <blockquote class="font-display text-xl leading-relaxed font-semibold text-primary sm:text-2xl">{{ config('school.kepsek_sambutan') }}</blockquote>
            <p class="mt-6 font-display font-bold">{{ config('school.kepsek_nama') ?: 'Kepala Sekolah' }}</p>
            <p class="text-sm text-slate-500">{{ config('school.kepsek_jabatan') }}, {{ config('school.name') }}</p>
        </div>
    </section>

    {{-- ======================================================= BERITA UNGGULAN --}}
    @if ($konten)
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Berita" title="Kabar Terbaru" />
            @if (! $utama)
                <p class="card p-10 text-center text-slate-500">Belum ada berita.</p>
            @else
                <div class="grid gap-6 lg:grid-cols-5">
                    <a href="{{ route('berita.show', $utama->berita_slug) }}" data-animate="fadeInLeft" class="group relative isolate block overflow-hidden rounded-3xl no-underline lg:col-span-3">
                        <img src="{{ $utama->gambar_url }}" alt="" loading="lazy" class="aspect-[16/11] size-full object-cover transition duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary-deep/95 via-primary-deep/30 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-6 text-white sm:p-8">
                            @if ($utama->kategori)<span class="badge badge-accent">{{ $utama->kategori->kategori_nama }}</span>@endif
                            <h3 class="mt-3 font-display text-2xl leading-snug font-extrabold sm:text-3xl">{{ $utama->berita_judul }}</h3>
                            <p class="mt-2 text-sm text-white/75"><i class="bi bi-calendar3"></i> {{ ($utama->created_at ?? $utama->berita_tanggal)?->translatedFormat('d M Y') }}</p>
                        </div>
                    </a>
                    <div class="flex flex-col gap-4 lg:col-span-2">
                        @foreach ($lainnya as $b)
                            <a href="{{ route('berita.show', $b->berita_slug) }}" data-animate="fadeInRight" data-delay="{{ $loop->index * 120 }}" class="card card-lift group flex flex-1 gap-4 p-3 no-underline">
                                <img src="{{ $b->gambar_url }}" alt="" loading="lazy" class="size-28 shrink-0 rounded-xl object-cover sm:size-32">
                                <span class="flex min-w-0 flex-col justify-center">
                                    <span class="text-xs text-slate-500"><i class="bi bi-calendar3"></i> {{ ($b->created_at ?? $b->berita_tanggal)?->translatedFormat('d M Y') }}</span>
                                    <span class="mt-1 line-clamp-2 font-display font-bold text-ink transition group-hover:text-primary">{{ $b->berita_judul }}</span>
                                    <span class="mt-1 line-clamp-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($b->berita_isi), 90) }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mt-8 text-center"><a href="{{ route('berita') }}" class="btn btn-primary">Semua Berita <i class="bi bi-arrow-right"></i></a></div>

            @if ($pengumuman->isNotEmpty())
                <div data-animate="fadeInUp" class="mt-12 grid gap-4 md:grid-cols-3">
                    @foreach ($pengumuman as $p)
                        <a href="{{ route('pengumuman') }}" class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 no-underline transition hover:border-primary/40 hover:shadow-soft">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent-dark"><i class="bi bi-megaphone-fill"></i></span>
                            <span class="min-w-0"><span class="block line-clamp-2 text-sm font-bold text-ink">{{ $p->pengumuman_judul }}</span><span class="text-xs text-slate-400">{{ ($p->created_at ?? $p->pengumuman_tanggal)?->translatedFormat('d M Y') }}</span></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- ======================================================= GALERI --}}
    @if ($konten && $galeri->isNotEmpty())
        <section class="bg-white py-20" x-data="{ foto: null }" @keydown.escape.window="foto = null">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-public.section eyebrow="Galeri" title="Momen Kegiatan Kami" />
                <div class="columns-2 gap-3 md:columns-4 [&>*]:mb-3">
                    @foreach ($galeri->take(8) as $g)
                        <button type="button" @click="foto = @js(asset('storage/gallery/'.$g->foto))" class="group relative block w-full cursor-zoom-in overflow-hidden rounded-2xl border-0 bg-slate-100 p-0">
                            <img src="{{ asset('storage/gallery/'.$g->foto) }}" alt="Foto kegiatan sekolah" loading="lazy" class="w-full object-cover transition duration-500 group-hover:scale-105">
                        </button>
                    @endforeach
                </div>
                <div class="mt-8 text-center"><a href="{{ route('galeri') }}" class="btn btn-outline">Galeri Lengkap <i class="bi bi-arrow-right"></i></a></div>
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
            <x-public.section eyebrow="Testimoni" title="Kata Mereka tentang Kami" />
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimoni as $t)
                    <figure data-animate="fadeInUp" data-delay="{{ $loop->index * 100 }}" class="flex flex-col rounded-3xl bg-primary-soft p-6">
                        <i class="bi bi-quote text-4xl leading-none text-accent"></i>
                        <blockquote class="mt-1 flex-1 text-slate-700">{{ $t->isi }}</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
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
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div data-animate="zoomIn" class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary to-primary-deep px-6 py-14 text-center text-white sm:px-12">
            <div class="absolute -bottom-20 -left-20 -z-10 size-80 rounded-full bg-accent/25 blur-3xl"></div>
            <h2 class="font-display text-3xl font-extrabold sm:text-4xl">Siap Tumbuh Bersama Kami?</h2>
            <p class="mx-auto mt-3 max-w-xl text-white/80">Bergabunglah dengan keluarga besar {{ config('school.name') }}.</p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                @if ($ppdbAktif)<a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>@endif
                @if ($wa)
                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                @else
                    <a href="{{ route('kontak') }}" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-envelope"></i> Hubungi Kami</a>
                @endif
            </div>
        </div>
    </section>
@endsection
