@extends('layouts.admin')

@section('title', 'Sekolah')

@section('content')
    <x-admin.crud title="Sekolah Penyewa" subtitle="Kelola sekolah, subdomain/domain, dan akun admin sekolah pertama." name="Sekolah" add-label="Tambah Sekolah" add-icon="building-add"
        :url="route('platform/sekolah.data')" :store="route('platform/sekolah.store')" :update="url('platform/sekolah/:id')"
        :show="url('platform/sekolah/:id')" show-label="Detail" show-icon="eye">
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nama">Sekolah</th>
            <th data-col="domain" data-cell="tautan" class="no-sort">Domain</th>
            <th data-col="jenjang">Jenjang</th>
            <th data-col="langganan" class="no-sort">Langganan</th>
            <th data-col="status_sewa" data-cell="badge" class="no-sort">Sewa</th>
            <th data-col="status_label" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="nama" id="sk_nama" label="Nama sekolah" class="sm:col-span-2" maxlength="150" required />
            <x-admin.field name="npsn" id="sk_npsn" label="NPSN" maxlength="20" optional />
            <x-admin.field name="jenjang" id="sk_jenjang" label="Jenjang" as="select" required>
                @foreach ($jenjang as $j)<option>{{ $j }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="subdomain" id="sk_sub" label="Subdomain" maxlength="60" required hint="Huruf kecil, angka, strip. Alamat: subdomain.{{ config('sekolah.domain_utama') ?: 'domain-utama' }}" />
            <x-admin.field name="domain_kustom" id="sk_domain" label="Domain kustom" maxlength="150" optional hint="Mis. sekolahku.sch.id (tanpa www)." />
            <x-admin.field name="status" id="sk_status" label="Status" as="select">
                @foreach (\App\Sekolah::STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </x-admin.field>
            <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="tanpa_batas" value="1" class="check"> Tanpa batas langganan (internal/demo): semua modul aktif, tidak pernah baca-saja</label>
            <div class="border-t border-slate-100 pt-3 sm:col-span-2" data-create-only><p class="font-display font-bold">Akun admin sekolah pertama</p></div>
            <x-admin.field name="admin_name" id="sk_an" label="Nama admin" data-create-only maxlength="100" />
            <x-admin.field name="admin_username" id="sk_au" label="Username admin" data-create-only maxlength="50" autocomplete="off" />
            <x-admin.field name="admin_email" id="sk_ae" label="Email admin" type="email" data-create-only maxlength="191" />
            <x-admin.field name="admin_password" id="sk_ap" label="Password admin" type="password" data-create-only minlength="6" autocomplete="new-password" />
        </x-slot:fields>
    </x-admin.crud>
@endsection
