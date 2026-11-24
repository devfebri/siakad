@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard" subtitle="{{ $siswa?->kelas ? 'Kelas '.$siswa->kelas->nama_kelas.' · ' : '' }}Semangat belajar hari ini!" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Kelas" :value="$siswa?->kelas?->nama_kelas ?? '-'" icon="door-open" color="emerald" :href="route('siswa/kelas')" />
        <x-admin.stat-card label="Mata Pelajaran" :value="$stats['mapel']" icon="journal-bookmark" color="violet" :href="route('siswa/jadwal')" />
        <x-admin.stat-card label="Kehadiran Bulan Ini" :value="$kehadiran['persen'] === null ? '-' : number_format($kehadiran['persen'], 0).'%'" icon="clipboard2-check" color="sky" :href="route('siswa/kehadiran')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">@include('dashboard._jadwal', ['tampilKelas' => false])</div>
        <div class="lg:col-span-2">@include('dashboard._pengumuman')</div>
    </div>
@endsection
