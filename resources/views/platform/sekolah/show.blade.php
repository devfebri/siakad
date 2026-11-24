@extends('layouts.admin')

@section('title', $sekolah->nama)

@php
    $badgeSewa = $sekolah->tanpa_batas
        ? ['Tanpa batas', 'primary']
        : ['aktif' => ['Aktif', 'success'], 'tenggang' => ['Masa tenggang', 'warning'], 'baca_saja' => ['Baca-saja', 'danger'], 'belum_ada' => ['Belum berlangganan', 'danger']][$statusSewa];
@endphp

@section('content')
    <x-admin.page-header :title="$sekolah->nama" :subtitle="$sekolah->jenjang.' · NPSN '.($sekolah->npsn ?: '-').' · '.$sekolah->subdomain" :back="route('platform/sekolah')">
        <span class="badge badge-{{ $sekolah->isAktif() ? 'success' : 'muted' }} text-sm">{{ \App\Sekolah::STATUS[$sekolah->status] }}</span>
        <span class="badge badge-{{ $badgeSewa[1] }} text-sm">{{ $badgeSewa[0] }}</span>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Siswa" :value="$jumlah['siswa']" icon="mortarboard" color="primary" />
        <x-admin.stat-card label="Guru" :value="$jumlah['guru']" icon="person-workspace" color="accent" />
        <x-admin.stat-card label="Akun pengguna" :value="$jumlah['pengguna']" icon="people" color="emerald" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <h2 class="font-display text-lg font-bold">Langganan saat ini</h2>
            @if ($terkini)
                <p class="mt-2 text-sm text-slate-600"><strong>{{ $terkini->paket?->nama }}</strong> · {{ $terkini->durasi_bulan }} bulan · {{ $terkini->mulai->translatedFormat('d M Y') }} – <strong>{{ $terkini->berakhir->translatedFormat('d M Y') }}</strong></p>
            @else
                <p class="mt-2 text-sm text-slate-500">Belum ada langganan. Buat invoice lalu konfirmasi pembayaran untuk mengaktifkan.</p>
            @endif
            <p class="mt-3 text-sm text-slate-500">Admin sekolah: <strong>{{ $admin?->username ?? '-' }}</strong> {{ $admin ? '('.$admin->email.')' : '' }}</p>
            <p class="mt-1 text-sm text-slate-500">Alamat: <a href="{{ $sekolah->url('/') }}" target="_blank" rel="noopener">{{ $sekolah->domainAktif() ?: $sekolah->subdomain }}</a></p>
        </div>

        <div class="card space-y-3 p-5" x-data="{ galat: '' }">
            <h2 class="font-display text-lg font-bold">Tindakan</h2>
            <form method="POST" action="{{ route('platform/sekolah.masuk', $sekolah) }}" target="_blank">
                @csrf
                <button type="submit" class="btn btn-accent btn-block" @disabled(! $admin || ! $sekolah->isAktif())><i class="bi bi-box-arrow-in-right"></i> Masuk sebagai Admin</button>
            </form>
            <button type="button" class="btn btn-outline btn-block" data-status="{{ $sekolah->isAktif() ? 'nonaktif' : 'aktif' }}"
                    @click="
                        const baru = $el.dataset.status;
                        const r = await fetch(@js(route('platform/sekolah.status', $sekolah)), { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ status: baru }) });
                        if (r.ok) { window.toast((await r.json()).message); setTimeout(() => location.reload(), 600); } else { window.toast('Gagal mengubah status.', 'error'); }">
                <i class="bi bi-power"></i> {{ $sekolah->isAktif() ? 'Nonaktifkan sekolah' : 'Aktifkan sekolah' }}
            </button>
        </div>
    </div>

    <div class="mb-6 card p-5">
        <h2 class="font-display text-lg font-bold">Domain kustom</h2>
        @if ($instruksiDomain)
            <p class="mt-2 text-sm">
                <strong>{{ $instruksiDomain['domain'] }}</strong>
                @if ($sekolah->domainAktif())<span class="badge badge-success">Terverifikasi</span>@else<span class="badge badge-warning">Menunggu verifikasi</span>@endif
            </p>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">TXT (bukti kepemilikan)</dt><dd class="break-all font-mono text-xs">{{ $instruksiDomain['txt_host'] }}<br>{{ $instruksiDomain['txt_nilai'] }}</dd></div>
                <div><dt class="text-slate-500">Arah lalu lintas</dt><dd class="break-all font-mono text-xs">@if ($instruksiDomain['cname_target'])CNAME → {{ $instruksiDomain['cname_target'] }}<br>@endif @if ($instruksiDomain['a_target'])A → {{ $instruksiDomain['a_target'] }}@endif @if (! $instruksiDomain['cname_target'] && ! $instruksiDomain['a_target'])<span class="text-slate-400">belum dikonfigurasi (SEKOLAH_CNAME_TARGET / SEKOLAH_SERVER_IP)</span>@endif</dd></div>
            </dl>
            @if ($hasilCekDomain)
                <p class="mt-3 rounded-xl p-3 text-sm {{ $hasilCekDomain['terverifikasi'] ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }}">{{ $hasilCekDomain['pesan'] }}</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('platform/sekolah.domain.periksa', $sekolah) }}">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Cek DNS</button></form>
                @unless ($sekolah->domainAktif())
                    <form method="POST" action="{{ route('platform/sekolah.domain.manual', $sekolah) }}" onsubmit="return confirm('Tandai terverifikasi tanpa cek DNS? Pastikan Anda yakin domain ini milik sekolah.')">@csrf<button class="btn btn-outline btn-sm">Tandai terverifikasi</button></form>
                @endunless
                <form method="POST" action="{{ route('platform/sekolah.domain.hapus', $sekolah) }}" onsubmit="return confirm('Hapus domain kustom sekolah ini?')">@csrf @method('DELETE')<button class="btn btn-soft btn-sm">Hapus domain</button></form>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-500">Belum ada. Admin sekolah bisa mendaftarkannya di menu <em>Domain Website</em>, atau isi lewat tombol Ubah di daftar sekolah.</p>
        @endif
    </div>

    <div class="mb-6 card">
        <div class="card-header">Buat Invoice</div>
        <form data-ajax-form novalidate action="{{ route('platform/sekolah.invoice', $sekolah) }}" class="grid gap-3 p-5 sm:grid-cols-4">
            <div>
                <label class="label" for="inv_paket">Paket</label>
                <select id="inv_paket" name="paket_id" class="select" required>@foreach ($paket as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label" for="inv_durasi">Durasi</label>
                <select id="inv_durasi" name="durasi_bulan" class="select" required>@foreach (\App\Paket::DURASI as $d)<option value="{{ $d }}">{{ $d }} bulan</option>@endforeach</select>
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="inv_cat">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="inv_cat" name="catatan" class="input" maxlength="300">
            </div>
            <div class="sm:col-span-4 flex justify-end"><button type="submit" class="btn btn-primary"><i class="bi bi-receipt"></i> Buat Invoice</button></div>
        </form>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <div class="card-header">Riwayat Langganan</div>
            <table class="table-clean">
                <thead><tr><th>Paket</th><th>Periode</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($langganan as $l)
                        <tr><td>{{ $l->paket?->nama }} · {{ $l->durasi_bulan }} bln</td><td class="text-slate-600">{{ $l->mulai->translatedFormat('d M Y') }} – {{ $l->berakhir->translatedFormat('d M Y') }}</td><td><span class="badge badge-{{ $l->status === 'aktif' ? 'success' : 'muted' }}">{{ $l->status }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-slate-500">Belum ada langganan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <div class="card-header">Invoice</div>
            <table class="table-clean">
                <thead><tr><th>Nomor</th><th>Jumlah</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($invoice as $i)
                        <tr><td>{{ $i->nomor }}<br><span class="text-xs text-slate-500">{{ $i->paket?->nama }} · {{ $i->durasi_bulan }} bln</span></td><td>Rp {{ number_format($i->jumlah, 0, ',', '.') }}</td><td><span class="badge badge-{{ \App\Invoice::WARNA[$i->status] }}">{{ \App\Invoice::STATUS[$i->status] }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-slate-500">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
