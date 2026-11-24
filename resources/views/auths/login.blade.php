@extends('layouts.guest')

@section('title', 'Masuk')

@php
    $kutipan = [
        ['Pendidikan adalah senjata paling ampuh yang dapat kamu gunakan untuk mengubah dunia.', 'Nelson Mandela'],
        ['Belajar tanpa berpikir itu tidak berguna, tetapi berpikir tanpa belajar itu berbahaya.', 'Konfusius'],
        ['Tujuan pendidikan adalah memerdekakan manusia dari ketidaktahuan.', 'Ki Hajar Dewantara'],
        ['Masa depan milik mereka yang percaya pada keindahan mimpinya.', 'Eleanor Roosevelt'],
        ['Ilmu itu seperti cahaya: menuntun langkah dan menerangi sekitar.', 'Pepatah'],
    ];
    [$teks, $penulis] = $kutipan[now()->dayOfYear % count($kutipan)];
@endphp

@section('content')
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Sisi kiri: identitas sekolah --}}
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-primary via-primary to-primary-deep p-12 text-white lg:flex">
            <div class="bg-grid pointer-events-none absolute inset-0"></div>
            {{-- Ikon pendidikan melayang (CSS saja, tanpa library) --}}
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                @foreach ([
                    ['book-half', 8, 12, 3.2, 0, 9, 'text-accent/40'], ['pencil-fill', 78, 8, 2.2, 1.5, 11, 'text-accent/35'],
                    ['mortarboard-fill', 62, 30, 3.6, 3, 13, 'text-white/20'], ['journal-bookmark', 15, 55, 2.8, 2, 10, 'text-white/20'],
                    ['calculator', 85, 52, 2.4, 4, 12, 'text-accent/30'], ['globe-asia-australia', 45, 72, 3.4, 1, 14, 'text-white/15'],
                    ['lightbulb', 28, 82, 2.4, 5, 9, 'text-accent/35'], ['rulers', 90, 82, 2.6, 2.5, 11, 'text-white/20'],
                    ['book', 40, 6, 2.2, 6, 10, 'text-white/20'], ['palette', 6, 32, 2, 3.5, 12, 'text-accent/25'],
                ] as [$ikon, $x, $y, $ukuran, $tunda, $durasi, $warna])
                    <i class="bi bi-{{ $ikon }} login-float absolute {{ $warna }}" style="left: {{ $x }}%; top: {{ $y }}%; font-size: {{ $ukuran }}rem; animation-delay: -{{ $tunda }}s; animation-duration: {{ $durasi }}s"></i>
                @endforeach
                @foreach (range(1, 14) as $i)
                    <span class="login-dot absolute rounded-full bg-white/30" style="left: {{ ($i * 37) % 100 }}%; bottom: -10px; width: {{ 3 + $i % 4 }}px; height: {{ 3 + $i % 4 }}px; animation-delay: -{{ $i * 1.3 }}s; animation-duration: {{ 9 + $i % 6 }}s"></span>
                @endforeach
            </div>

            <div class="relative flex items-center gap-3">
                <span class="grid size-14 place-items-center overflow-hidden rounded-2xl bg-accent text-3xl text-ink shadow-lg">
                    @if (config('school.logo'))
                        <img src="{{ asset(config('school.logo')) }}" alt="" class="size-full bg-white object-contain">
                    @else
                        <i class="bi bi-mortarboard-fill"></i>
                    @endif
                </span>
                <div>
                    <p class="font-display text-xl leading-tight font-extrabold">{{ config('school.name') }}</p>
                    <p class="text-sm text-white/65">{{ config('school.tagline') }}</p>
                </div>
            </div>

            <figure class="relative max-w-lg">
                <i class="bi bi-quote text-6xl leading-none text-accent"></i>
                <blockquote class="font-display text-3xl leading-snug font-bold">{{ $teks }}</blockquote>
                <figcaption class="mt-4 text-sm font-semibold text-accent">— {{ $penulis }}</figcaption>
            </figure>

            <p class="relative text-sm text-white/50">&copy; {{ date('Y') }} {{ config('school.name') }}</p>
        </aside>

        {{-- Sisi kanan: formulir --}}
        <main class="flex items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary text-2xl text-accent"><i class="bi bi-mortarboard-fill"></i></span>
                    <p class="font-display text-lg leading-tight font-extrabold text-primary">{{ config('school.name') }}</p>
                </div>

                <h1 class="font-display text-3xl font-extrabold">Selamat datang kembali</h1>
                <p class="mt-2 text-slate-500">Masuk untuk mengelola data sekolah, kelas, dan pembelajaran.</p>

                <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" novalidate>
                    @csrf

                    <div>
                        <label for="username" class="label">Username</label>
                        <div class="relative">
                            <i class="bi bi-person pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="50" autofocus required
                                   class="input pl-10 @error('username') is-invalid @enderror" placeholder="username Anda">
                        </div>
                        @error('username')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="{ show: false }">
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="label mb-0">Kata sandi</label>
                            <a href="#" class="text-xs font-semibold text-primary no-underline hover:underline" x-data @click.prevent="Swal.fire({ icon: 'info', title: 'Lupa kata sandi?', text: 'Hubungi admin sekolah untuk mengatur ulang kata sandi Anda.', confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand') })">Lupa kata sandi?</a>
                        </div>
                        <div class="relative">
                            <i class="bi bi-lock pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"></i>
                            <input :type="show ? 'text' : 'password'" id="password" name="password" autocomplete="current-password" required
                                   class="input px-10 @error('password') is-invalid @enderror">
                            <button type="button" class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-1 text-slate-400 hover:text-primary" @click="show = !show" :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="check"> Ingat saya
                    </label>

                    <button type="submit" class="btn btn-primary btn-lg btn-block"><i class="bi bi-box-arrow-in-right"></i> Masuk</button>
                </form>

                <a href="{{ url('/') }}" class="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 no-underline hover:text-primary"><i class="bi bi-arrow-left"></i> Kembali ke situs</a>
            </div>
        </main>
    </div>
@endsection
