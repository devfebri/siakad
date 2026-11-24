@extends('layouts.admin')

@section('title', 'Nilai & Rapor')

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.page-header title="Nilai & Rapor" subtitle="{{ $akademik['label'] }}" />

    <div x-data="nilai({
            siswaUrl: @js(route($role.'/nilai.siswa')), simpanUrl: @js(route($role.'/nilai.simpan')), daftarUrl: @js(route($role.'/nilai.daftar')),
            raporUrl: @js(url($role.'/rapor/:id')),
        })">
        <div class="mb-5 flex flex-wrap gap-2" role="tablist">
            <button type="button" role="tab" @click="tab = 'input'" :aria-selected="tab === 'input'" class="btn btn-sm" :class="tab === 'input' ? 'btn-primary' : 'btn-outline'"><i class="bi bi-pencil-square"></i> Input Nilai</button>
            <button type="button" role="tab" @click="tab = 'rapor'" :aria-selected="tab === 'rapor'" class="btn btn-sm" :class="tab === 'rapor' ? 'btn-primary' : 'btn-outline'"><i class="bi bi-file-earmark-text"></i> Rapor Siswa</button>
        </div>

        {{-- ============ Input nilai ============ --}}
        <div x-show="tab === 'input'">
            <div class="card mb-5"><div class="grid gap-4 p-5 sm:grid-cols-3">
                <div><label for="n-kelas" class="label">Kelas</label>
                    <select id="n-kelas" x-model="kelas" @change="muat()" class="select"><option value="">- pilih -</option>@foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach</select></div>
                <div><label for="n-mapel" class="label">Mata Pelajaran</label>
                    <select id="n-mapel" x-model="mapel" @change="muat()" class="select"><option value="">- pilih -</option>@foreach ($mapel as $m)<option value="{{ $m->id }}">{{ $m->mapel_nama }}</option>@endforeach</select></div>
                <div><label for="n-jenis" class="label">Jenis Penilaian</label>
                    <select id="n-jenis" x-model="jenis" @change="muat()" class="select">@foreach ($jenis as $kode => $j)<option value="{{ $kode }}">{{ $j['label'] }} (bobot {{ $j['bobot'] }}%)</option>@endforeach</select></div>
            </div></div>

            <p x-show="!(kelas && mapel)" class="card py-12 text-center text-sm text-slate-500"><i class="bi bi-pencil-square text-3xl text-slate-300"></i><br>Pilih kelas dan mata pelajaran.</p>
            <p x-cloak x-show="kelas && mapel && memuat" class="card py-12 text-center text-sm text-slate-500">Memuat...</p>

            <div x-cloak x-show="kelas && mapel && !memuat" class="card">
                <div class="overflow-x-auto p-2">
                    <table class="table-clean">
                        <thead><tr><th class="w-12">No</th><th>Siswa</th><th class="w-40">Nilai (0–100)</th></tr></thead>
                        <tbody>
                            <template x-for="(s, i) in siswa" :key="s.id">
                                <tr>
                                    <td x-text="i + 1"></td>
                                    <td><span class="block font-semibold" x-text="s.nama"></span><span class="text-xs text-slate-500" x-text="s.nisn"></span></td>
                                    <td><input type="number" min="0" max="100" step="0.01" x-model="s.nilai" class="input !py-1.5" :class="galat(s) ? 'is-invalid' : ''" :aria-label="'Nilai ' + s.nama"></td>
                                </tr>
                            </template>
                            <tr x-show="siswa.length === 0"><td colspan="3" class="py-10 text-center text-slate-500">Belum ada siswa di kelas ini.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Kosongkan kolom untuk menghapus nilai. Nilai akhir rapor dihitung dari bobot tiap jenis.</p>
                    <button type="button" class="btn btn-primary" :disabled="menyimpan || siswa.length === 0" @click="simpan()"><i class="bi bi-save"></i> <span x-text="menyimpan ? 'Menyimpan...' : 'Simpan Nilai'"></span></button>
                </div>
            </div>
        </div>

        {{-- ============ Rapor ============ --}}
        <div x-cloak x-show="tab === 'rapor'">
            <div class="card"><div class="grid gap-4 p-5 sm:grid-cols-3">
                <div><label for="r-kelas" class="label">Kelas</label>
                    <select id="r-kelas" x-model="raporKelas" @change="muatDaftar()" class="select"><option value="">- pilih -</option>@foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label for="r-siswa" class="label">Siswa</label>
                    <select id="r-siswa" x-model="raporSiswa" class="select"><option value="">- pilih siswa -</option><template x-for="s in daftar" :key="s.id"><option :value="s.id" x-text="s.nama"></option></template></select></div>
                <div class="sm:col-span-3"><a :href="raporSiswa ? raporUrl.replace(':id', raporSiswa) : '#'" class="btn btn-primary" :class="raporSiswa ? '' : 'pointer-events-none opacity-50'"><i class="bi bi-file-earmark-text"></i> Buka Rapor</a></div>
            </div></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('nilai', (cfg) => ({
            tab: 'input', kelas: '', mapel: @js($mapel->count() === 1 ? (string) $mapel->first()->id : ''), jenis: 'tugas', siswa: [], memuat: false, menyimpan: false,
            raporKelas: '', raporSiswa: '', daftar: [], raporUrl: cfg.raporUrl,

            galat(s) { return s.nilai !== null && s.nilai !== '' && (Number(s.nilai) < 0 || Number(s.nilai) > 100); },
            async panggil(url, opsi = {}) {
                const res = await fetch(url, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' }, ...opsi });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Terjadi kesalahan.');
                return json;
            },
            async muat() {
                if (!(this.kelas && this.mapel)) { this.siswa = []; return; }
                this.memuat = true;
                try { this.siswa = (await this.panggil(`${cfg.siswaUrl}?kelas_id=${this.kelas}&mapel_id=${this.mapel}&jenis=${this.jenis}`)).siswa; }
                catch (e) { window.toast(e.message, 'error'); } finally { this.memuat = false; }
            },
            async simpan() {
                if (this.siswa.some(s => this.galat(s))) { window.toast('Ada nilai di luar 0–100.', 'error'); return; }
                this.menyimpan = true;
                try {
                    const j = await this.panggil(cfg.simpanUrl, { method: 'POST', body: JSON.stringify({ kelas_id: this.kelas, mapel_id: this.mapel, jenis: this.jenis, items: this.siswa.map(s => ({ siswa_id: s.id, nilai: s.nilai === '' ? null : s.nilai })) }) });
                    window.toast(j.message);
                } catch (e) { window.toast(e.message, 'error'); } finally { this.menyimpan = false; }
            },
            async muatDaftar() {
                this.raporSiswa = ''; this.daftar = [];
                if (!this.raporKelas) return;
                try { this.daftar = (await this.panggil(`${cfg.daftarUrl}?kelas_id=${this.raporKelas}`)).siswa; } catch (e) { window.toast(e.message, 'error'); }
            },
        }));
    });
</script>
@endpush
