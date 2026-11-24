@extends('layouts.admin')

@section('title', 'Berita')

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.crud title="Berita" subtitle="{{ $role === 'admin' ? 'Semua berita yang tayang di situs sekolah.' : 'Berita yang Anda tulis.' }}" name="Berita"
        add-label="Tulis Berita" add-icon="pencil-square" :add-href="route($role.'/berita.create')"
        :url="route($role.'/berita.data')" :update="url($role.'/berita/:id')" :destroy="url($role.'/berita/:id')"
        :edit-link="url($role.'/berita/:id/edit')" :show="url('berita/:id')" show-key="slug" show-blank>
        <x-slot:actions>
            <a href="{{ route($role.'/kategori') }}" class="btn btn-outline"><i class="bi bi-tags"></i> Kategori</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="gambar_url" data-cell="image" class="no-sort w-32">Gambar</th>
            <th data-col="berita_judul">Judul</th>
            <th data-col="kategori" class="no-sort">Kategori</th>
            @if ($role === 'admin')<th data-col="author">Penulis</th>@endif
            <th data-col="berita_views">Dibaca</th>
            <th data-col="tanggal" class="no-sort">Tanggal</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
    </x-admin.crud>
@endsection
