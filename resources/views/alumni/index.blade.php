@extends('layouts.admin')

@section('title', 'Data Alumni')

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.crud title="Data Alumni" subtitle="Lulusan per angkatan. Pencarian berdasarkan nama atau NISN." name="Alumni" :url="route($role.'/alumni.data')">
        <x-slot:actions>
            <select id="filter-angkatan" data-crud-filter="angkatan" class="select !w-auto" aria-label="Angkatan">
                <option value="">Semua angkatan</option>
                @foreach ($angkatan as $a)<option value="{{ $a }}">Angkatan {{ $a }}</option>@endforeach
            </select>
            <a href="{{ route($role.'/alumni.export') }}" class="btn btn-outline btn-sm" id="ekspor-alumni"><i class="bi bi-file-earmark-excel"></i> Ekspor Excel</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="angkatan">Angkatan</th>
            <th data-col="nisn" class="no-sort">NISN</th>
            <th data-col="nama" class="no-sort">Nama</th>
            <th data-col="tanggal_lulus">Tanggal Lulus</th>
            <th data-col="no_ijazah" class="no-sort">No. Ijazah</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
        </x-slot:columns>
    </x-admin.crud>

    <script>
        // ekspor mengikuti filter angkatan yang dipilih
        document.getElementById('filter-angkatan')?.addEventListener('change', (e) => {
            const a = document.getElementById('ekspor-alumni'); const u = new URL(a.href);
            e.target.value ? u.searchParams.set('angkatan', e.target.value) : u.searchParams.delete('angkatan'); a.href = u;
        });
    </script>
@endsection
