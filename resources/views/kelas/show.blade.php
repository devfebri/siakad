@extends('layouts.admin')

@section('title', 'Rombel '.$kelas->nama_kelas)

@php $role = auth()->user()->panel; @endphp

@section('content')
    <x-admin.page-header :title="'Rombel '.$kelas->nama_kelas" subtitle="TA {{ $kelas->tahunAjaran?->nama }} · Wali kelas: {{ $kelas->wali?->nama_guru ?? 'belum ditentukan' }} · {{ $siswa->count() }} siswa"
        :back="$role !== 'siswa' ? route($role.'/kelas') : null" />

    @if ($role === 'admin' && $calon->isNotEmpty())
        <form data-ajax-form novalidate action="{{ route('admin/kelas.anggota.tambah', $kelas) }}" class="card mb-6">
            <div class="card-header">Tambah Siswa ke Rombel</div>
            <div class="p-5">
                <label class="label" for="calon">Siswa yang belum punya rombel di tahun ajaran ini (tahan Ctrl untuk memilih banyak)</label>
                <select id="calon" name="siswa[]" multiple size="6" class="select">@foreach ($calon as $c)<option value="{{ $c->id }}">{{ $c->nama_siswa }} · {{ $c->nisn }}</option>@endforeach</select>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Tambahkan</button></div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="table-clean">
            <thead><tr><th class="w-16">No</th><th>NISN</th><th>Nama</th><th>L/P</th>@if (\Illuminate\Support\Facades\Route::has($role.'/rapor') && \App\Support\AksesSekolah::modul('nilai_rapor'))<th>Rapor</th>@endif @if ($role === 'admin')<th class="text-right">Aksi</th>@endif</tr></thead>
            <tbody>
                @forelse ($siswa as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td><td>{{ $s->nisn }}</td><td class="font-semibold">{{ $s->nama_siswa }}</td><td>{{ \Illuminate\Support\Str::substr($s->jenis_kelamin, 0, 1) }}</td>
                        @if (\Illuminate\Support\Facades\Route::has($role.'/rapor') && \App\Support\AksesSekolah::modul('nilai_rapor'))<td><a href="{{ route($role.'/rapor', $s->id) }}" class="btn btn-soft btn-sm"><i class="bi bi-file-earmark-text"></i></a></td>@endif
                        @if ($role === 'admin')
                            <td class="text-right"><form data-ajax-form data-method="DELETE" action="{{ route('admin/kelas.anggota.keluar', [$kelas, $s->id]) }}" class="inline"><button type="submit" class="btn btn-soft btn-sm" title="Keluarkan dari rombel"><i class="bi bi-person-dash"></i></button></form></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada siswa di rombel ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
