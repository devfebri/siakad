@extends('layouts.public')

@section('title', 'Pendaftaran Berhasil')

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6">
        <span class="mx-auto mb-6 grid size-20 place-items-center rounded-full bg-emerald-50 text-5xl text-emerald-500"><i class="bi bi-check-circle-fill"></i></span>
        <h1 class="font-display text-3xl font-extrabold">Pendaftaran berhasil dikirim!</h1>
        <p class="mt-3 text-slate-600">Terima kasih, <strong>{{ $nama }}</strong>. Simpan nomor pendaftaran berikut untuk mengecek status.</p>

        <div class="card mx-auto mt-8 max-w-md border-2 border-dashed border-accent bg-accent-soft/40 p-6">
            <p class="text-xs font-bold tracking-widest text-slate-500 uppercase">Nomor Pendaftaran</p>
            <p class="mt-1 font-display text-3xl font-extrabold tracking-wide text-primary" id="no">{{ $no }}</p>
        </div>

        <ul class="mx-auto mt-8 max-w-md space-y-2 text-left text-sm text-slate-600">
            <li class="flex gap-2"><i class="bi bi-1-circle-fill text-accent-dark"></i> Panitia akan memverifikasi data Anda.</li>
            <li class="flex gap-2"><i class="bi bi-2-circle-fill text-accent-dark"></i> Cek status dengan nomor pendaftaran dan tanggal lahir.</li>
            <li class="flex gap-2"><i class="bi bi-3-circle-fill text-accent-dark"></i> Pantau pengumuman untuk jadwal berikutnya.</li>
        </ul>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('ppdb.cek') }}" class="btn btn-primary"><i class="bi bi-search"></i> Cek Status</a>
            <button type="button" class="btn btn-outline" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
            <a href="{{ route('beranda') }}" class="btn btn-ghost">Kembali ke beranda</a>
        </div>
    </section>
@endsection
