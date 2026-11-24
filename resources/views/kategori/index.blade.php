@extends('layouts.admin')

@section('title', 'Kategori Berita')

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.crud title="Kategori Berita" subtitle="Kelompokkan berita agar mudah ditemukan pengunjung." name="Kategori"
        :url="route($role.'/kategori.data')" :store="route($role.'/kategori.store')"
        :update="url($role.'/kategori/:id')" :destroy="url($role.'/kategori/:id')" :back="route($role.'/berita')">
        <x-slot:columns>
            <th data-col="no" class="w-16">No</th>
            <th data-col="kategori_nama">Kategori</th>
            <th data-col="berita_count">Jumlah Berita</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="kategori_nama" label="Nama Kategori" class="sm:col-span-2" maxlength="100" required />
        </x-slot:fields>
    </x-admin.crud>
@endsection
