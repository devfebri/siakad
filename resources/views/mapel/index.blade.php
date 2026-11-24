@extends('layouts.admin')

@section('title', 'Mata Pelajaran')

@php
    $role = auth()->user()->panel;
    $canManage = $role === 'admin'; // guru hanya melihat daftar
@endphp

@section('content')
    @if ($canManage)@include('kelas._tab')@endif
    <x-admin.crud title="Mata Pelajaran" subtitle="Daftar mata pelajaran yang diajarkan di sekolah." name="Mata Pelajaran"
        :url="route($role.'/mapel.data')"
        :store="$canManage ? route('admin/mapel.store') : null"
        :update="$canManage ? url('admin/mapel/:id') : null"
        :destroy="$canManage ? url('admin/mapel/:id') : null">
        <x-slot:columns>
            <th data-col="no" class="w-16">No</th>
            <th data-col="mapel_nama">Mata Pelajaran</th>
            <th data-col="created_at">Dibuat</th>
            @if ($canManage)<th data-col="actions" class="no-sort text-right">Aksi</th>@endif
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="mapel_nama" label="Nama Mata Pelajaran" class="sm:col-span-2" maxlength="200" required />
        </x-slot:fields>
    </x-admin.crud>
@endsection
