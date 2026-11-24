@extends('layouts.admin')

@section('title', 'Dashboard Platform')

@section('content')
    <x-admin.page-header title="Dashboard Platform" subtitle="Ringkasan sekolah penyewa, langganan, dan pembayaran." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Sekolah" :value="$ringkas['sekolah']" icon="buildings" color="primary" :href="route('platform/sekolah')" :hint="$ringkas['sekolah_aktif'].' aktif'" />
        <x-admin.stat-card label="Langganan aktif" :value="$ringkas['langganan_aktif']" icon="calendar-check" color="emerald" :href="route('platform/langganan')" />
        <x-admin.stat-card label="Tenggang / baca-saja" :value="$ringkas['tenggang']" icon="exclamation-triangle" color="rose" :href="route('platform/langganan')" />
        <x-admin.stat-card label="Invoice menunggu" :value="$ringkas['invoice_menunggu']" icon="receipt" color="accent" :href="route('platform/invoice')" :hint="'Pendapatan bulan ini Rp '.number_format($ringkas['pendapatan_bulan_ini'], 0, ',', '.')" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">Langganan Segera Berakhir <span class="text-xs font-normal text-slate-500">30 hari ke depan</span></div>
            <div class="divide-y divide-slate-100">
                @forelse ($segeraBerakhir as $l)
                    <a href="{{ route('platform/sekolah.show', $l->sekolah_id) }}" class="flex items-center justify-between gap-3 px-5 py-3 no-underline hover:bg-primary-soft">
                        <span><span class="block font-semibold text-ink">{{ $l->sekolah?->nama }}</span><span class="text-xs text-slate-500">{{ $l->paket?->nama }}</span></span>
                        <span class="badge badge-warning">{{ $l->berakhir->translatedFormat('d M Y') }}</span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada langganan yang segera berakhir.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header">Invoice Terbaru</div>
            <div class="divide-y divide-slate-100">
                @forelse ($invoiceTerbaru as $i)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <span><span class="block font-semibold">{{ $i->nomor }}</span><span class="text-xs text-slate-500">{{ $i->sekolah?->nama }} · {{ $i->paket?->nama }} {{ $i->durasi_bulan }} bln</span></span>
                        <span class="text-right"><span class="block text-sm font-semibold">Rp {{ number_format($i->jumlah, 0, ',', '.') }}</span><span class="badge badge-{{ \App\Invoice::WARNA[$i->status] ?? 'muted' }}">{{ \App\Invoice::STATUS[$i->status] ?? $i->status }}</span></span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada invoice.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Aktivitas Terakhir <a href="{{ route('platform/log') }}" class="btn btn-soft btn-sm">Semua log</a></div>
        <div class="divide-y divide-slate-100">
            @forelse ($log as $l)
                <div class="px-5 py-3 text-sm"><span class="badge badge-muted">{{ $l->aksi }}</span> {{ $l->deskripsi }} <span class="text-xs text-slate-400">· {{ $l->created_at?->diffForHumans() }}</span></div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </div>
@endsection
