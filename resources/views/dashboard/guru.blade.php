@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard" subtitle="Jadwal mengajar, absensi, dan penilaian Anda." />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Siswa" :value="$stats['siswa']" icon="mortarboard" color="primary" />
        <x-admin.stat-card label="Kelas" :value="$stats['kelas']" icon="door-open" color="emerald" :href="route('guru/kelas')" />
        <x-admin.stat-card label="Mata Pelajaran" :value="$stats['mapel']" icon="journal-bookmark" color="violet" :href="route('guru/mapel')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">@include('dashboard._jadwal')</div>
        <div class="lg:col-span-2">@include('dashboard._pengumuman')</div>
    </div>
@endsection
