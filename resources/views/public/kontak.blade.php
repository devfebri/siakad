@extends('layouts.public')

@section('title', 'Kontak')

@php
    $wa = config('school.whatsapp') ? 'https://wa.me/'.preg_replace('/\D/', '', config('school.whatsapp')) : null;
    $kartu = array_filter([
        ['geo-alt-fill', 'Alamat', config('school.alamat'), null],
        ['telephone-fill', 'Telepon', config('school.telepon'), config('school.telepon') ? 'tel:'.preg_replace('/[^0-9+]/', '', config('school.telepon')) : null],
        ['envelope-fill', 'Email', config('school.email'), config('school.email') ? 'mailto:'.config('school.email') : null],
        ['clock-fill', 'Jam Layanan', config('school.jam_layanan'), null],
    ], fn ($k) => filled($k[2]));
@endphp

@section('content')
    <x-public.page-title title="Hubungi Kami" subtitle="Kami senang membantu Anda dengan informasi sekolah dan PPDB." crumb="Kontak" />

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($kartu as [$ikon, $label, $nilai, $tautan])
                <div class="card card-lift p-6">
                    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-primary text-2xl text-accent"><i class="bi bi-{{ $ikon }}"></i></span>
                    <h2 class="font-display font-bold">{{ $label }}</h2>
                    @if ($tautan)<a href="{{ $tautan }}" class="mt-1 block text-sm break-words text-slate-600 no-underline hover:text-primary">{{ $nilai }}</a>
                    @else<p class="mt-1 text-sm text-slate-600">{{ $nilai }}</p>@endif
                </div>
            @empty
                <p class="card p-10 text-center text-slate-500 sm:col-span-2 lg:col-span-4">Informasi kontak belum diatur.</p>
            @endforelse
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="card overflow-hidden lg:col-span-2">
                @if (config('school.peta_embed_url'))
                    <iframe src="{{ config('school.peta_embed_url') }}" class="h-96 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi sekolah" allowfullscreen></iframe>
                @else
                    <div class="grid h-96 place-items-center bg-slate-50 text-slate-400"><span class="text-center"><i class="bi bi-map block text-5xl"></i>Peta belum diatur</span></div>
                @endif
            </div>
            <div class="card flex flex-col justify-center p-8 text-center">
                <span class="mx-auto mb-4 grid size-16 place-items-center rounded-2xl bg-emerald-50 text-4xl text-emerald-600"><i class="bi bi-whatsapp"></i></span>
                <h2 class="font-display text-xl font-bold">Tanya via WhatsApp</h2>
                <p class="mt-2 text-sm text-slate-600">Respons cepat pada jam layanan untuk pertanyaan seputar sekolah dan PPDB.</p>
                @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success btn-lg mt-6"><i class="bi bi-whatsapp"></i> Chat Sekarang</a>
                @else<p class="mt-5 text-sm text-slate-400">Nomor WhatsApp belum diatur.</p>@endif
            </div>
        </div>
    </div>
@endsection
