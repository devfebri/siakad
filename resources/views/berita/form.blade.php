@extends('layouts.admin')

@php
    $role = auth()->user()->panel;
    $editing = (bool) $berita;
@endphp

@section('title', $editing ? 'Ubah Berita' : 'Tulis Berita')

@section('content')
    <x-admin.page-header :title="$editing ? 'Ubah Berita' : 'Tulis Berita'" subtitle="Berita tayang di halaman berita situs sekolah." :back="route($role.'/berita')" />

    <form data-ajax-form enctype="multipart/form-data"
          action="{{ $editing ? url($role.'/berita/'.$berita->id) : route($role.'/berita.store') }}"
          @if ($editing) data-method="PUT" @endif novalidate class="grid gap-6 lg:grid-cols-3">
        @csrf
        <div class="card lg:col-span-2">
            <div class="space-y-5 p-5">
                <x-admin.field name="berita_judul" label="Judul" maxlength="100" value="{{ $berita?->berita_judul }}" class="[&_input]:text-base [&_input]:font-semibold" required />
                <div data-field>
                    <label class="label" id="isi-label">Isi Berita</label>
                    <input type="hidden" id="berita_isi" name="berita_isi" value="{{ $berita?->berita_isi }}">
                    <div data-editor="berita_isi" aria-labelledby="isi-label"></div>
                </div>
            </div>
        </div>

        <div class="card h-fit">
            <div class="space-y-5 p-5">
                <x-admin.field name="kategori_id" label="Kategori" as="select" required>
                    <option value="">- pilih -</option>
                    @foreach ($kategori as $k)
                        <option value="{{ $k->id }}" @selected($berita?->kategori_id === $k->id)>{{ $k->kategori_nama }}</option>
                    @endforeach
                </x-admin.field>
                <div data-field>
                    <label for="berita_gambar" class="label">Gambar Utama</label>
                    @if ($editing)
                        <img data-preview src="{{ $berita->gambar_url }}" alt="" class="mb-2 block max-h-48 w-full rounded-xl object-cover">
                    @endif
                    <input type="file" id="berita_gambar" name="berita_gambar" class="input" accept="image/jpeg,image/png,image/webp">
                    <p class="field-hint">JPG, PNG, atau WEBP, maksimal 2 MB{{ $editing ? '. Kosongkan jika tidak diganti.' : '.' }}</p>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-send"></i> {{ $editing ? 'Simpan Perubahan' : 'Publikasikan' }}</button>
            </div>
        </div>
    </form>
@endsection
