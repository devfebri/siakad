@extends('layouts.admin')

@section('title', 'Langganan Saya')

@php
    $label = ['aktif' => ['Aktif', 'success'], 'tenggang' => ['Masa tenggang', 'warning'], 'baca_saja' => ['Mode baca-saja', 'danger'], 'belum_ada' => ['Belum berlangganan', 'danger']][$akses['status']];
@endphp

@section('content')
    <x-admin.page-header title="Langganan Saya" subtitle="Status sewa, modul yang tersedia, dan pengajuan perpanjangan." />

    @if ($baruKembaliDariBayar)
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            <p class="font-bold"><i class="bi bi-check-circle-fill"></i> Terima kasih!</p>
            <p>Pembayaran Anda sedang kami periksa. Status invoice akan berubah menjadi <strong>Lunas</strong> otomatis dalam beberapa saat. Muat ulang halaman ini bila belum berubah.</p>
        </div>
    @endif

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-{{ $label[1] }} text-sm">{{ $akses['bebas'] ? 'Tanpa batas' : $label[0] }}</span>
                @if ($akses['paket'])<span class="badge badge-primary text-sm">Paket {{ $akses['paket'] }}</span>@endif
                @if ($terkini?->trial)<span class="badge badge-accent text-sm">Masa percobaan gratis</span>@endif
            </div>

            @if ($akses['bebas'])
                <p class="mt-3 text-slate-600">Sekolah Anda tidak dibatasi masa langganan. Semua modul tersedia.</p>
            @elseif ($terkini)
                <p class="mt-3 text-lg font-bold">Berlaku sampai {{ $terkini->berakhir->translatedFormat('d F Y') }}</p>
                <p class="text-sm text-slate-600">
                    @if ($akses['status'] === 'aktif')
                        Sisa {{ $akses['sisa_hari'] }} hari.
                    @elseif ($akses['status'] === 'tenggang')
                        Masa tenggang sampai {{ $akses['tenggang_sampai']->translatedFormat('d F Y') }}. Setelah itu situs menjadi baca-saja.
                    @else
                        Masa tenggang berakhir {{ $akses['tenggang_sampai']->translatedFormat('d F Y') }}. Situs dalam mode baca-saja: data masih bisa dilihat, perubahan ditolak.
                    @endif
                </p>
            @else
                <p class="mt-3 text-slate-600">Sekolah belum memiliki langganan. Ajukan paket di bawah ini untuk mengaktifkan fitur.</p>
            @endif
        </div>

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">Modul</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-emerald-500"></i> Data akademik inti (siswa, guru, kelas, jadwal)</li>
                @foreach ($modul as $m)
                    @php $ada = $akses['modul'] === null || in_array($m->kode, $akses['modul'], true); @endphp
                    <li class="flex items-center gap-2 {{ $ada ? '' : 'text-slate-400' }}"><i class="bi {{ $ada ? 'bi-check-circle-fill text-emerald-500' : 'bi-lock-fill' }}"></i> {{ $m->nama }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    @if (! $akses['bebas'])
        <div class="card mb-6">
            <div class="card-header">Ajukan Perpanjangan / Ganti Paket</div>
            <div class="grid gap-4 p-5 lg:grid-cols-3">
                @foreach ($paket as $p)
                    <form data-ajax-form novalidate action="{{ route('admin/langganan.ajukan') }}" class="rounded-2xl border border-slate-200 p-4">
                        <input type="hidden" name="paket_id" value="{{ $p->id }}">
                        <p class="font-display text-lg font-bold">{{ $p->nama }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $p->deskripsi }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $p->modul->pluck('nama')->implode(' · ') }}</p>
                        <div class="mt-3 flex items-end gap-2">
                            <div class="flex-1">
                                <label class="label" for="dur{{ $p->id }}">Durasi</label>
                                <select id="dur{{ $p->id }}" name="durasi_bulan" class="select">
                                    @foreach (\App\Paket::DURASI as $d)
                                        @if ($p->hargaUntuk($d) !== null)<option value="{{ $d }}">{{ $d }} bulan · Rp {{ number_format($p->hargaUntuk($d), 0, ',', '.') }}</option>@endif
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Ajukan</button>
                        </div>
                    </form>
                @endforeach
            </div>
            @if ($petunjukBayar)<p class="border-t border-slate-100 px-5 py-3 text-sm text-slate-600"><i class="bi bi-info-circle"></i> {{ $petunjukBayar }}</p>@endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <div class="card-header">Invoice</div>
            <table class="table-clean">
                <thead><tr><th>Nomor</th><th>Jumlah</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($invoice as $i)
                        <tr>
                            <td>{{ $i->nomor }}<br><span class="text-xs text-slate-500">{{ $i->paket?->nama }} · {{ $i->durasi_bulan }} bln</span></td>
                            <td>Rp {{ number_format($i->jumlah, 0, ',', '.') }}</td>
                            <td><span class="badge badge-{{ \App\Invoice::WARNA[$i->status] }}">{{ \App\Invoice::STATUS[$i->status] }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                @if ($i->status === 'menunggu' && $bayarOnline)
                                    <form method="POST" action="{{ route('admin/langganan.bayar', $i) }}" class="inline">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-credit-card"></i> Bayar Online</button></form>
                                @endif
                                <a href="{{ route('admin/langganan.invoice-pdf', $i) }}" class="btn btn-soft btn-sm" title="Unduh invoice (PDF)"><i class="bi bi-file-earmark-pdf"></i> Invoice</a>
                                @if ($i->status === 'lunas')<a href="{{ route('admin/langganan.kuitansi-pdf', $i) }}" class="btn btn-soft btn-sm" title="Unduh kuitansi (PDF)"><i class="bi bi-receipt"></i> Kuitansi</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <div class="card-header">Riwayat Langganan</div>
            <table class="table-clean">
                <thead><tr><th>Paket</th><th>Periode</th></tr></thead>
                <tbody>
                    @forelse ($riwayat as $l)
                        <tr><td>{{ $l->paket?->nama }} · {{ $l->durasi_bulan }} bln</td><td class="text-slate-600">{{ $l->mulai->translatedFormat('d M Y') }} – {{ $l->berakhir->translatedFormat('d M Y') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-8 text-center text-slate-500">Belum ada langganan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
