@extends('layouts.admin')

@section('title', 'Rombel')

@php
    $role = auth()->user()->panel;
    $canManage = $role === 'admin'; // guru hanya melihat dan membuka detail rombel
@endphp

@section('content')
    @if ($canManage)@include('kelas._tab')@endif
    <x-admin.crud title="Rombongan Belajar" subtitle="Kelas per tahun ajaran beserta wali kelas dan jumlah siswanya." name="Rombel"
        :url="route($role.'/kelas.data')" :show="url($role.'/kelas/open/:id')"
        :store="$canManage ? route('admin/kelas.store') : null"
        :update="$canManage ? url('admin/kelas/:id') : null"
        :destroy="$canManage ? url('admin/kelas/:id') : null">
        <x-slot:actions>
            <select id="filter-ta" data-crud-filter="tahun_ajaran_id" class="select !w-auto" aria-label="Tahun ajaran">
                @foreach ($tahunAjaran as $t)<option value="{{ $t->id }}" @selected($t->id === $aktifId)>TA {{ $t->nama }}{{ $t->id === $aktifId ? ' (aktif)' : '' }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-16">No</th>
            <th data-col="nama_kelas">Rombel</th>
            <th data-col="tingkat" class="no-sort">Tingkat</th>
            <th data-col="wali" class="no-sort">Wali Kelas</th>
            <th data-col="siswa_count">Jumlah Siswa</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nama_kelas" label="Nama Rombel" maxlength="100" placeholder="mis. X IPA 1" required />
            <x-admin.field name="tingkat" label="Tingkat" maxlength="10" placeholder="mis. X atau 7" optional />
            <x-admin.field name="wali_guru_id" id="kl_wali" label="Wali Kelas" as="select" optional>
                <option value="">- belum ada -</option>@foreach ($guru as $g)<option value="{{ $g->id }}">{{ $g->nama_guru }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="tahun_ajaran_id" id="kl_ta" label="Tahun Ajaran" as="select" data-create-only>
                @foreach ($tahunAjaran as $t)<option value="{{ $t->id }}" @selected($t->id === $aktifId)>{{ $t->nama }}</option>@endforeach
            </x-admin.field>
        </x-slot:fields>
    </x-admin.crud>

    @if ($canManage && $tahunAjaran->count() > 1)
        <form data-ajax-form novalidate action="{{ route('admin/kelas.salin') }}" class="card mt-6">
            <div class="card-header">Salin Rombel ke Tahun Ajaran Lain</div>
            <div class="grid gap-3 p-5 sm:grid-cols-3">
                <div><label class="label" for="sl_dari">Dari</label><select id="sl_dari" name="dari" class="select">@foreach ($tahunAjaran as $t)<option value="{{ $t->id }}">{{ $t->nama }}</option>@endforeach</select></div>
                <div><label class="label" for="sl_ke">Ke</label><select id="sl_ke" name="ke" class="select">@foreach ($tahunAjaran->reverse() as $t)<option value="{{ $t->id }}">{{ $t->nama }}</option>@endforeach</select></div>
                <div class="flex items-end"><button type="submit" class="btn btn-outline btn-block"><i class="bi bi-copy"></i> Salin struktur</button></div>
            </div>
            <p class="px-5 pb-4 text-xs text-slate-500">Hanya nama dan tingkat yang disalin; wali kelas dan anggota diatur ulang. Rombel dengan nama yang sudah ada dilewati.</p>
        </form>
    @endif
@endsection
