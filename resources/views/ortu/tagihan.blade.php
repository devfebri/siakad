@extends('layouts.admin')

@section('title', 'Tagihan & Pembayaran')

@php
    $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
    $bisaDibayar = $tagihan->filter(fn ($t) => $t->sisa > 0);
@endphp

@section('content')
    <x-admin.page-header title="Tagihan & Pembayaran" subtitle="Biaya sekolah {{ $anak->nama_siswa }} dan riwayat pembayarannya." />

    @include('ortu._anak', ['daftarAnak' => $daftarAnak, 'anak' => $anak])

    @if ($baruKembali)
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">
            <p class="font-bold"><i class="bi bi-check-circle-fill"></i> Terima kasih!</p>
            <p class="text-sm">Pembayaran Anda sedang diperiksa. Tagihan akan berubah menjadi <strong>Lunas</strong> otomatis dalam beberapa saat. Muat ulang halaman ini bila belum berubah.</p>
        </div>
    @endif

    @if ($menunggu)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
            <p class="text-sm"><i class="bi bi-hourglass-split"></i> Ada pembayaran <strong>{{ $rp($menunggu->total) }}</strong> yang belum diselesaikan.</p>
            <a href="{{ $menunggu->url_bayar }}" class="btn btn-accent btn-sm">Lanjutkan Pembayaran</a>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-admin.stat-card label="Yang harus dibayar" :value="$rp($sisa)" icon="wallet2" :color="$sisa > 0 ? 'rose' : 'emerald'" />
        <x-admin.stat-card label="Sudah dibayar" :value="$rp($terbayar)" icon="check-circle" color="emerald" />
    </div>

    @if ($sisa === 0)
        <p class="mb-6 rounded-xl bg-emerald-50 p-4 text-emerald-800"><i class="bi bi-emoji-smile"></i> Tidak ada tagihan yang harus dibayar saat ini. Terima kasih!</p>
    @endif

    <form method="POST" action="{{ route('ortu/tagihan.bayar') }}"
          x-data="{
              pilih: [], sisa: @js($bisaDibayar->pluck('sisa', 'id')), biaya: @js($biaya),
              get subtotal() { return this.pilih.reduce((a, id) => a + (this.sisa[id] || 0), 0) },
              get admin() { if (!this.biaya || this.biaya.ditanggung !== 'ortu' || !this.subtotal) return 0; return Number(this.biaya.tetap) + Math.ceil(this.subtotal * Number(this.biaya.persen) / 100) },
              get total() { return this.subtotal + this.admin },
              rp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID') },
              semua() { this.pilih = this.pilih.length === Object.keys(this.sisa).length ? [] : Object.keys(this.sisa).map(Number) }
          }">
        @csrf
        <div class="card mb-6 overflow-x-auto">
            <div class="card-header">Daftar Tagihan</div>
            <table class="table-clean">
                <thead><tr>
                    @if ($bayarOnline && $bisaDibayar->isNotEmpty())<th class="w-12"><input type="checkbox" class="check" @click="semua()" aria-label="Pilih semua" :checked="pilih.length && pilih.length === Object.keys(sisa).length"></th>@endif
                    <th>Tagihan</th><th>Jumlah</th><th>Sisa</th><th>Jatuh tempo</th><th>Status</th>
                </tr></thead>
                <tbody>
                    @forelse ($tagihan as $t)
                        <tr>
                            @if ($bayarOnline && $bisaDibayar->isNotEmpty())
                                <td>@if ($t->sisa > 0)<input type="checkbox" name="tagihan_id[]" value="{{ $t->id }}" x-model.number="pilih" class="check" aria-label="Pilih {{ $t->nama }}">@endif</td>
                            @endif
                            <td class="font-semibold">{{ $t->nama }}</td>
                            <td>{{ $rp($t->jumlah) }}</td>
                            <td class="font-bold">{{ $rp($t->sisa) }}</td>
                            <td>{{ $t->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</td>
                            <td><span class="badge badge-{{ $t->terlambat() ? 'danger' : \App\Tagihan::WARNA[$t->status] }}">{{ $t->terlambat() ? 'Terlambat' : \App\Tagihan::STATUS[$t->status] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bayarOnline && $bisaDibayar->isNotEmpty())
            <div class="card sticky bottom-3 z-10 mb-6 p-5 shadow-lift" x-cloak x-show="pilih.length">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Tagihan dipilih (<span x-text="pilih.length"></span>)</dt><dd class="font-semibold" x-text="rp(subtotal)"></dd></div>
                    <div class="flex justify-between" x-show="admin > 0"><dt class="text-slate-600">Biaya admin pembayaran</dt><dd class="font-semibold" x-text="rp(admin)"></dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-lg"><dt class="font-bold">Total bayar</dt><dd class="font-display font-extrabold text-primary" x-text="rp(total)"></dd></div>
                </dl>
                <button class="btn btn-accent btn-lg btn-block mt-4"><i class="bi bi-credit-card"></i> Bayar Sekarang</button>
                <p class="mt-2 text-center text-xs text-slate-500">Anda akan diarahkan ke halaman pembayaran yang aman (virtual account, QRIS, atau e-wallet).</p>
            </div>
            @error('tagihan_id')<p class="mb-6 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
            <p class="mb-6 text-sm text-slate-500" x-show="!pilih.length"><i class="bi bi-hand-index"></i> Centang tagihan yang ingin dibayar, lalu tekan <strong>Bayar Sekarang</strong>.</p>
        @elseif ($bisaDibayar->isNotEmpty())
            <p class="mb-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><i class="bi bi-info-circle"></i> Pembayaran dilakukan langsung di sekolah (tunai atau transfer). Hubungi bendahara sekolah bila ada pertanyaan.</p>
        @endif
    </form>

    <div class="card overflow-x-auto">
        <div class="card-header">Riwayat Pembayaran</div>
        <table class="table-clean">
            <thead><tr><th>Tanggal</th><th>Untuk</th><th>Jumlah</th><th>Cara bayar</th><th class="text-right">Kuitansi</th></tr></thead>
            <tbody>
                @forelse ($pembayaran as $b)
                    <tr>
                        <td>{{ $b->tanggal->translatedFormat('d M Y') }}</td>
                        <td>{{ $b->tagihan?->nama }}</td>
                        <td class="font-bold text-emerald-600">{{ $rp($b->jumlah) }}</td>
                        <td>{{ \App\PembayaranSpp::METODE[$b->metode] ?? $b->metode }}</td>
                        <td class="text-right"><a href="{{ route('ortu/kuitansi', $b) }}" class="btn btn-soft btn-sm"><i class="bi bi-receipt"></i> Unduh</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
