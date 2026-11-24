@extends('layouts.admin')

@section('title', 'Galeri')

@section('content')
    <x-admin.crud title="Galeri" subtitle="Foto kegiatan yang tampil di situs sekolah." name="Foto" add-label="Unggah Foto" add-icon="cloud-arrow-up"
        :url="route('admin/gallery.data')" :store="route('admin/gallery.store')" :destroy="url('admin/gallery/:id')">
        <x-slot:columns>
            <th data-col="no" class="w-14">No</th>
            <th data-col="url" data-cell="image" class="no-sort w-32">Foto</th>
            <th data-col="author">Diunggah oleh</th>
            <th data-col="tanggal" class="no-sort">Tanggal</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="foto" label="Pilih Foto" type="file" class="sm:col-span-2" accept="image/jpeg,image/png,image/webp" hint="Format JPG, PNG, atau WEBP. Maksimal 2 MB." required />
        </x-slot:fields>
    </x-admin.crud>
@endsection
