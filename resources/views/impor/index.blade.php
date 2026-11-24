@extends('layouts.admin')

@section('title', 'Impor Data')

@section('content')
    <x-admin.page-header title="Impor Data" subtitle="Tambah banyak guru, siswa, atau orang tua sekaligus dari Excel (.xlsx) atau CSV. Unduh template, isi, lalu unggah." />

    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        @foreach ($jenis as $kode => $def)
            <div class="card" x-data="impor(@js($kode), @js(route('admin/impor.periksa', $kode)), @js(route('admin/impor.simpan', $kode)))">
                <div class="card-header">Impor {{ $def['label'] }}</div>
                <div class="space-y-4 p-5">
                    <a href="{{ route('admin/impor.template', $kode) }}" class="btn btn-accent btn-block"><i class="bi bi-file-earmark-excel"></i> 1. Unduh Template {{ $def['label'] }}</a>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600" start="2">
                        <li>Buka di Excel, isi lembar <strong>Data</strong> mulai baris ke-3 (baris abu-abu hanya contoh). Judul <span class="font-semibold text-amber-700">oranye</span> wajib diisi. Aturan lengkap ada di lembar <strong>Petunjuk</strong>.</li>
                        <li>Simpan, lalu unggah berkasnya di bawah ini.</li>
                        <li>Periksa hasilnya, lalu klik <strong>Impor</strong>.</li>
                    </ol>
                    @if ($kode === 'orang_tua')<p class="rounded-xl bg-primary-soft p-3 text-xs text-slate-700">Impor siswa <strong>lebih dulu</strong>: orang tua dihubungkan ke anaknya lewat NISN. Template sudah berisi lembar <strong>Daftar Siswa</strong> untuk menyalin NISN.</p>@endif
                    <p class="text-xs text-slate-500">Kolom wajib: {{ implode(', ', $def['wajib']) }}. Maksimal {{ \App\Support\Impor::MAKS_BARIS }} baris, 2 MB.</p>

                    <div>
                        <label class="label" for="berkas-{{ $kode }}">Berkas</label>
                        <input id="berkas-{{ $kode }}" type="file" accept=".xlsx,.csv" class="input" x-ref="berkas" @change="periksa()">
                        <p x-show="galat" x-text="galat" class="field-error" x-cloak></p>
                    </div>

                    <div x-cloak x-show="hasil" class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <span class="badge badge-success"><span x-text="hasil.jumlah_valid"></span> baris siap diimpor</span>
                            <span class="badge" :class="hasil.jumlah_galat ? 'badge-danger' : 'badge-muted'"><span x-text="hasil.jumlah_galat"></span> bermasalah</span>
                        </div>

                        <div x-show="hasil.galat.length" class="max-h-48 overflow-y-auto rounded-xl border border-red-100 bg-red-50 p-3 text-sm text-red-800">
                            <p class="mb-1 font-semibold">Baris ini akan dilewati:</p>
                            <ul class="list-disc space-y-0.5 pl-5"><template x-for="g in hasil.galat" :key="g.baris"><li>Baris <span x-text="g.baris"></span>: <span x-text="g.pesan"></span></li></template></ul>
                        </div>

                        <div x-show="hasil.contoh.length" class="overflow-x-auto rounded-xl border border-slate-100">
                            <table class="table-clean">
                                <thead><tr><th>Baris</th><th>{{ ['guru' => 'NIP', 'orang_tua' => 'No. HP'][$kode] ?? 'NISN' }}</th><th>Nama</th><th>{{ ['guru' => 'Mata pelajaran', 'orang_tua' => 'Anak'][$kode] ?? 'Rombel' }}</th></tr></thead>
                                <tbody><template x-for="c in hasil.contoh" :key="c.baris"><tr><td x-text="c.baris"></td><td x-text="c.id"></td><td x-text="c.nama"></td><td x-text="c.info"></td></tr></template></tbody>
                            </table>
                            <p x-show="hasil.jumlah_valid > hasil.contoh.length" class="px-3 py-2 text-xs text-slate-500">…dan <span x-text="hasil.jumlah_valid - hasil.contoh.length"></span> baris lainnya.</p>
                        </div>

                        <button type="button" class="btn btn-primary" :disabled="sibuk || !hasil.jumlah_valid" @click="simpan()"><i class="bi bi-upload"></i> Impor <span x-text="hasil.jumlah_valid"></span> {{ strtolower($def['label']) }}</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('impor', (jenis, urlPeriksa, urlSimpan) => ({
                hasil: null, galat: '', sibuk: false,
                token() { return document.querySelector('meta[name=csrf-token]').content; },
                async kirim(url, body) {
                    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.token(), ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }) }, body });
                    const j = await r.json().catch(() => ({}));
                    if (!r.ok) throw new Error(j.errors?.berkas?.[0] || j.message || 'Terjadi kesalahan.');
                    return j;
                },
                async periksa() {
                    this.hasil = null; this.galat = '';
                    const f = this.$refs.berkas.files[0];
                    if (!f) return;
                    const fd = new FormData(); fd.append('berkas', f);
                    this.sibuk = true;
                    try { this.hasil = await this.kirim(urlPeriksa, fd); } catch (e) { this.galat = e.message; } finally { this.sibuk = false; }
                },
                async simpan() {
                    this.sibuk = true;
                    try {
                        const j = await this.kirim(urlSimpan, JSON.stringify({ token: this.hasil.token }));
                        window.toast(j.message);
                        this.hasil = null; this.$refs.berkas.value = '';
                    } catch (e) { window.toast(e.message, 'error'); } finally { this.sibuk = false; }
                },
            }));
        });
    </script>
@endsection
