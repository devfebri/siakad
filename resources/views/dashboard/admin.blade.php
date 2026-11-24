@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard" subtitle="Ringkasan data {{ config('school.name') }}." />

    @unless ($setupSelesai)
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-accent/40 bg-accent/10 p-4">
            <i class="bi bi-rocket-takeoff text-2xl text-accent-dark"></i>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">Selesaikan pengaturan awal sekolah</p>
                <p class="text-sm text-slate-600">Atur identitas, tahun ajaran, mata pelajaran, rombel, lalu impor guru dan siswa dari Excel.</p>
            </div>
            <a href="{{ route('admin/setup') }}" class="btn btn-accent btn-sm">Lanjutkan</a>
        </div>
    @endunless

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Siswa" :value="$stats['siswa']" icon="mortarboard" color="primary" :href="route('admin/siswa')" />
        <x-admin.stat-card label="Guru" :value="$stats['guru']" icon="person-workspace" color="accent" :href="route('admin/guru')" />
        <x-admin.stat-card label="Kelas" :value="$stats['kelas']" icon="door-open" color="emerald" :href="route('admin/kelas')" />
        <x-admin.stat-card label="Kehadiran Hari Ini" :value="$kehadiranHariIni === null ? 'Belum ada' : number_format($kehadiranHariIni, 1, ',', '.').'%'" icon="clipboard2-check" color="sky" :href="route('admin/absensi')" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-5">
        <div class="{{ \App\Support\AksesSekolah::modul('ppdb') ? 'lg:col-span-2' : 'lg:col-span-5' }}">@include('dashboard._jadwal')</div>
        @if (\App\Support\AksesSekolah::modul('ppdb'))
        <div class="card lg:col-span-3">
            <div class="card-header">Pendaftar PPDB Terbaru <a href="{{ route('admin/ppdb') }}" class="btn btn-soft btn-sm">Lihat semua</a></div>
            <div class="overflow-x-auto p-2">
                <table class="table-clean">
                    <thead><tr><th>Nama</th><th>Jalur</th><th>Status</th><th>Daftar</th></tr></thead>
                    <tbody>
                        @forelse ($pendaftarTerbaru as $p)
                            <tr>
                                <td class="font-semibold">{{ $p->nama }}</td>
                                <td class="text-slate-600">{{ $p->jalur }}</td>
                                <td><span class="badge badge-{{ \App\PpdbPendaftar::STATUS[$p->status]['badge'] ?? 'muted' }}">{{ $p->status_label }}</span></td>
                                <td class="text-slate-500">{{ $p->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada pendaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    @include('dashboard._pengumuman')
@endsection
