@extends('layouts.admin')

@section('title', 'Absensi')

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.page-header title="Absensi" subtitle="Catat kehadiran siswa per kelas dan lihat rekapnya." />

    <div x-data="absensi({
            siswaUrl: @js(route($role.'/absensi.siswa')), simpanUrl: @js(route($role.'/absensi.simpan')), rekapUrl: @js(route($role.'/absensi.rekap')),
            hariIni: @js(now()->toDateString()), bulanIni: @js(now()->format('Y-m')),
        })">
        <div class="mb-5 flex flex-wrap gap-2" role="tablist">
            <button type="button" role="tab" @click="tab = 'catat'" :aria-selected="tab === 'catat'" class="btn btn-sm" :class="tab === 'catat' ? 'btn-primary' : 'btn-outline'"><i class="bi bi-check2-square"></i> Catat Absensi</button>
            <button type="button" role="tab" @click="tab = 'rekap'; muatRekap()" :aria-selected="tab === 'rekap'" class="btn btn-sm" :class="tab === 'rekap' ? 'btn-primary' : 'btn-outline'"><i class="bi bi-table"></i> Rekap Bulanan</button>
        </div>

        <div class="card mb-5">
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label for="abs-kelas" class="label">Kelas</label>
                    <select id="abs-kelas" x-model="kelas" @change="tab === 'catat' ? muat() : muatRekap()" class="select">
                        <option value="">- pilih kelas -</option>
                        @foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
                    </select>
                </div>
                <div x-show="tab === 'catat'">
                    <label for="abs-tanggal" class="label">Tanggal</label>
                    <input id="abs-tanggal" type="date" x-model="tanggal" :max="hariIni" @change="muat()" class="input">
                </div>
                <div x-cloak x-show="tab === 'rekap'">
                    <label for="abs-bulan" class="label">Bulan</label>
                    <input id="abs-bulan" type="month" x-model="bulan" :max="bulanIni" @change="muatRekap()" class="input">
                </div>
            </div>
        </div>

        {{-- ============ Catat absensi ============ --}}
        <div x-show="tab === 'catat'">
            <p x-show="!kelas" class="card py-12 text-center text-sm text-slate-500"><i class="bi bi-people text-3xl text-slate-300"></i><br>Pilih kelas untuk mulai mencatat.</p>
            <p x-cloak x-show="kelas && memuat" class="card py-12 text-center text-sm text-slate-500">Memuat daftar siswa...</p>
            <p x-cloak x-show="kelas && !memuat && siswa.length === 0" class="card py-12 text-center text-sm text-slate-500">Belum ada siswa di kelas ini.</p>

            <div x-cloak x-show="kelas && !memuat && siswa.length > 0" class="card">
                <div class="card-header flex-wrap">
                    <span>Daftar Kehadiran <span class="badge badge-muted ml-1" x-show="sudahTercatat">Sudah tercatat</span></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="s in ringkasan" :key="s.kode"><span class="badge" :class="'badge-' + s.badge" x-text="s.label + ': ' + s.jumlah"></span></template>
                        <button type="button" class="btn btn-outline btn-sm" @click="semua('hadir')"><i class="bi bi-check-all"></i> Semua hadir</button>
                    </div>
                </div>
                <div class="overflow-x-auto p-2">
                    <table class="table-clean">
                        <thead><tr><th class="w-12">No</th><th>Siswa</th><th>Status</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            <template x-for="(s, i) in siswa" :key="s.id">
                                <tr>
                                    <td x-text="i + 1"></td>
                                    <td><span class="block font-semibold" x-text="s.nama"></span><span class="text-xs text-slate-500" x-text="s.nisn"></span></td>
                                    <td>
                                        <div class="inline-flex gap-1" role="radiogroup" :aria-label="'Status ' + s.nama">
                                            @foreach ($status as $kode => $info)
                                                <button type="button" role="radio" :aria-checked="s.status === '{{ $kode }}'" @click="s.status = '{{ $kode }}'"
                                                        class="grid size-9 cursor-pointer place-items-center rounded-lg border text-sm font-bold transition"
                                                        :class="s.status === '{{ $kode }}' ? '{{ ['hadir' => 'border-emerald-500 bg-emerald-500 text-white', 'izin' => 'border-primary bg-primary text-white', 'sakit' => 'border-amber-500 bg-amber-500 text-white', 'alpa' => 'border-red-500 bg-red-500 text-white'][$kode] }}' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                                        title="{{ $info['label'] }}">{{ $info['huruf'] }}</button>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td><input type="text" x-model="s.keterangan" maxlength="150" class="input !py-1.5" placeholder="Opsional" :aria-label="'Keterangan ' + s.nama" x-show="s.status !== 'hadir'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">H = Hadir · I = Izin · S = Sakit · A = Alpa</p>
                    <button type="button" class="btn btn-primary" :disabled="menyimpan" @click="simpan()"><i class="bi bi-save"></i> <span x-text="menyimpan ? 'Menyimpan...' : 'Simpan Absensi'"></span></button>
                </div>
            </div>
        </div>

        {{-- ============ Rekap ============ --}}
        <div x-cloak x-show="tab === 'rekap'">
            <p x-show="!kelas" class="card py-12 text-center text-sm text-slate-500">Pilih kelas untuk melihat rekap.</p>
            <div x-cloak x-show="kelas && rekap" class="card">
                <div class="card-header"><span>Rekap <span x-text="rekap?.bulan"></span></span></div>
                <div class="overflow-x-auto p-2">
                    <table class="table-clean">
                        <thead><tr><th class="w-12">No</th><th>Siswa</th><th class="text-center">H</th><th class="text-center">I</th><th class="text-center">S</th><th class="text-center">A</th><th class="text-right">Kehadiran</th></tr></thead>
                        <tbody>
                            <template x-for="(r, i) in rekap?.baris ?? []" :key="r.id">
                                <tr>
                                    <td x-text="i + 1"></td>
                                    <td><span class="block font-semibold" x-text="r.nama"></span><span class="text-xs text-slate-500" x-text="r.nisn"></span></td>
                                    <td class="text-center" x-text="r.hadir"></td><td class="text-center" x-text="r.izin"></td><td class="text-center" x-text="r.sakit"></td><td class="text-center font-semibold" :class="r.alpa > 0 ? 'text-red-600' : ''" x-text="r.alpa"></td>
                                    <td class="text-right"><span class="badge" :class="r.persen === null ? 'badge-muted' : (r.persen >= 90 ? 'badge-success' : (r.persen >= 75 ? 'badge-warning' : 'badge-danger'))" x-text="r.persen === null ? '-' : r.persen + '%'"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('absensi', (cfg) => ({
            tab: 'catat', kelas: '', tanggal: cfg.hariIni, bulan: cfg.bulanIni, hariIni: cfg.hariIni, bulanIni: cfg.bulanIni,
            siswa: [], sudahTercatat: false, memuat: false, menyimpan: false, rekap: null,
            labels: @js(collect($status)->map(fn ($s, $k) => ['kode' => $k, 'label' => $s['label'], 'badge' => $s['badge']])->values()),

            get ringkasan() { return this.labels.map(l => ({ ...l, jumlah: this.siswa.filter(s => s.status === l.kode).length })); },
            semua(status) { this.siswa.forEach(s => s.status = status); },

            async panggil(url, opsi = {}) {
                const res = await fetch(url, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' }, ...opsi });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Terjadi kesalahan.');
                return json;
            },
            async muat() {
                if (!this.kelas || !this.tanggal) { this.siswa = []; return; }
                this.memuat = true;
                try {
                    const j = await this.panggil(`${cfg.siswaUrl}?kelas_id=${this.kelas}&tanggal=${this.tanggal}`);
                    this.siswa = j.siswa; this.sudahTercatat = j.sudah_tercatat;
                } catch (e) { window.toast(e.message, 'error'); } finally { this.memuat = false; }
            },
            async simpan() {
                this.menyimpan = true;
                try {
                    const j = await this.panggil(cfg.simpanUrl, { method: 'POST', body: JSON.stringify({ kelas_id: this.kelas, tanggal: this.tanggal, items: this.siswa.map(s => ({ siswa_id: s.id, status: s.status, keterangan: s.status === 'hadir' ? null : s.keterangan })) }) });
                    window.toast(j.message); this.sudahTercatat = true;
                } catch (e) { window.toast(e.message, 'error'); } finally { this.menyimpan = false; }
            },
            async muatRekap() {
                if (!this.kelas || !this.bulan) { this.rekap = null; return; }
                try { this.rekap = await this.panggil(`${cfg.rekapUrl}?kelas_id=${this.kelas}&bulan=${this.bulan}`); }
                catch (e) { window.toast(e.message, 'error'); }
            },
        }));
    });
</script>
@endpush
