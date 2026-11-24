@extends('layouts.admin')

@section('title', 'Notifikasi WhatsApp')

@php $bawaan = collect($kejadian)->map(fn ($d) => $d['templat'])->all(); @endphp

@section('content')
    <x-admin.page-header title="Notifikasi WhatsApp" subtitle="Kabari orang tua otomatis lewat WhatsApp: siswa alpa, tagihan baru, pembayaran diterima, dan lainnya." />

    <div class="mb-6 rounded-xl p-4 text-sm {{ $pg->aktif ? 'bg-emerald-50 text-emerald-900' : 'bg-accent-soft text-ink' }}" role="status">
        @if ($pg->aktif)
            <i class="bi bi-check-circle-fill"></i> <strong>WhatsApp menyala.</strong> Pesan dikirim otomatis sesuai pilihan di bawah.
        @else
            <i class="bi bi-info-circle-fill"></i> <strong>WhatsApp masih mati.</strong> Tidak ada pesan yang dikirim sampai Anda menyalakannya.
        @endif
    </div>

    <form method="POST" action="{{ route('admin/wa.simpan') }}" class="space-y-6" x-data="{ driver: @js(old('driver', $pg->driver)), bawaan: @js($bawaan), t: @js(collect($kejadian)->keys()->mapWithKeys(fn ($k) => [$k => old('templat.'.$k, $pg->templat($k))])->all()) }">
        @csrf @method('PUT')

        {{-- ============================================================ 1. nyalakan --}}
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">1. Sambungkan WhatsApp</h2>
            <p class="mt-1 text-sm text-slate-600">Daftar dulu di layanan pengirim WhatsApp (Fonnte, Wablas, atau WhatsApp Cloud API), lalu isi kunci dari mereka di sini. Biaya pesan dibayar sekolah langsung ke layanan tersebut.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold sm:col-span-2"><input type="checkbox" name="aktif" value="1" class="check" @checked(old('aktif', $pg->aktif))> Nyalakan notifikasi WhatsApp</label>
                @error('aktif')<p class="field-error sm:col-span-2">{{ $message }}</p>@enderror

                <div>
                    <label class="label" for="driver">Layanan pengirim</label>
                    <select id="driver" name="driver" x-model="driver" class="select">@foreach ($driver as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                </div>
                <div x-show="driver !== 'fake'">
                    <label class="label" for="token"><span x-text="driver === 'cloud' ? 'Access Token' : 'Token'"></span></label>
                    <input id="token" type="password" name="token" class="input" autocomplete="new-password" placeholder="{{ $kunci['token'] ?: 'Belum diisi' }}">
                    <p class="field-hint">Kosongkan bila tidak ingin mengubah kunci yang tersimpan.</p>
                </div>
                <div x-show="driver === 'wablas'" x-cloak>
                    <label class="label" for="server_url">Alamat server Wablas</label>
                    <input id="server_url" type="url" name="server_url" value="{{ old('server_url', $pg->server_url) }}" class="input" placeholder="https://pati.wablas.com" maxlength="200">
                    @error('server_url')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="driver === 'wablas' || driver === 'cloud'" x-cloak>
                    <label class="label" for="kunci2"><span x-text="driver === 'cloud' ? 'Phone Number ID' : 'Secret key (bila ada)'"></span></label>
                    <input id="kunci2" type="password" name="kunci2" class="input" autocomplete="new-password" placeholder="{{ $kunci['kunci2'] ?: 'Belum diisi' }}">
                </div>
                <p class="rounded-xl bg-accent-soft p-3 text-sm sm:col-span-2" x-show="driver === 'cloud'" x-cloak><i class="bi bi-exclamation-triangle"></i> WhatsApp Cloud API hanya mengizinkan pesan bebas ke orang yang menghubungi nomor bisnis dalam 24 jam terakhir. Untuk pemberitahuan ke semua orang tua, Fonnte atau Wablas lebih praktis.</p>
                <p class="rounded-xl bg-accent-soft p-3 text-sm sm:col-span-2" x-show="driver === 'fake'" x-cloak><i class="bi bi-info-circle"></i> Mode simulasi: pesan tidak dikirim sungguhan. Hanya untuk lokal/uji.</p>

                <div>
                    <label class="label" for="nomor_admin">Nomor WhatsApp admin <span class="font-normal text-slate-400">(untuk pengingat langganan)</span></label>
                    <input id="nomor_admin" name="nomor_admin" value="{{ old('nomor_admin', $pg->nomor_admin) }}" class="input" placeholder="0812 3456 7890" inputmode="tel" maxlength="25">
                    @error('nomor_admin')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="batas_per_menit">Batas kirim per menit</label>
                    <input id="batas_per_menit" type="number" min="1" max="120" name="batas_per_menit" value="{{ old('batas_per_menit', $pg->batas_per_menit) }}" class="input">
                    <p class="field-hint">Supaya nomor tidak diblokir WhatsApp. Pesan berlebih menunggu giliran, tidak hilang.</p>
                    @error('batas_per_menit')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- ============================================================ 2. pesan --}}
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold">2. Pesan yang dikirim</h2>
            <p class="mt-1 text-sm text-slate-600">Hidupkan kejadian yang ingin dikabarkan dan ubah kalimatnya bila perlu. Kata dalam kurung kurawal, seperti <code>{nama_siswa}</code>, otomatis diganti dengan data sebenarnya.</p>
            <div class="mt-5 space-y-6">
                @foreach ($kejadian as $kode => $d)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <label class="flex cursor-pointer items-center gap-2 font-display font-bold"><input type="checkbox" name="event[{{ $kode }}]" value="1" class="check" @checked(old('event.'.$kode, $pg->kejadianAktif($kode)))> {{ $d['label'] }} <span class="badge badge-muted font-sans text-xs font-normal">ke {{ $d['penerima'] }}</span></label>
                        <textarea name="templat[{{ $kode }}]" x-model="t.{{ $kode }}" rows="5" maxlength="1000" class="textarea mt-3" aria-label="Isi pesan {{ $d['label'] }}"></textarea>
                        @error('templat.'.$kode)<p class="field-error">{{ $message }}</p>@enderror
                        <p class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">Isian yang bisa dipakai:
                            @foreach (array_merge($d['placeholder'], in_array('nama_sekolah', $d['placeholder'], true) ? [] : ['nama_sekolah']) as $p)<code class="rounded bg-slate-100 px-1.5 py-0.5">{{ '{'.$p.'}' }}</code>@endforeach
                            <button type="button" class="ml-auto text-primary underline" @click="t.{{ $kode }} = bawaan.{{ $kode }}">Kembalikan ke teks bawaan</button>
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <button class="btn btn-primary btn-lg">Simpan Pengaturan</button>
    </form>

    {{-- ============================================================ 3. uji --}}
    <div class="card mt-6 p-6">
        <h2 class="font-display text-lg font-bold">3. Coba kirim ke nomor Anda</h2>
        <p class="mt-1 text-sm text-slate-600">Simpan pengaturan dulu, lalu kirim satu pesan percobaan untuk memastikan semuanya tersambung.</p>
        <form method="POST" action="{{ route('admin/wa.uji') }}" class="mt-4 flex flex-wrap items-start gap-3">@csrf
            <div>
                <input name="nomor_uji" value="{{ old('nomor_uji') }}" class="input" placeholder="0812 3456 7890" inputmode="tel" maxlength="25" aria-label="Nomor tujuan percobaan">
                @error('nomor_uji')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-outline"><i class="bi bi-send"></i> Kirim Pesan Percobaan</button>
        </form>
    </div>

    {{-- ============================================================ riwayat --}}
    <div class="card mt-6">
        <div class="card-header">
            <span>Riwayat pesan (30 hari)</span>
            <span class="flex flex-wrap gap-2 text-xs font-normal">
                @foreach (\App\WaLog::STATUS as $k => $v)<span class="badge badge-{{ \App\WaLog::WARNA[$k] }}">{{ $v }}: {{ $ringkas[$k] ?? 0 }}</span>@endforeach
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead><tr><th>Waktu</th><th>Kejadian</th><th>Nomor</th><th>Status</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($riwayat as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $kejadian[$r->kejadian]['label'] ?? $r->kejadian }}</td>
                            <td class="font-mono text-xs">{{ substr($r->nomor, 0, 5) }}••••{{ substr($r->nomor, -3) }}</td>
                            <td><span class="badge badge-{{ \App\WaLog::WARNA[$r->status] }}">{{ \App\WaLog::STATUS[$r->status] }}</span></td>
                            <td class="text-slate-500">{{ $r->galat ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada pesan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
