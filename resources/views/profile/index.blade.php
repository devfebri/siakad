@extends('layouts.admin')

@section('title', 'Profil Saya')

@php
    $role = $user->panel;
    $siswa = $role === 'siswa';
    $namaField = $siswa ? 'nama_siswa' : 'nama_guru';
    $namaValue = $siswa ? $profil?->nama_siswa : $profil?->nama_guru;
@endphp

@section('content')
    <x-admin.page-header title="Profil Saya" subtitle="Perbarui data diri dan password akun Anda." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card h-fit text-center">
            <div class="px-5 py-8">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="mx-auto mb-4 size-28 rounded-3xl object-cover shadow-lift">
                @else
                    <span class="mx-auto mb-4 grid size-24 place-items-center rounded-3xl bg-primary font-display text-4xl font-bold text-white shadow-lift">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                @endif
                <h2 class="font-display text-lg font-bold">{{ $user->name }}</h2>
                <p class="badge badge-accent mt-1 capitalize">{{ $role }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $user->email }}</p>
                @if ($extra)
                    <dl class="mt-5 space-y-2 border-t border-slate-100 pt-5 text-left text-sm">
                        @foreach ($extra as $label => $value)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form data-ajax-form data-method="PUT" enctype="multipart/form-data" novalidate action="{{ route($role.'/profile.update') }}" class="card">
                @csrf
                <div class="card-header">Data Diri</div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.field :name="$namaField" label="Nama Lengkap" class="sm:col-span-2" maxlength="200" value="{{ $namaValue ?? $user->name }}" required />
                    @if ($profil)
                        <x-admin.field name="jenis_kelamin" label="Jenis Kelamin" as="select" required>
                            <option value="">- pilih -</option>
                            @foreach (['Laki-Laki', 'Perempuan'] as $jk)<option value="{{ $jk }}" @selected($jenisKelamin === $jk)>{{ $jk }}</option>@endforeach
                        </x-admin.field>
                        <x-admin.field name="tanggal_lahir" label="Tanggal Lahir" type="date" value="{{ $tanggalLahir }}" required />
                        @unless ($siswa)
                            <x-admin.field name="no_hp" label="No. HP" maxlength="100" value="{{ $profil->no_hp }}" required />
                        @endunless
                        <x-admin.field name="avatar" label="Foto" type="file" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WEBP, maksimal 500 KB." class="{{ $siswa ? 'sm:col-span-2' : '' }}" />
                    @endif
                </div>
                <div class="card-footer text-right"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan Profil</button></div>
            </form>

            <form data-ajax-form data-reset data-method="PUT" novalidate action="{{ route($role.'/profile.password') }}" class="card">
                @csrf
                <div class="card-header">Ganti Password</div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <x-admin.field name="password_lama" label="Password Lama" type="password" class="sm:col-span-2" autocomplete="current-password" required />
                    <x-admin.field name="password" label="Password Baru" type="password" minlength="6" autocomplete="new-password" required />
                    <x-admin.field name="password_confirmation" label="Ulangi Password Baru" type="password" minlength="6" autocomplete="new-password" required />
                </div>
                <div class="card-footer text-right"><button type="submit" class="btn btn-accent"><i class="bi bi-key"></i> Ubah Password</button></div>
            </form>
        </div>
    </div>
@endsection
