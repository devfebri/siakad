@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard Kepala Sekolah" subtitle="Ringkasan {{ config('school.name') }} (hanya-baca)." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Siswa" :value="$stats['siswa']" icon="mortarboard" color="primary" />
        <x-admin.stat-card label="Guru" :value="$stats['guru']" icon="person-workspace" color="accent" />
        <x-admin.stat-card label="Rombel" :value="$stats['kelas']" icon="door-open" color="emerald" :href="route('kepsek/kelas')" />
        <x-admin.stat-card label="Kehadiran Hari Ini" :value="$kehadiranHariIni === null ? 'Belum ada' : number_format($kehadiranHariIni, 1, ',', '.').'%'" icon="clipboard2-check" color="sky" :href="route('kepsek/kehadiran')" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">Kehadiran Mingguan <span class="text-xs font-normal text-slate-500">% siswa hadir</span></div>
            <div class="p-5">
                @php
                    $grafikHadir = [
                        'type' => 'line',
                        'data' => ['labels' => $kehadiranMingguan['labels'], 'datasets' => [[
                            'label' => 'Hadir (%)', 'data' => $kehadiranMingguan['data'], 'borderColor' => '#1e3a8a', 'backgroundColor' => 'rgba(30,58,138,.12)',
                            'fill' => true, 'tension' => .35, 'pointBackgroundColor' => \App\Support\Tema::aksen(), 'spanGaps' => true,
                        ]]],
                        'options' => ['scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]],
                    ];
                @endphp
                <canvas data-chart='@json($grafikHadir)' aria-label="Grafik kehadiran mingguan" role="img" height="150"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Rata-rata Nilai per Rombel <span class="text-xs font-normal text-slate-500">semester aktif</span></div>
            <div class="p-5">
                @if (count($nilaiPerKelas['data']))
                    @php
                        $grafikNilai = [
                            'type' => 'bar',
                            'data' => ['labels' => $nilaiPerKelas['labels'], 'datasets' => [['label' => 'Rata-rata', 'data' => $nilaiPerKelas['data'], 'backgroundColor' => '#1e3a8a', 'borderRadius' => 8]]],
                            'options' => ['scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]],
                        ];
                    @endphp
                    <canvas data-chart='@json($grafikNilai)' aria-label="Grafik rata-rata nilai per rombel" role="img" height="150"></canvas>
                @else
                    <p class="py-12 text-center text-sm text-slate-500">Belum ada nilai pada semester ini.</p>
                @endif
            </div>
        </div>
    </div>

    @include('dashboard._pengumuman')
@endsection
