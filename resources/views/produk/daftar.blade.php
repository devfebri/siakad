@extends('layouts.landing')

@section('title', 'Daftarkan Sekolah')

@php
    $dataPaket = $paket->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama, 'harga' => collect(\App\Paket::DURASI)->mapWithKeys(fn ($d) => [$d => $p->hargaUntuk($d)])->all()])->values();
    $awalPaket = $pilihPaket && $paket->contains('id', $pilihPaket) ? $pilihPaket : ($paket->count() >= 2 ? $paket[1]->id : $paket->first()?->id);
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6"
         x-data="{
            paket: {{ (int) old('paket_id', $awalPaket) }}, durasi: {{ (int) old('durasi_bulan', 12) }}, trial: {{ old('pakai_trial', $trial ? 1 : 0) ? 'true' : 'false' }},
            sub: @js(old('subdomain', '')), status: null, pesan: '', t: null, daftar: @js($dataPaket),
            get harga() { const p = this.daftar.find(x => x.id == this.paket); return p ? p.harga[this.durasi] : null },
            rupiah(n) { return n === null ? '-' : 'Rp ' + Number(n).toLocaleString('id-ID') },
            cek() {
                clearTimeout(this.t); this.sub = this.sub.toLowerCase().replace(/[^a-z0-9-]/g, ''); this.status = null;
                if (this.sub.length < 2) { this.pesan = ''; return; }
                this.t = setTimeout(async () => {
                    const r = await fetch(@js(route('daftar.cek-subdomain')) + '?s=' + encodeURIComponent(this.sub), { headers: { Accept: 'application/json' } });
                    const j = await r.json(); this.status = j.tersedia; this.pesan = j.pesan;
                }, 350);
            }
         }" x-init="if (sub) cek()">

        <div class="mb-8 text-center">
            <h1 class="font-display text-3xl font-extrabold text-primary sm:text-4xl">Daftarkan sekolah Anda</h1>
            <p class="mt-2 text-slate-600">Isi tiga bagian di bawah. Tidak sampai 5 menit.@if ($trial) Anda bisa <strong>mencoba gratis {{ $trial }} hari</strong>.@endif</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-bold">Ada isian yang perlu diperbaiki:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('daftar.simpan') }}" class="space-y-6">
            @csrf
            <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            {{-- ============ 1. sekolah --}}
            <section class="card p-6">
                <h2 class="font-display text-xl font-bold"><span class="mr-2 inline-grid size-8 place-items-center rounded-full bg-primary text-sm text-white">1</span> Data sekolah</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="nama">Nama sekolah</label><input id="nama" name="nama" value="{{ old('nama') }}" class="input" maxlength="150" required placeholder="Contoh: SMP Negeri 1 Muaro Jambi"></div>
                    <div><label class="label" for="jenjang">Jenjang</label>
                        <select id="jenjang" name="jenjang" class="select" required>@foreach ($jenjang as $j)<option @selected(old('jenjang', 'SMP') === $j)>{{ $j }}</option>@endforeach</select></div>
                    <div><label class="label" for="npsn">NPSN <span class="font-normal text-slate-400">(boleh dikosongkan)</span></label><input id="npsn" name="npsn" value="{{ old('npsn') }}" class="input" inputmode="numeric" maxlength="20" placeholder="8 angka"></div>
                    <div class="sm:col-span-2">
                        <label class="label" for="subdomain">Alamat situs sekolah</label>
                        <div class="flex items-center gap-2">
                            <input id="subdomain" name="subdomain" x-model="sub" @input="cek()" class="input" maxlength="60" required autocomplete="off" placeholder="smpn1">
                            <span class="shrink-0 text-sm text-slate-500">.{{ $domainUtama }}</span>
                        </div>
                        <p class="mt-1.5 text-sm font-semibold" x-show="pesan" x-cloak :class="status ? 'text-emerald-600' : 'text-red-600'"><i class="bi" :class="status ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i> <span x-text="pesan"></span></p>
                        <p class="field-hint">Ini alamat website sekolah Anda. Pakai huruf kecil, angka, dan strip.</p>
                    </div>
                </div>
            </section>

            {{-- ============ 2. paket --}}
            <section class="card p-6">
                <h2 class="font-display text-xl font-bold"><span class="mr-2 inline-grid size-8 place-items-center rounded-full bg-primary text-sm text-white">2</span> Pilih paket</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    @foreach ($paket as $p)
                        <label class="cursor-pointer rounded-xl border-2 p-4 transition" :class="paket == {{ $p->id }} ? 'border-primary bg-primary-soft' : 'border-slate-200'">
                            <input type="radio" name="paket_id" value="{{ $p->id }}" x-model.number="paket" class="sr-only">
                            <span class="block font-display text-lg font-bold">{{ $p->nama }}</span>
                            <span class="mt-1 block text-xs text-slate-600">{{ $p->deskripsi }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-5">
                    <label class="label" for="durasi_bulan">Lama langganan</label>
                    <select id="durasi_bulan" name="durasi_bulan" x-model.number="durasi" class="select">
                        @foreach (\App\Paket::DURASI as $d)<option value="{{ $d }}">{{ $d }} bulan</option>@endforeach
                    </select>
                    <p class="mt-2 text-lg font-bold text-primary">Biaya: <span x-text="rupiah(harga)"></span> <span class="text-sm font-normal text-slate-500">untuk <span x-text="durasi"></span> bulan</span></p>
                </div>
                @if ($trial)
                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl bg-accent-soft p-4 text-sm">
                        <input type="checkbox" name="pakai_trial" value="1" x-model="trial" class="check mt-0.5">
                        <span><strong>Coba gratis {{ $trial }} hari dulu.</strong> Tidak perlu membayar sekarang. Setelah masa percobaan, Anda bisa memperpanjang kapan saja.</span>
                    </label>
                @endif
                <p class="mt-3 text-xs text-slate-500" x-show="!trial" x-cloak>Setelah aktif, tagihan muncul di menu Langganan Saya. Sekolah bisa langsung dicoba, dan penuh setelah dibayar.</p>
            </section>

            {{-- ============ 3. admin --}}
            <section class="card p-6">
                <h2 class="font-display text-xl font-bold"><span class="mr-2 inline-grid size-8 place-items-center rounded-full bg-primary text-sm text-white">3</span> Akun admin sekolah</h2>
                <p class="mt-1 text-sm text-slate-600">Akun ini yang dipakai mengatur seluruh aplikasi. Kami kirim email untuk memastikan alamatnya benar.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="admin_name">Nama Anda</label><input id="admin_name" name="admin_name" value="{{ old('admin_name') }}" class="input" maxlength="100" required autocomplete="name"></div>
                    <div><label class="label" for="admin_email">Email</label><input id="admin_email" type="email" name="admin_email" value="{{ old('admin_email') }}" class="input" maxlength="191" required autocomplete="email"></div>
                    <div><label class="label" for="admin_username">Username untuk masuk</label><input id="admin_username" name="admin_username" value="{{ old('admin_username', 'admin') }}" class="input" maxlength="50" required autocomplete="username"></div>
                    <div></div>
                    <div><label class="label" for="admin_password">Kata sandi</label><input id="admin_password" type="password" name="admin_password" class="input" minlength="8" maxlength="100" required autocomplete="new-password"><p class="field-hint">Minimal 8 karakter.</p></div>
                    <div><label class="label" for="admin_password_confirmation">Ulangi kata sandi</label><input id="admin_password_confirmation" type="password" name="admin_password_confirmation" class="input" minlength="8" required autocomplete="new-password"></div>
                </div>
            </section>

            <label class="flex cursor-pointer items-start gap-3 text-sm"><input type="checkbox" name="setuju" value="1" class="check mt-0.5" @checked(old('setuju')) required> Saya sudah membaca dan setuju dengan syarat penggunaan layanan ini.</label>

            <button class="btn btn-accent btn-lg btn-block" :disabled="status === false"><i class="bi bi-envelope-check"></i> Daftar dan Kirim Email Aktivasi</button>
        </form>
    </div>
@endsection
