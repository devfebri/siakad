@extends('layouts.admin')

@section('title', $judul ?? 'Kehadiran Saya')

@section('content')
    @isset($daftarAnak)@include('ortu._anak', ['daftarAnak' => $daftarAnak, 'anak' => $anak])@endisset
    <x-admin.page-header :title="$judul ?? 'Kehadiran Saya'" :subtitle="$subjudul ?? 'Rekap kehadiran Anda per bulan.'" />

    <div x-data="kehadiran(@js($dataUrl ?? route('siswa/kehadiran.data')), @js(now()->format('Y-m')))">
        <div class="card mb-5"><div class="p-5 sm:max-w-xs">
            <label for="bulan" class="label">Bulan</label>
            <input id="bulan" type="month" x-model="bulan" :max="@js(now()->format('Y-m'))" @change="muat()" class="input">
        </div></div>

        <div x-cloak x-show="data" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-5">
                <template x-for="k in [['Hadir','hadir','success'],['Izin','izin','primary'],['Sakit','sakit','warning'],['Alpa','alpa','danger']]" :key="k[1]">
                    <div class="card p-5 text-center"><p class="font-display text-3xl font-extrabold" x-text="data.ringkasan[k[1]]"></p><span class="badge mt-1" :class="'badge-' + k[2]" x-text="k[0]"></span></div>
                </template>
                <div class="card bg-primary p-5 text-center text-white"><p class="font-display text-3xl font-extrabold text-accent" x-text="data.ringkasan.persen === null ? '-' : data.ringkasan.persen + '%'"></p><span class="text-xs text-white/70">Kehadiran</span></div>
            </div>
            <div class="card overflow-x-auto p-2">
                <table class="table-clean">
                    <thead><tr><th>Tanggal</th><th>Status</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <template x-for="d in data.daftar" :key="d.tanggal">
                            <tr><td x-text="d.tanggal"></td><td><span class="badge" :class="'badge-' + d.badge" x-text="d.status"></span></td><td class="text-slate-500" x-text="d.keterangan || '-'"></td></tr>
                        </template>
                        <tr x-show="data.daftar.length === 0"><td colspan="3" class="py-10 text-center text-slate-500">Belum ada data kehadiran bulan ini.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kehadiran', (url, bulanIni) => ({
            bulan: bulanIni, data: null,
            init() { this.muat(); },
            async muat() {
                const res = await fetch(`${url}?bulan=${this.bulan}`, { headers: { Accept: 'application/json' } });
                if (res.ok) this.data = await res.json();
            },
        }));
    });
</script>
@endpush
