@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard" subtitle="Pantau perkembangan anak Anda di {{ config('school.name') }}." />

    @if (! $anak)
        <div class="card p-10 text-center text-slate-500">
            <i class="bi bi-people text-4xl text-slate-300"></i>
            <p class="mt-3 font-semibold text-ink">Belum ada siswa yang terhubung dengan akun Anda.</p>
            <p class="text-sm">Hubungi admin sekolah untuk menghubungkan akun ini dengan data anak Anda.</p>
        </div>
    @else
        @include('ortu._anak', ['daftarAnak' => $daftarAnak, 'anak' => $anak])

        <div class="card mb-6 flex flex-wrap items-center gap-4 p-5">
            <span class="grid size-14 place-items-center rounded-2xl bg-primary text-2xl text-accent"><i class="bi bi-person-fill"></i></span>
            <div class="min-w-0">
                <p class="font-display text-lg font-bold">{{ $anak->nama_siswa }}</p>
                <p class="text-sm text-slate-500">NISN {{ $anak->nisn }} · Kelas {{ $anak->kelas?->nama_kelas ?? 'belum ditempatkan' }}</p>
            </div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-admin.stat-card label="Kelas" :value="$anak->kelas?->nama_kelas ?? '-'" icon="door-open" color="emerald" />
            <x-admin.stat-card label="Wali Kelas" :value="$wali?->nama_guru ?? '-'" icon="person-hearts" color="violet" />
            <x-admin.stat-card label="Kehadiran Bulan Ini" :value="$kehadiran['persen'] === null ? '-' : number_format($kehadiran['persen'], 0).'%'" icon="clipboard2-check" color="sky" :href="route('ortu/kehadiran')" />
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3">@include('dashboard._jadwal', ['tampilKelas' => false])</div>
            <div class="lg:col-span-2">@include('dashboard._pengumuman')</div>
        </div>
    @endif
@endsection
