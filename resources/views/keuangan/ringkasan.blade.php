@extends('layouts.admin')

@section('title', 'Keuangan')

@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); @endphp

@section('content')
    @include('keuangan._tab')
    <x-admin.page-header title="Keuangan" subtitle="Ringkasan tagihan dan pembayaran sekolah.">
        <a href="{{ route($panel.'/keuangan/tagihan') }}" class="btn btn-primary"><i class="bi bi-receipt"></i> Kelola Tagihan</a>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Belum dibayar" :value="$rp($r['sisa_tagihan'])" icon="hourglass-split" color="rose" />
        <x-admin.stat-card label="Siswa menunggak" :value="$r['jumlah_menunggak']" icon="exclamation-triangle" color="accent" />
        <x-admin.stat-card label="Masuk hari ini" :value="$rp($r['tertagih_hari_ini'])" icon="cash-coin" color="emerald" />
        <x-admin.stat-card label="Masuk bulan ini" :value="$rp($r['tertagih_bulan_ini'])" icon="graph-up-arrow" color="sky" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">Pembayaran terbaru <a href="{{ route($panel.'/keuangan/pembayaran') }}" class="btn btn-soft btn-sm">Lihat semua</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($terbaru as $b)
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ $b->tagihan?->siswa?->nama_siswa }}</span><span class="text-xs text-slate-500">{{ $b->tagihan?->nama }} · {{ $b->tanggal->translatedFormat('d M Y') }}</span></span>
                        <span class="shrink-0 font-bold text-emerald-600">{{ $rp($b->jumlah) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada pembayaran.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header">Tagihan lewat jatuh tempo <a href="{{ route($panel.'/keuangan/tagihan', ['terlambat' => 1]) }}" class="btn btn-soft btn-sm">Lihat semua</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($menunggak as $t)
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ $t->siswa?->nama_siswa }} <span class="font-normal text-slate-500">· {{ $t->siswa?->kelas?->nama_kelas }}</span></span><span class="text-xs text-slate-500">{{ $t->nama }} · jatuh tempo {{ $t->jatuh_tempo->translatedFormat('d M Y') }}</span></span>
                        <span class="shrink-0 font-bold text-rose-600">{{ $rp($t->sisa) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada tunggakan. 🎉</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
