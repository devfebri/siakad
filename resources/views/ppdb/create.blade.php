@extends('layouts.admin')

@section('title', 'Tambah Pendaftar PPDB')

@section('content')
    <x-admin.page-header title="Tambah Pendaftar" subtitle="Input pendaftar PPDB secara manual · Tahun ajaran {{ $tahunAjaran }}" :back="route('admin/ppdb')" />

    <form data-ajax-form novalidate enctype="multipart/form-data" action="{{ route('admin/ppdb.store') }}" class="card max-w-4xl">
        <div class="card-header">Data Calon Siswa</div>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <x-admin.field name="nama" id="p_nama" label="Nama lengkap" class="sm:col-span-2" maxlength="120" required />
            <x-admin.field name="jenis_kelamin" id="p_jk" label="Jenis kelamin" as="select" required>
                <option value="">Pilih...</option><option>Laki-Laki</option><option>Perempuan</option>
            </x-admin.field>
            <x-admin.field name="agama" id="p_agama" label="Agama" as="select" optional hint="Bisa dilengkapi nanti saat dijadikan siswa.">
                <option value="">Pilih...</option>@foreach (\App\PpdbPendaftar::AGAMA as $a)<option>{{ $a }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="nisn" id="p_nisn" label="NISN" maxlength="20" inputmode="numeric" optional hint="8–20 digit angka." />
            <x-admin.field name="tempat_lahir" id="p_tempat" label="Tempat lahir" maxlength="80" required />
            <x-admin.field name="tanggal_lahir" id="p_tgl" label="Tanggal lahir" type="date" max="{{ now()->subDay()->toDateString() }}" required />
            <x-admin.field name="asal_sekolah" id="p_asal" label="Asal sekolah" class="sm:col-span-2" maxlength="120" required />
            <x-admin.field name="alamat" id="p_alamat" label="Alamat" as="textarea" rows="2" class="sm:col-span-2" maxlength="500" required />
            <x-admin.field name="nama_ortu" id="p_ortu" label="Nama orang tua/wali" maxlength="120" required />
            <x-admin.field name="telepon" id="p_telp" label="Telepon/WhatsApp" type="tel" maxlength="20" required />
            <x-admin.field name="email" id="p_email" label="Email" type="email" maxlength="150" optional />
            <x-admin.field name="nilai_rata" id="p_nilai" label="Nilai rata-rata" type="number" step="0.01" min="0" max="100" optional />
            <x-admin.field name="jalur" id="p_jalur" label="Jalur" as="select" required>
                <option value="">Pilih...</option>
                @foreach ($jalur as $j)<option>{{ $j }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="peminatan" id="p_minat" label="Peminatan" as="select" optional>
                <option value="">-</option>
                @foreach ($peminatan as $m)<option>{{ $m }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="status" id="p_status" label="Status awal" as="select">
                @foreach (\App\PpdbPendaftar::STATUS as $key => $s)<option value="{{ $key }}">{{ $s['label'] }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="berkas" id="p_berkas" label="Berkas pendukung" type="file" class="sm:col-span-2" accept="application/pdf,image/jpeg,image/png" optional hint="PDF/JPG/PNG, maks. 2 MB." />
            <x-admin.field name="catatan" id="p_catatan" label="Catatan" as="textarea" rows="2" class="sm:col-span-2" maxlength="1000" optional />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
            <a href="{{ route('admin/ppdb') }}" class="btn btn-outline">Batal</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Simpan Pendaftar</button>
        </div>
    </form>
@endsection
