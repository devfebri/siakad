@extends('layouts.admin')

@section('title', 'Pembayaran Online')

@section('content')
    @include('keuangan._tab')
    <x-admin.page-header title="Pembayaran Online" subtitle="Orang tua bisa membayar SPP dari ponsel (virtual account, QRIS, e-wallet). Uangnya masuk langsung ke akun gerbang milik sekolah." />

    <div class="mb-6 rounded-xl p-4 text-sm {{ $siap ? 'bg-emerald-50 text-emerald-900' : 'bg-accent-soft text-ink' }}" role="status">
        @if ($siap)
            <i class="bi bi-check-circle-fill"></i> <strong>Aktif.</strong> Tombol "Bayar Sekarang" tampil di halaman Tagihan orang tua.
        @else
            <i class="bi bi-info-circle-fill"></i> <strong>Belum aktif.</strong> Isi pengaturan di bawah. Selama belum aktif, orang tua membayar langsung ke sekolah.
        @endif
    </div>

    <form method="POST" action="{{ route('admin/keuangan/online.simpan') }}" class="space-y-6" x-data="{ driver: @js(old('driver', $pg->driver)) }">
        @csrf @method('PUT')

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">1. Pilih gerbang pembayaran</h2>
            <p class="mt-1 text-sm text-slate-600">Daftar dulu di Midtrans atau Xendit atas nama sekolah. Mereka yang mengurus pembayarannya dan mengirim uang ke rekening sekolah.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="driver">Gerbang</label>
                    <select id="driver" name="driver" x-model="driver" class="select">@foreach ($driver as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    @error('driver')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex cursor-pointer items-center gap-2 self-end pb-2 text-sm" x-show="driver === 'midtrans'" x-cloak><input type="checkbox" name="sandbox" value="1" class="check" @checked(old('sandbox', $pg->sandbox))> Mode uji coba (sandbox)</label>

                <div class="sm:col-span-2" x-show="driver === 'midtrans'" x-cloak>
                    <label class="label" for="midtrans_server_key">Midtrans Server Key</label>
                    <input id="midtrans_server_key" type="password" name="midtrans_server_key" class="input" autocomplete="new-password" placeholder="{{ $kunci['midtrans_server_key'] ?: 'Belum diisi' }}">
                    <p class="field-hint">Kosongkan bila tidak ingin mengubah kunci yang tersimpan. Isi <em>Payment Notification URL</em> di dashboard Midtrans dengan:<br><code class="break-all">{{ $pg->urlCallback('midtrans') }}</code></p>
                    @error('midtrans_server_key')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="driver === 'xendit'" x-cloak>
                    <label class="label" for="xendit_secret_key">Xendit Secret Key</label>
                    <input id="xendit_secret_key" type="password" name="xendit_secret_key" class="input" autocomplete="new-password" placeholder="{{ $kunci['xendit_secret_key'] ?: 'Belum diisi' }}">
                </div>
                <div x-show="driver === 'xendit'" x-cloak>
                    <label class="label" for="xendit_callback_token">Xendit Callback Verification Token</label>
                    <input id="xendit_callback_token" type="password" name="xendit_callback_token" class="input" autocomplete="new-password" placeholder="{{ $kunci['xendit_callback_token'] ?: 'Belum diisi' }}">
                    @error('xendit_callback_token')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <p class="field-hint sm:col-span-2" x-show="driver === 'xendit'" x-cloak>Di dashboard Xendit (Callbacks: Invoice paid dan Invoice expired) isi alamat:<br><code class="break-all">{{ $pg->urlCallback('xendit') }}</code></p>
                <p class="rounded-xl bg-accent-soft p-3 text-sm sm:col-span-2" x-show="driver === 'fake'" x-cloak><i class="bi bi-info-circle"></i> Mode simulasi untuk mencoba alur tanpa uang sungguhan. Hanya tersedia di lokal/uji.</p>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">2. Biaya admin gerbang</h2>
            <p class="mt-1 text-sm text-slate-600">Gerbang biasanya memotong biaya tiap transaksi. Pilih siapa yang menanggungnya. Bila dibebankan ke orang tua, jumlahnya ditambahkan saat mereka membayar.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <label class="label" for="biaya_admin_ditanggung">Biaya admin</label>
                    <select id="biaya_admin_ditanggung" name="biaya_admin_ditanggung" class="select">@foreach (\App\PengaturanPembayaranSekolah::DITANGGUNG as $k => $v)<option value="{{ $k }}" @selected(old('biaya_admin_ditanggung', $pg->biaya_admin_ditanggung) === $k)>{{ $v }}</option>@endforeach</select>
                </div>
                <div><label class="label" for="biaya_admin_tetap">Biaya tetap per transaksi (Rp)</label><input id="biaya_admin_tetap" type="number" min="0" max="100000" step="500" name="biaya_admin_tetap" value="{{ old('biaya_admin_tetap', $pg->biaya_admin_tetap) }}" class="input">@error('biaya_admin_tetap')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="biaya_admin_persen">Ditambah persen dari tagihan (%)</label><input id="biaya_admin_persen" type="number" min="0" max="10" step="0.1" name="biaya_admin_persen" value="{{ old('biaya_admin_persen', $pg->biaya_admin_persen) }}" class="input">@error('biaya_admin_persen')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </div>

        <button class="btn btn-primary btn-lg">Simpan Pengaturan</button>
    </form>

    <div class="card mt-6">
        <div class="card-header">Transaksi online terakhir</div>
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead><tr><th>Waktu</th><th>Nomor transaksi</th><th>Tagihan</th><th>Dibayar</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($transaksiTerakhir as $t)
                        <tr>
                            <td>{{ $t->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="font-mono text-xs">{{ $t->order_id }}</td>
                            <td>Rp {{ number_format($t->subtotal, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($t->total, 0, ',', '.') }}</td>
                            <td><span class="badge badge-{{ ['berhasil' => 'success', 'menunggu' => 'warning'][$t->status] ?? 'muted' }}">{{ \App\TransaksiSpp::STATUS[$t->status] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada transaksi online.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
