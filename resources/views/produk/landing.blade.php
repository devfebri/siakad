@extends('layouts.landing')

@section('title', 'Aplikasi Sekolah Lengkap')

@section('content')
    {{-- ======================================================= HERO --}}
    <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary via-primary to-primary-deep px-6 py-16 text-center text-white sm:px-12 lg:py-24">
            <div class="bg-grid absolute inset-0 -z-10 opacity-60"></div>
            <div class="absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-accent/25 blur-3xl"></div>
            @if ($trial)
                <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold ring-1 ring-white/25"><i class="bi bi-gift-fill text-accent"></i> Coba gratis {{ $trial }} hari, tanpa kartu</p>
            @endif
            <h1 class="mx-auto max-w-3xl font-display text-4xl leading-[1.1] font-extrabold sm:text-5xl">Urus data, absensi, nilai, dan website sekolah di satu aplikasi</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-white/85">Mudah dipakai guru dan tata usaha. Tidak perlu pasang aplikasi atau beli server. Sekolah Anda langsung punya alamat sendiri.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('daftar') }}" class="btn btn-accent btn-lg"><i class="bi bi-rocket-takeoff"></i> {{ $trial ? 'Coba Gratis Sekarang' : 'Daftarkan Sekolah' }}</a>
                <a href="#harga" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15">Lihat Harga</a>
            </div>
        </div>
    </section>

    {{-- ======================================================= FITUR --}}
    <section id="fitur" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 lg:px-8">
        <x-public.section eyebrow="Fitur" title="Semua yang dibutuhkan sekolah" subtitle="Dibuat sederhana supaya bisa dipakai siapa saja, termasuk yang belum terbiasa dengan komputer." />
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($fitur as $f)
                <div data-animate="fadeInUp" data-delay="{{ ($loop->index % 3) * 100 }}" class="card p-6">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary-soft text-2xl text-primary"><i class="bi bi-{{ $f['icon'] }}"></i></span>
                    <h3 class="mt-4 font-display text-lg font-bold">{{ $f['judul'] }}</h3>
                    <p class="mt-1 text-slate-600">{{ $f['isi'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ======================================================= HARGA --}}
    <section id="harga" class="scroll-mt-20 bg-primary-soft py-20" x-data="{ durasi: 12 }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-public.section eyebrow="Harga" title="Pilih paket yang cocok" subtitle="Makin lama langganan, makin hemat. Bisa pindah paket kapan saja." />

            <div class="mb-8 flex justify-center">
                <div class="inline-flex rounded-xl bg-white p-1 shadow-soft" role="group" aria-label="Lama langganan">
                    @foreach (\App\Paket::DURASI as $d)
                        <button type="button" @click="durasi = {{ $d }}" class="cursor-pointer rounded-lg border-0 px-4 py-2 text-sm font-semibold transition" :class="durasi === {{ $d }} ? 'bg-primary text-white' : 'bg-transparent text-slate-600'">{{ $d }} bulan</button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-6 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2'][$paket->count()] ?? 'lg:grid-cols-3' }}">
                @foreach ($paket as $p)
                    @php $sorot = $paket->count() >= 3 && $loop->iteration === 2; @endphp
                    <div @class(['card relative flex flex-col p-7', 'ring-2 ring-accent' => $sorot])>
                        @if ($sorot)<span class="badge badge-accent absolute -top-3 left-6">Paling populer</span>@endif
                        <h3 class="font-display text-2xl font-extrabold text-primary">{{ $p->nama }}</h3>
                        <p class="mt-1 min-h-12 text-sm text-slate-600">{{ $p->deskripsi }}</p>
                        <div class="mt-5">
                            @foreach (\App\Paket::DURASI as $d)
                                @if ($p->hargaUntuk($d) !== null)
                                    <p x-show="durasi === {{ $d }}" @if ($d !== 12) x-cloak @endif>
                                        <span class="font-display text-4xl font-extrabold">Rp {{ number_format($p->hargaUntuk($d), 0, ',', '.') }}</span>
                                        <span class="text-sm text-slate-500">/ {{ $d }} bulan</span>
                                        @if ($d > 1 && $p->hargaUntuk(1))
                                            <span class="mt-1 block text-xs font-semibold text-emerald-600">Setara Rp {{ number_format($p->hargaUntuk($d) / $d, 0, ',', '.') }} per bulan</span>
                                        @endif
                                    </p>
                                @endif
                            @endforeach
                        </div>
                        <ul class="mt-5 flex-1 space-y-2 text-sm">
                            <li class="flex gap-2"><i class="bi bi-check-circle-fill text-emerald-500"></i> Data siswa, guru, kelas, dan jadwal</li>
                            @foreach ($modul as $m)
                                @php $ada = in_array($m->kode, $p->kodeModul(), true); @endphp
                                <li @class(['flex gap-2', 'text-slate-400 line-through' => ! $ada])><i @class(['bi', 'bi-check-circle-fill text-emerald-500' => $ada, 'bi-dash-circle' => ! $ada])></i> {{ $m->nama }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('daftar', ['paket' => $p->id]) }}" @class(['btn btn-block mt-6', 'btn-accent' => $sorot, 'btn-primary' => ! $sorot])>Pilih {{ $p->nama }}</a>
                    </div>
                @endforeach
            </div>
            @if ($trial)<p class="mt-6 text-center text-sm text-slate-600"><i class="bi bi-gift"></i> Semua paket bisa dicoba gratis {{ $trial }} hari sebelum membayar.</p>@endif
        </div>
    </section>

    {{-- ======================================================= FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-20 sm:px-6">
        <x-public.section eyebrow="Pertanyaan" title="Pertanyaan yang sering diajukan" />
        <div class="space-y-3">
            @foreach ($faq as $f)
                <details class="card group p-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-display font-bold">{{ $f['t'] }}<i class="bi bi-plus-lg text-primary transition group-open:rotate-45"></i></summary>
                    <p class="mt-3 text-slate-600">{{ $f['j'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- ======================================================= KONTAK --}}
    <section id="kontak" class="mx-auto max-w-7xl scroll-mt-20 px-4 pb-20 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] bg-gradient-to-br from-primary to-primary-deep px-6 py-14 text-center text-white sm:px-12">
            <h2 class="font-display text-3xl font-extrabold">Masih bingung? Kami bantu.</h2>
            <p class="mx-auto mt-3 max-w-xl text-white/80">Tanyakan apa saja, kami jawab dengan bahasa yang mudah dimengerti.</p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                @if ($kontak['wa'])<a href="https://wa.me/{{ preg_replace('/\D/', '', $kontak['wa']) }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg"><i class="bi bi-whatsapp"></i> Chat WhatsApp</a>@endif
                @if ($kontak['email'])<a href="mailto:{{ $kontak['email'] }}" class="btn btn-lg border border-white/40 bg-white/5 text-white hover:bg-white/15"><i class="bi bi-envelope"></i> {{ $kontak['email'] }}</a>@endif
                @if (! $kontak['wa'] && ! $kontak['email'])<a href="{{ route('daftar') }}" class="btn btn-accent btn-lg">Daftarkan Sekolah</a>@endif
            </div>
        </div>
    </section>
@endsection
