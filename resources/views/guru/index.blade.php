@extends('layouts.admin')

@section('title', 'Data Guru')

@section('content')
    <x-admin.crud title="Data Guru" subtitle="Kelola data guru dan akun login mereka." name="Guru" size="lg" add-icon="person-plus"
        :url="route('admin/guru.data')" :store="route('admin/guru.store')"
        :update="url('admin/guru/:id')" :destroy="url('admin/guru/:id')">
        <x-slot:actions>
            <a href="{{ route('admin/impor') }}" class="btn btn-outline"><i class="bi bi-file-earmark-excel"></i> Impor Excel</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="avatar_url" data-cell="avatar" class="no-sort w-16">Foto</th>
            <th data-col="nama_guru">Nama</th>
            <th data-col="nip_guru">NIP</th>
            <th data-col="username" class="no-sort">Username</th>
            <th data-col="email" class="no-sort">Email</th>
            <th data-col="mapel" class="no-sort">Mapel</th>
            <th data-col="no_hp" class="no-sort">No. HP</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nip_guru" label="NIP" maxlength="100" required />
            <x-admin.field name="nama_guru" label="Nama Lengkap" maxlength="200" required />
            <x-admin.field name="username" label="Username (untuk login)" maxlength="50" autocomplete="off" optional hint="Huruf kecil/angka. Kosongkan untuk memakai NIP." />
            <div data-field>
                <x-admin.field name="email" label="Email" type="email" maxlength="191" data-lock-on-edit required />
                <p class="field-hint" data-create-only>Password awal: <strong>guru123</strong>. Guru dapat menggantinya di halaman profil.</p>
            </div>
            <x-admin.field name="mapel_id" label="Mata Pelajaran" as="select" required>
                <option value="">- pilih -</option>
                @foreach ($mapel as $m)<option value="{{ $m->id }}">{{ $m->mapel_nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="jenis_kelamin" label="Jenis Kelamin" as="select" required>
                <option value="">- pilih -</option>
                <option value="Laki-Laki">Laki-Laki</option>
                <option value="Perempuan">Perempuan</option>
            </x-admin.field>
            <x-admin.field name="no_hp" label="No. HP" maxlength="100" inputmode="tel" required />
            <x-admin.field name="tanggal_lahir" label="Tanggal Lahir" type="date" required />
            <x-admin.field name="avatar" label="Foto" type="file" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WEBP, maksimal 500 KB." optional />
            <x-admin.field name="password" label="Password Baru" type="password" class="sm:col-span-2" minlength="6" autocomplete="new-password" hint="Kosongkan jika tidak diubah." data-edit-only hidden optional />
        </x-slot:fields>
    </x-admin.crud>
@endsection
