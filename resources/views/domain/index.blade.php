@extends('layouts.admin')

@section('title', 'Domain Website')

@php
    $tersalin = "navigator.clipboard.writeText(\$el.dataset.salin); \$el.textContent = 'Tersalin ✓'";
@endphp

@section('content')
    <x-admin.page-header title="Domain Website" subtitle="Pakai alamat website sendiri, misalnya www.sekolahku.sch.id, menggantikan alamat bawaan." />

    <div class="card mb-6 p-6">
        <p class="text-sm text-slate-500">Alamat website saat ini</p>
        <p class="mt-1 break-all font-display text-xl font-bold text-primary"><a href="{{ $sekolah->url('/') }}" target="_blank" rel="noopener">{{ $sekolah->url('/') }}</a></p>
        @if ($sekolah->domainAktif())
            <p class="mt-2 text-sm text-slate-600">Alamat bawaan <span class="break-all">{{ $alamatBawaan }}</span> tetap bisa dipakai sebagai cadangan.</p>
        @endif
    </div>

    {{-- ============================================================ langkah 1: daftar domain --}}
    <div class="card mb-6 p-6">
        <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 1</p>
        <h2 class="font-display text-xl font-bold">Masukkan nama domain Anda</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">Domain dibeli terpisah di penyedia domain (misalnya PANDI untuk .sch.id). Tulis tanpa <code>http://</code> dan tanpa garis miring.</p>
        <form method="POST" action="{{ route('admin/domain.simpan') }}" class="mt-4 flex flex-wrap items-start gap-3">@csrf
            <div class="min-w-0 flex-1">
                <input name="domain" value="{{ old('domain', $sekolah->domain_kustom) }}" class="input" placeholder="sekolahku.sch.id" maxlength="150" aria-label="Nama domain" autocomplete="off">
                @error('domain')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Simpan Domain</button>
        </form>
    </div>

    @if ($instruksi)
        <div class="mb-6 flex flex-wrap items-center gap-2">
            @if ($sekolah->domainAktif())
                <span class="badge badge-success text-sm"><i class="bi bi-patch-check-fill"></i> Terverifikasi</span>
            @else
                <span class="badge badge-warning text-sm"><i class="bi bi-hourglass-split"></i> Menunggu verifikasi</span>
            @endif
            <span class="text-sm text-slate-600">{{ $instruksi['domain'] }}</span>
        </div>

        {{-- ============================================================ langkah 2: DNS --}}
        <div class="card mb-6 p-6" x-data>
            <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 2</p>
            <h2 class="font-display text-xl font-bold">Pasang pengaturan DNS di penyedia domain</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Masuk ke panel DNS penyedia domain Anda, lalu tambahkan dua rekaman berikut. Jika bingung, kirim halaman ini kepada orang yang mengurus domain sekolah.</p>

            <div class="mt-5 space-y-5">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="font-display font-bold">A. Bukti bahwa domain ini milik sekolah (wajib)</p>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-slate-500">Jenis</dt><dd class="font-mono font-semibold">TXT</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Nama / Host</dt><dd class="flex flex-wrap items-center gap-2"><code class="break-all rounded bg-slate-100 px-2 py-1">{{ $instruksi['txt_host'] }}</code><button type="button" class="btn btn-outline btn-sm" data-salin="{{ $instruksi['txt_host'] }}" @click="{{ $tersalin }}">Salin</button></dd></div>
                        <div class="sm:col-span-3"><dt class="text-slate-500">Isi / Nilai</dt><dd class="flex flex-wrap items-center gap-2"><code class="break-all rounded bg-slate-100 px-2 py-1">{{ $instruksi['txt_nilai'] }}</code><button type="button" class="btn btn-outline btn-sm" data-salin="{{ $instruksi['txt_nilai'] }}" @click="{{ $tersalin }}">Salin</button></dd></div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="font-display font-bold">B. Arahkan domain ke server kami (pilih salah satu)</p>
                    @if ($instruksi['cname_target'])
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="text-slate-500">Cara 1 · Jenis</dt><dd class="font-mono font-semibold">CNAME</dd></div>
                            <div><dt class="text-slate-500">Nama / Host</dt><dd><code class="rounded bg-slate-100 px-2 py-1">{{ $instruksi['cname_host'] }}</code></dd></div>
                            <div><dt class="text-slate-500">Tujuan</dt><dd class="flex flex-wrap items-center gap-2"><code class="break-all rounded bg-slate-100 px-2 py-1">{{ $instruksi['cname_target'] }}</code><button type="button" class="btn btn-outline btn-sm" data-salin="{{ $instruksi['cname_target'] }}" @click="{{ $tersalin }}">Salin</button></dd></div>
                        </dl>
                        <p class="mt-2 text-xs text-slate-500">Cocok untuk alamat berawalan "www" atau subdomain. Domain tanpa awalan (misalnya <em>sekolahku.sch.id</em>) umumnya tidak boleh memakai CNAME; pakai Cara 2.</p>
                    @endif
                    @if ($instruksi['a_target'])
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="text-slate-500">{{ $instruksi['cname_target'] ? 'Cara 2' : 'Rekaman' }} · Jenis</dt><dd class="font-mono font-semibold">A</dd></div>
                            <div><dt class="text-slate-500">Nama / Host</dt><dd><code class="rounded bg-slate-100 px-2 py-1">@</code> <span class="text-xs text-slate-500">(atau kosong)</span></dd></div>
                            <div><dt class="text-slate-500">Tujuan (alamat IP)</dt><dd class="flex flex-wrap items-center gap-2"><code class="rounded bg-slate-100 px-2 py-1">{{ $instruksi['a_target'] }}</code><button type="button" class="btn btn-outline btn-sm" data-salin="{{ $instruksi['a_target'] }}" @click="{{ $tersalin }}">Salin</button></dd></div>
                        </dl>
                    @endif
                    @if (! $instruksi['cname_target'] && ! $instruksi['a_target'])
                        <p class="mt-3 text-sm text-slate-600">Tujuan DNS belum diatur oleh pengelola platform. Hubungi pengelola untuk mendapatkan alamat tujuannya.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================================ langkah 3: cek --}}
        <div class="card mb-6 p-6">
            <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 3</p>
            <h2 class="font-display text-xl font-bold">Cek apakah sudah terpasang</h2>
            <p class="mt-1 text-sm text-slate-600">Setelah rekaman dipasang, tunggu beberapa menit (kadang sampai beberapa jam), lalu tekan tombol di bawah. Sistem juga mengecek sendiri tiap jam.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin/domain.periksa') }}">@csrf<button class="btn btn-accent btn-lg"><i class="bi bi-search"></i> Cek Sekarang</button></form>
                <form method="POST" action="{{ route('admin/domain.hapus') }}" onsubmit="return confirm('Hapus domain sendiri? Situs kembali memakai alamat bawaan.')">@csrf @method('DELETE')<button class="btn btn-outline">Hapus Domain</button></form>
            </div>

            @if ($cek)
                <div class="mt-5 rounded-xl p-4 text-sm {{ $cek['terverifikasi'] ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }}" role="status">
                    <p class="font-bold">{{ $cek['pesan'] }}</p>
                    <ul class="mt-2 space-y-1">
                        <li><i class="bi {{ $cek['txt'] ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-red-500' }}"></i> Bukti kepemilikan (TXT): {{ $cek['txt'] ? 'ditemukan' : 'belum ditemukan' }}</li>
                        <li><i class="bi {{ $cek['mengarah'] === null ? 'bi-dash-circle text-slate-400' : ($cek['mengarah'] ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-red-500') }}"></i> Mengarah ke server: {{ $cek['mengarah'] === null ? 'tidak bisa dicek' : ($cek['mengarah'] ? 'sudah' : 'belum') }}</li>
                    </ul>
                </div>
            @endif
        </div>
    @endif
@endsection
