@extends('layouts.admin')

@section('title', 'Pengaturan Platform')

@section('content')
    <x-admin.page-header title="Pengaturan Platform" subtitle="Identitas produk, kontak, dan percobaan gratis untuk sekolah yang mendaftar sendiri." />

    <form method="POST" action="{{ route('platform/pengaturan.simpan') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">Halaman produk</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label" for="nama_produk">Nama produk</label><input id="nama_produk" name="nama_produk" value="{{ old('nama_produk', $nilai['nama_produk']) }}" class="input" maxlength="60" required>@error('nama_produk')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="kontak_email">Email kontak</label><input id="kontak_email" type="email" name="kontak_email" value="{{ old('kontak_email', $nilai['kontak_email']) }}" class="input" maxlength="150">@error('kontak_email')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="kontak_wa">WhatsApp kontak</label><input id="kontak_wa" name="kontak_wa" value="{{ old('kontak_wa', $nilai['kontak_wa']) }}" class="input" maxlength="20" placeholder="628123456789">@error('kontak_wa')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">Percobaan gratis (trial)</h2>
            <p class="mt-1 text-sm text-slate-600">Berlaku untuk sekolah yang mendaftar sendiri. Satu kali per email dan per NPSN.</p>
            <div class="mt-4 space-y-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="trial_aktif" value="1" class="check" @checked(old('trial_aktif', $nilai['trial_aktif']))> Tawarkan percobaan gratis</label>
                <div class="max-w-xs"><label class="label" for="trial_hari">Lama percobaan (hari)</label><input id="trial_hari" type="number" name="trial_hari" min="1" max="90" value="{{ old('trial_hari', $nilai['trial_hari']) }}" class="input" required>@error('trial_hari')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">Backup sekolah</h2>
            <p class="mt-1 text-sm text-slate-600">Backup otomatis mingguan untuk semua sekolah aktif. Backup manual dan arsip sekolah nonaktif tidak pernah dihapus otomatis.</p>
            <div class="mt-4 space-y-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="backup_terjadwal_aktif" value="1" class="check" @checked(old('backup_terjadwal_aktif', $nilai['backup_terjadwal_aktif']))> Jalankan backup terjadwal (tiap Minggu dini hari)</label>
                <div class="max-w-xs"><label class="label" for="backup_simpan_jumlah">Simpan berapa backup terjadwal per sekolah</label><input id="backup_simpan_jumlah" type="number" min="1" max="30" name="backup_simpan_jumlah" value="{{ old('backup_simpan_jumlah', $nilai['backup_simpan_jumlah']) }}" class="input" required>@error('backup_simpan_jumlah')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </div>

        <div class="card p-6" x-data="{ driver: @js(old('pembayaran_driver', $nilai['pembayaran_driver'])) }">
            <h2 class="font-display text-lg font-bold">Pembayaran online (sewa)</h2>
            <p class="mt-1 text-sm text-slate-600">Sekolah bisa membayar invoice lewat virtual account, QRIS, atau e-wallet. Transfer manual dan konfirmasi Anda tetap tersedia.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="pembayaran_driver">Gerbang pembayaran</label>
                    <select id="pembayaran_driver" name="pembayaran_driver" x-model="driver" class="select">@foreach ($driver as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    @error('pembayaran_driver')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex cursor-pointer items-center gap-2 self-end pb-2 text-sm" x-show="driver === 'midtrans'" x-cloak><input type="checkbox" name="pembayaran_sandbox" value="1" class="check" @checked(old('pembayaran_sandbox', $nilai['pembayaran_sandbox']))> Mode uji coba (sandbox)</label>

                <div class="sm:col-span-2" x-show="driver === 'midtrans'" x-cloak>
                    <label class="label" for="midtrans_server_key">Midtrans Server Key</label>
                    <input id="midtrans_server_key" type="password" name="midtrans_server_key" class="input" autocomplete="new-password" placeholder="{{ $kunci['midtrans_server_key'] ?: 'Belum diisi' }}">
                    <p class="field-hint">Kosongkan bila tidak ingin mengubah kunci yang tersimpan. Alamat notifikasi untuk dashboard Midtrans (Payment Notification URL): <code>{{ $urlCallback['midtrans'] }}</code></p>
                </div>
                <div x-show="driver === 'xendit'" x-cloak>
                    <label class="label" for="xendit_secret_key">Xendit Secret Key</label>
                    <input id="xendit_secret_key" type="password" name="xendit_secret_key" class="input" autocomplete="new-password" placeholder="{{ $kunci['xendit_secret_key'] ?: 'Belum diisi' }}">
                    <p class="field-hint">Kunci "development" = uji coba; kunci "production" = uang sungguhan.</p>
                </div>
                <div x-show="driver === 'xendit'" x-cloak>
                    <label class="label" for="xendit_callback_token">Xendit Callback Verification Token</label>
                    <input id="xendit_callback_token" type="password" name="xendit_callback_token" class="input" autocomplete="new-password" placeholder="{{ $kunci['xendit_callback_token'] ?: 'Belum diisi' }}">
                </div>
                <p class="field-hint sm:col-span-2" x-show="driver === 'xendit'" x-cloak>Isi di dashboard Xendit, bagian Callbacks (Invoice paid dan expired): <code>{{ $urlCallback['xendit'] }}</code></p>
                <p class="rounded-xl bg-accent-soft p-3 text-sm sm:col-span-2" x-show="driver === 'fake'" x-cloak><i class="bi bi-info-circle"></i> Mode simulasi: halaman bayar tiruan di aplikasi ini, tanpa uang sungguhan. Hanya untuk lokal/uji dan tidak tersedia di server produksi.</p>
            </div>
        </div>

        <button class="btn btn-primary btn-lg">Simpan Pengaturan</button>
    </form>
@endsection
