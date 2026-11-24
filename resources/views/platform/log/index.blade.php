@extends('layouts.admin')

@section('title', 'Log Aktivitas')

@section('content')
    <x-admin.crud title="Log Aktivitas" subtitle="Catatan tindakan penting di seluruh sekolah dan platform. Saring menurut sekolah, pengguna, jenis tindakan, atau tanggal." name="Log" :url="route('platform/log.data')">
        <x-slot:actions>
            <a id="unduhLog" href="{{ route('platform/log.ekspor') }}" class="btn btn-outline"><i class="bi bi-filetype-csv"></i> Unduh CSV</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="waktu" class="no-sort">Waktu</th>
            <th data-col="pengguna" class="no-sort">Pengguna</th>
            <th data-col="sekolah" class="no-sort">Sekolah</th>
            <th data-col="aksi">Aksi</th>
            <th data-col="deskripsi" class="no-sort">Deskripsi</th>
            <th data-col="ip" class="no-sort">IP</th>
        </x-slot:columns>
    </x-admin.crud>
@endsection

@push('scripts')
<script>
    // Saringan di atas tabel: dipasang lewat JS supaya memakai mekanisme [data-crud-filter] yang sama dengan halaman lain.
    document.addEventListener('DOMContentLoaded', () => {
        const wadah = document.querySelector('[data-crud]');
        const bar = document.createElement('div');
        bar.className = 'mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5';
        bar.innerHTML = `
            <select data-crud-filter="sekolah_id" class="select" aria-label="Sekolah"><option value="">Semua sekolah</option><option value="platform">Platform saja</option>@foreach ($sekolah as $s)<option value="{{ $s->id }}">{{ addslashes($s->nama) }}</option>@endforeach</select>
            <input data-crud-filter="pengguna" class="input" placeholder="Pengguna (nama/username)" aria-label="Pengguna">
            <select data-crud-filter="aksi" class="select" aria-label="Jenis tindakan"><option value="">Semua tindakan</option>@foreach ($jenisAksi as $kode => $nama)<option value="{{ $kode }}">{{ $nama }}</option>@endforeach</select>
            <input data-crud-filter="dari" type="date" class="input" aria-label="Dari tanggal" title="Dari tanggal">
            <input data-crud-filter="sampai" type="date" class="input" aria-label="Sampai tanggal" title="Sampai tanggal">`;
        wadah.querySelector('.card').before(bar);

        const unduh = document.getElementById('unduhLog');
        const perbarui = () => {
            const p = new URLSearchParams();
            bar.querySelectorAll('[data-crud-filter]').forEach((el) => el.value && p.set(el.dataset.crudFilter, el.value));
            unduh.href = @js(route('platform/log.ekspor')) + (p.toString() ? '?' + p : '');
        };
        bar.addEventListener('change', () => { perbarui(); $(wadah.querySelector('table')).DataTable().ajax.reload(); });
    });
</script>
@endpush
