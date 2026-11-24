@extends('layouts.admin')

@section('title', 'Profil Saya')

@section('content')
    <x-admin.page-header title="Profil Saya" subtitle="Data akun orang tua/wali murid." />

    <div class="grid gap-6 lg:grid-cols-2">
        <form data-ajax-form data-method="PUT" novalidate action="{{ route('ortu/profile.update') }}" class="card">
            <div class="card-header">Data Diri</div>
            <div class="space-y-3 p-5">
                <div><label class="label" for="pf_nama">Nama</label><input id="pf_nama" name="nama" value="{{ $ortu?->nama ?? auth()->user()->name }}" class="input" maxlength="100" required></div>
                <div><label class="label" for="pf_hp">Nomor HP</label><input id="pf_hp" name="no_hp" value="{{ $ortu?->no_hp }}" class="input" maxlength="30"></div>
                <div><label class="label" for="pf_kerja">Pekerjaan</label><input id="pf_kerja" name="pekerjaan" value="{{ $ortu?->pekerjaan }}" class="input" maxlength="100"></div>
                <div><label class="label" for="pf_alamat">Alamat</label><input id="pf_alamat" name="alamat" value="{{ $ortu?->alamat }}" class="input" maxlength="255"></div>
                <p class="text-xs text-slate-500">Username: <strong>{{ auth()->user()->username }}</strong> (hubungi admin sekolah untuk mengubah).</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan</button></div>
        </form>

        <form data-ajax-form data-method="PUT" data-reset novalidate action="{{ route('ortu/profile.password') }}" class="card">
            <div class="card-header">Ganti Password</div>
            <div class="space-y-3 p-5">
                <div><label class="label" for="pw_lama">Password lama</label><input id="pw_lama" type="password" name="password_lama" class="input" autocomplete="current-password" required></div>
                <div><label class="label" for="pw_baru">Password baru</label><input id="pw_baru" type="password" name="password" class="input" minlength="6" autocomplete="new-password" required></div>
                <div><label class="label" for="pw_konf">Ulangi password baru</label><input id="pw_konf" type="password" name="password_confirmation" class="input" autocomplete="new-password" required></div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-key"></i> Ubah Password</button></div>
        </form>
    </div>
@endsection
