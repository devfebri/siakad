@extends('layouts.landing')

@section('title', 'Cek Email Anda')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <span class="mx-auto grid size-20 place-items-center rounded-full bg-primary-soft text-4xl text-primary"><i class="bi bi-envelope-paper-heart"></i></span>
        <h1 class="mt-6 font-display text-3xl font-extrabold text-primary">Cek email Anda</h1>
        <p class="mt-3 text-lg text-slate-600">
            @if ($email) Kami sudah mengirim email ke <strong>{{ $email }}</strong>. @else Kami sudah mengirim email aktivasi. @endif
            Buka email itu lalu tekan tombol <strong>"Aktifkan Sekolah Saya"</strong>.
        </p>
        <ul class="mx-auto mt-6 max-w-md space-y-2 text-left text-sm text-slate-600">
            <li class="flex gap-2"><i class="bi bi-clock text-primary"></i> Tombol berlaku {{ \App\Support\Pendaftaran::BERLAKU_JAM }} jam.</li>
            <li class="flex gap-2"><i class="bi bi-inbox text-primary"></i> Belum ada? Lihat juga folder Spam atau Promosi.</li>
            <li class="flex gap-2"><i class="bi bi-arrow-repeat text-primary"></i> Salah email? <a href="{{ route('daftar') }}">Daftar ulang</a> dengan email yang benar.</li>
        </ul>
    </div>
@endsection
