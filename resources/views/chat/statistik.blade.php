@extends('layouts.admin')

@section('title', 'Statistik Konsultasi')

@php
    $menit = fn (?float $m) => $m === null ? '-' : ($m >= 60 ? number_format($m / 60, 1, ',', '.').' jam' : number_format($m, 1, ',', '.').' mnt');
@endphp

@section('content')
    <x-admin.page-header title="Konsultasi Online" subtitle="Statistik layanan {{ \Illuminate\Support\Carbon::parse($dari)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->translatedFormat('d M Y') }}." />

    <x-admin.subnav :items="[['Percakapan Langsung', route('admin/chat'), ['admin/chat']], ['Riwayat', route('admin/chat.riwayat'), ['admin/chat/riwayat*']], ['Statistik', route('admin/chat.statistik'), ['admin/chat/statistik']], ['Pengaturan', route('admin/chat.pengaturan'), ['admin/chat/pengaturan']]]" />

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4" aria-label="Rentang tanggal">
        <div><label class="label" for="s-dari">Dari</label><input id="s-dari" type="date" name="dari" value="{{ $dari }}" class="input"></div>
        <div><label class="label" for="s-sampai">Sampai</label><input id="s-sampai" type="date" name="sampai" value="{{ $sampai }}" class="input"></div>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
        @if ($terpotong)<p class="text-xs font-semibold text-amber-700">Data dipotong pada 50.000 percakapan pertama.</p>@endif
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Total chat" :value="$ringkas['total']" icon="chat-dots" color="primary" :hint="$ringkas['selesai'].' selesai · '.$ringkas['diteruskan'].' via email'" />
        <x-admin.stat-card label="Waktu tunggu" :value="$menit($ringkas['tunggu'])" icon="hourglass-split" color="accent" hint="rata-rata sampai diambil" />
        <x-admin.stat-card label="Durasi layanan" :value="$menit($ringkas['durasi'])" icon="stopwatch" color="sky" hint="rata-rata diambil–selesai" />
        <x-admin.stat-card label="Rating" :value="$ringkas['rating'] === null ? '-' : number_format($ringkas['rating'], 2, ',', '.').' / 5'" icon="star" color="emerald" :hint="$ringkas['jumlah_rating'].' penilaian'" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header">Chat per Hari</div>
            <div class="p-5">
                @php
                    $grafikHari = [
                        'type' => 'bar',
                        'data' => ['labels' => collect(array_keys($perHari))->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'))->values(), 'datasets' => [['label' => 'Chat', 'data' => array_values($perHari), 'backgroundColor' => '#1e3a8a', 'borderRadius' => 6]]],
                        'options' => ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]],
                    ];
                @endphp
                <canvas data-chart='@json($grafikHari)' aria-label="Grafik chat per hari" role="img" height="130"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Topik</div>
            <div class="p-5">
                @if ($topik->isNotEmpty())
                    @php
                        $grafikTopik = [
                            'type' => 'doughnut',
                            'data' => ['labels' => $topik->keys()->values(), 'datasets' => [['data' => $topik->values(), 'backgroundColor' => [\App\Support\Tema::warna(), \App\Support\Tema::aksen(), '#10b981', '#0ea5e9', '#e11d48', '#8b5cf6'], 'borderWidth' => 0]]],
                            'options' => ['cutout' => '62%', 'plugins' => ['legend' => ['position' => 'bottom']]],
                        ];
                    @endphp
                    <canvas data-chart='@json($grafikTopik)' aria-label="Grafik topik" role="img"></canvas>
                @else
                    <p class="py-10 text-center text-sm text-slate-500">Belum ada data.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="card-header">Sebaran Rating</div>
            <div class="p-5">
                @php
                    $grafikRating = [
                        'type' => 'bar',
                        'data' => ['labels' => ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'], 'datasets' => [['label' => 'Jumlah', 'data' => $distribusiRating->values(), 'backgroundColor' => \App\Support\Tema::aksen(), 'borderRadius' => 6]]],
                        'options' => ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]],
                    ];
                @endphp
                <canvas data-chart='@json($grafikRating)' aria-label="Sebaran rating" role="img" height="160"></canvas>
            </div>
        </div>
        <div class="card overflow-x-auto lg:col-span-2">
            <div class="card-header">Kinerja Petugas</div>
            <div class="p-2">
                <table class="table-clean">
                    <thead><tr><th>Petugas</th><th>Chat</th><th>Selesai</th><th>Durasi rata-rata</th><th>Rating</th></tr></thead>
                    <tbody>
                        @forelse ($petugas as $p)
                            <tr>
                                <td class="font-semibold">{{ $p['nama'] }}</td>
                                <td>{{ $p['jumlah'] }}</td>
                                <td>{{ $p['selesai'] }}</td>
                                <td>{{ $menit($p['durasi']) }}</td>
                                <td class="text-accent-dark">{{ $p['rating'] === null ? '-' : '★ '.number_format($p['rating'], 2, ',', '.').' ('.$p['jumlah_rating'].')' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada chat yang dilayani petugas pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
