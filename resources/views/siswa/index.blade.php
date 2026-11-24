@extends('layouts.admin')

@section('title', 'Data Siswa')

@section('content')
    <x-admin.crud title="Data Siswa" subtitle="Kelola data siswa dan akun login mereka." name="Siswa" size="lg" add-icon="person-plus"
        :url="route('admin/siswa.data')" :store="route('admin/siswa.store')"
        :update="url('admin/siswa/:id')" :destroy="url('admin/siswa/:id')">
        <x-slot:actions>
            <a href="{{ route('admin/impor') }}" class="btn btn-outline"><i class="bi bi-file-earmark-excel"></i> Impor Excel</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="avatar_url" data-cell="avatar" class="no-sort w-16">Foto</th>
            <th data-col="nama_siswa">Nama</th>
            <th data-col="nisn">NISN</th>
            <th data-col="username" class="no-sort">Username</th>
            <th data-col="email" class="no-sort">Email</th>
            <th data-col="kelas" class="no-sort">Kelas</th>
            <th data-col="jenis_kelamin" class="no-sort">L/P</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nisn" label="NISN" minlength="5" maxlength="100" required />
            <x-admin.field name="nama_siswa" label="Nama Lengkap" maxlength="100" required />
            <x-admin.field name="username" label="Username (untuk login)" maxlength="50" autocomplete="off" optional hint="Huruf kecil/angka. Kosongkan untuk memakai NISN." />
            <div data-field>
                <x-admin.field name="email" label="Email" type="email" maxlength="191" data-lock-on-edit required />
                <p class="field-hint" data-create-only>Password awal: <strong>siswa123</strong>. Siswa dapat menggantinya di halaman profil.</p>
            </div>
            <x-admin.field name="kelas_id" label="Kelas" as="select" required>
                <option value="">- pilih -</option>
                @foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="agama" label="Agama" as="select" required>
                <option value="">- pilih -</option>
                @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'] as $agama)<option value="{{ $agama }}">{{ $agama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="jenis_kelamin" label="Jenis Kelamin" as="select" required>
                <option value="">- pilih -</option>
                <option value="Laki-Laki">Laki-Laki</option>
                <option value="Perempuan">Perempuan</option>
            </x-admin.field>
            <x-admin.field name="tanggal_lahir" label="Tanggal Lahir" type="date" required />
            <x-admin.field name="avatar" label="Foto" type="file" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WEBP, maksimal 500 KB." optional />
            <x-admin.field name="password" label="Password Baru" type="password" class="sm:col-span-2" minlength="6" autocomplete="new-password" hint="Kosongkan jika tidak diubah." data-edit-only hidden optional />
        </x-slot:fields>
    </x-admin.crud>
@endsection
