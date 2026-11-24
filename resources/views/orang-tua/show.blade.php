@extends('layouts.admin')

@section('title', $ortu->nama)

@section('content')
    <x-admin.page-header :title="$ortu->nama" subtitle="Username {{ $ortu->user?->username }} · {{ $ortu->anak->count() }} anak terhubung" :back="route('admin/orang-tua')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <div class="card-header">Anak</div>
            <table class="table-clean">
                <thead><tr><th>Nama</th><th>Kelas</th><th>Hubungan</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($ortu->anak as $a)
                        <tr>
                            <td class="font-semibold">{{ $a->nama_siswa }}<br><span class="text-xs font-normal text-slate-500">{{ $a->nisn }}</span></td>
                            <td>{{ $a->kelas?->nama_kelas ?? '-' }}</td>
                            <td>{{ \App\OrangTua::HUBUNGAN[$a->pivot->hubungan] ?? $a->pivot->hubungan }}</td>
                            <td class="text-right"><form data-ajax-form data-method="DELETE" action="{{ route('admin/orang-tua.anak.lepas', [$ortu, $a->id]) }}" class="inline"><button type="submit" class="btn btn-soft btn-sm" title="Lepas hubungan"><i class="bi bi-person-dash"></i></button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada anak yang terhubung.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form data-ajax-form novalidate action="{{ route('admin/orang-tua.anak.tambah', $ortu) }}" class="card h-fit">
            <div class="card-header">Hubungkan Anak</div>
            <div class="space-y-3 p-5">
                <div><label class="label" for="ad_siswa">Siswa</label><select id="ad_siswa" name="siswa_id" class="select" required><option value="">- pilih -</option>@foreach ($calon as $c)<option value="{{ $c->id }}">{{ $c->nama_siswa }} · {{ $c->nisn }}</option>@endforeach</select></div>
                <div><label class="label" for="ad_hub">Hubungan</label><select id="ad_hub" name="hubungan" class="select">@foreach (\App\OrangTua::HUBUNGAN as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-link-45deg"></i> Hubungkan</button></div>
        </form>
    </div>
@endsection
