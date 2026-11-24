@extends('layouts.admin')

@section('title', 'PPDB')

@php $status = \App\Support\Ppdb::status(); @endphp

@section('content')
    <x-admin.page-header title="PPDB" subtitle="Penerimaan peserta didik baru {{ $status['tahun_ajaran'] }} · {{ $status['label'] }}">
        <a href="{{ route('admin/ppdb.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Pendaftar</a>
        <a href="{{ route('admin/pengaturan', ['tab' => 'ppdb']) }}" class="btn btn-outline"><i class="bi bi-gear"></i> Atur PPDB</a>
        <a href="{{ route('ppdb') }}" target="_blank" rel="noopener" class="btn btn-outline"><i class="bi bi-box-arrow-up-right"></i> Halaman Pendaftaran</a>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Menunggu verifikasi" :value="$ringkasan['menunggu'] ?? 0" icon="hourglass-split" color="accent" />
        <x-admin.stat-card label="Diterima" :value="$ringkasan['diterima'] ?? 0" icon="check-circle" color="emerald" />
        <x-admin.stat-card label="Ditolak" :value="$ringkasan['ditolak'] ?? 0" icon="x-circle" color="rose" />
    </div>

    @if ($hasilKonversi)
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            <p class="font-bold"><i class="bi bi-check-circle-fill"></i> {{ $hasilKonversi['dibuat'] }} pendaftar dijadikan siswa.</p>
            @if ($hasilKonversi['dilewati'])
                <p class="mt-2 font-semibold text-amber-800">{{ count($hasilKonversi['dilewati']) }} dilewati, lengkapi lalu jadikan siswa satu per satu lewat tombol Detail:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-amber-900">@foreach ($hasilKonversi['dilewati'] as $d)<li>{{ $d['nama'] }}: {{ $d['alasan'] }}</li>@endforeach</ul>
            @endif
        </div>
    @endif

    @if ($siapKonversi > 0)
        <div class="card mb-6 p-5">
            <h2 class="font-display text-lg font-bold">Jadikan siswa</h2>
            <p class="mt-1 text-sm text-slate-600"><strong>{{ $siapKonversi }}</strong> pendaftar yang diterima belum dijadikan siswa. Satu per satu lewat tombol <em>Detail</em>, atau semuanya sekaligus di sini (yang datanya belum lengkap, misalnya agama, dilewati dan dilaporkan).</p>
            <form method="POST" action="{{ route('admin/ppdb.jadikan-massal') }}" class="mt-4 flex flex-wrap items-end gap-3" onsubmit="return confirm('Jadikan semua pendaftar yang diterima sebagai siswa? Akun siswa dan orang tua akan dibuat.')">@csrf
                <div>
                    <label class="label" for="rombel_massal">Tempatkan di rombel</label>
                    <select id="rombel_massal" name="rombel_id" class="select"><option value="">Belum ditempatkan</option>@foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach</select>
                </div>
                <button class="btn btn-accent"><i class="bi bi-people-fill"></i> Jadikan Siswa Semua yang Diterima</button>
            </form>
        </div>
    @endif

    <x-admin.crud bare title="Daftar Pendaftar" name="Pendaftar" :url="route('admin/ppdb.data')"
        :show="url('admin/ppdb/:id')" show-label="Detail" show-icon="eye" :destroy="url('admin/ppdb/:id')">
        <x-slot:actions>
            <select data-crud-filter="status" class="select !w-auto" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach (\App\PpdbPendaftar::STATUS as $key => $s)<option value="{{ $key }}">{{ $s['label'] }}</option>@endforeach
            </select>
            <select data-crud-filter="jalur" class="select !w-auto" aria-label="Filter jalur">
                <option value="">Semua jalur</option>
                @foreach (\App\PpdbPendaftar::JALUR as $j)<option value="{{ $j }}">{{ $j }}</option>@endforeach
            </select>
            <a href="{{ route('admin/ppdb.export') }}" class="btn btn-outline btn-sm" id="ekspor"><i class="bi bi-download"></i> Ekspor CSV</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="no_pendaftaran">No. Pendaftaran</th>
            <th data-col="nama">Nama</th>
            <th data-col="asal_sekolah" class="no-sort">Asal Sekolah</th>
            <th data-col="jalur" class="no-sort">Jalur</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="jadi_siswa" data-cell="badge" class="no-sort">Siswa</th>
            <th data-col="tanggal" class="no-sort">Mendaftar</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
    </x-admin.crud>
@endsection

@push('scripts')
<script>
    // Ekspor mengikuti filter status yang dipilih
    document.querySelector('[data-crud-filter="status"]').addEventListener('change', function () {
        var link = document.getElementById('ekspor');
        link.href = @js(route('admin/ppdb.export')) + (this.value ? '?status=' + encodeURIComponent(this.value) : '');
    });
</script>
@endpush
