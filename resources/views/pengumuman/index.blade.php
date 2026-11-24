@extends('layouts.admin')

@section('title', 'Pengumuman')

@section('content')
    <x-admin.crud title="Pengumuman" subtitle="Informasi resmi yang tampil di situs sekolah." name="Pengumuman" size="lg"
        :url="route('admin/pengumuman.data')" :store="route('admin/pengumuman.store')"
        :update="url('admin/pengumuman/:id')" :destroy="url('admin/pengumuman/:id')">
        <x-slot:columns>
            <th data-col="no" class="w-14">No</th>
            <th data-col="pengumuman_judul">Judul</th>
            <th data-col="ringkas" class="no-sort">Isi</th>
            <th data-col="pengumuman_author">Penulis</th>
            <th data-col="tanggal" class="no-sort">Tanggal</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="pengumuman_judul" label="Judul" class="sm:col-span-2" maxlength="150" required />
            <x-admin.field name="pengumuman_deskripsi" label="Isi Pengumuman" as="textarea" class="sm:col-span-2" rows="6" required />
        </x-slot:fields>
    </x-admin.crud>
@endsection
