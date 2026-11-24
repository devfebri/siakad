@extends('layouts.admin')

@section('title', 'Orang Tua')

@section('content')
    <x-admin.crud title="Orang Tua / Wali Murid" subtitle="Akun portal orang tua dan siswa yang terhubung. Login memakai username." name="Orang Tua"
        :url="route('admin/orang-tua.data')" :store="route('admin/orang-tua.store')" :update="url('admin/orang-tua/:id')" :destroy="url('admin/orang-tua/:id')"
        :show="url('admin/orang-tua/:id')" show-label="Kelola anak" show-icon="people">
        <x-slot:actions>
            <a href="{{ route('admin/impor') }}" class="btn btn-outline"><i class="bi bi-file-earmark-excel"></i> Impor Excel</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nama">Nama</th>
            <th data-col="username" class="no-sort">Username</th>
            <th data-col="no_hp" class="no-sort">No. HP</th>
            <th data-col="anak_count">Jumlah Anak</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nama" id="ot_nama" label="Nama" class="sm:col-span-2" maxlength="100" required />
            <x-admin.field name="username" id="ot_user" label="Username" maxlength="50" autocomplete="off" hint="Huruf kecil, angka, titik, strip." required />
            <x-admin.field name="password" id="ot_pw" label="Password" type="password" minlength="6" autocomplete="new-password" optional hint="Kosong = password awal ortu123 (saat tambah) / tidak diubah (saat ubah)." />
            <x-admin.field name="no_hp" id="ot_hp" label="Nomor HP" maxlength="30" optional />
            <x-admin.field name="pekerjaan" id="ot_kerja" label="Pekerjaan" maxlength="100" optional />
            <x-admin.field name="email" id="ot_email" label="Email" type="email" maxlength="191" optional data-create-only />
            <x-admin.field name="hubungan" id="ot_hub" label="Hubungan dengan anak pertama" as="select" data-create-only>
                @foreach (\App\OrangTua::HUBUNGAN as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="siswa_id" id="ot_siswa" label="Anak (opsional)" as="select" class="sm:col-span-2" data-create-only>
                <option value="">- hubungkan nanti -</option>@foreach ($siswa as $s)<option value="{{ $s->id }}">{{ $s->nama_siswa }} · {{ $s->nisn }}</option>@endforeach
            </x-admin.field>
        </x-slot:fields>
    </x-admin.crud>
@endsection
