@extends('layouts.admin')

@section('title', 'Jenis Biaya')

@section('content')
    @include('keuangan._tab')
    <x-admin.crud title="Jenis Biaya" subtitle="Daftar biaya yang ditagihkan ke siswa, misalnya SPP, uang gedung, atau seragam. Atur nominal sekali di sini." name="Jenis Biaya"
        :url="route($panel.'/keuangan/biaya.data')" :store="route($panel.'/keuangan/biaya.store')"
        :update="url($panel.'/keuangan/biaya/:id')" :destroy="url($panel.'/keuangan/biaya/:id')" add-label="Tambah Jenis Biaya">
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nama">Nama biaya</th>
            <th data-col="nominal_rp" class="no-sort">Nominal</th>
            <th data-col="jenis_label" class="no-sort">Jenis</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nama" id="jb_nama" label="Nama biaya" class="sm:col-span-2" maxlength="100" required hint="Contoh: SPP, Uang Gedung, Seragam." />
            <x-admin.field name="nominal" id="jb_nominal" label="Nominal (Rp)" type="number" min="0" step="1000" required hint="Tanpa titik. Contoh: 150000." />
            <x-admin.field name="jenis" id="jb_jenis" label="Jenis" as="select" required>
                @foreach (\App\JenisBiaya::JENIS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="aktif" id="jb_aktif" label="Status" as="select" class="sm:col-span-2">
                <option value="1">Aktif (bisa ditagihkan)</option><option value="0">Nonaktif</option>
            </x-admin.field>
        </x-slot:fields>
    </x-admin.crud>
@endsection
