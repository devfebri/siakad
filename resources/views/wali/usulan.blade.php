@extends('layouts.admin')

@section('title', 'Usulan Kenaikan')

@section('content')
    <x-admin.page-header title="Usulan Kenaikan" subtitle="Usulkan naik/tinggal kelas{{ $aktif ? ' · TA '.$aktif->nama : '' }}. Admin yang memproses keputusan akhirnya." />

    @forelse ($rombel as $r)
        @php $opsi = $r['akhir'] ? ['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak lulus', 'tinggal' => 'Tinggal kelas'] : ['naik' => 'Naik kelas', 'tinggal' => 'Tinggal kelas']; @endphp
        <form data-ajax-form data-method="PUT" novalidate action="{{ route('guru/usulan.simpan', $r['kelas']) }}" class="card mb-6">
            <div class="card-header">Rombel {{ $r['kelas']->nama_kelas }} <span class="badge badge-muted">{{ $r['siswa']->count() }} siswa</span>@if ($r['akhir'])<span class="badge badge-primary">Tingkat akhir</span>@endif</div>
            <div class="overflow-x-auto">
                <table class="table-clean">
                    <thead><tr><th class="w-12">No</th><th>Nama</th><th>Usulan</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @forelse ($r['siswa'] as $s)
                            @php $u = $usulan->get($s->id); @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="font-semibold">{{ $s->nama_siswa }}<br><span class="text-xs font-normal text-slate-500">{{ $s->nisn }}</span></td>
                                <td><select name="usulan[{{ $s->id }}]" class="select !w-auto" aria-label="Usulan {{ $s->nama_siswa }}"><option value="">- belum diusulkan -</option>@foreach ($opsi as $k => $v)<option value="{{ $k }}" @selected($u?->usulan === $k)>{{ $v }}</option>@endforeach</select></td>
                                <td><input name="catatan[{{ $s->id }}]" value="{{ $u?->catatan }}" class="input" maxlength="300" aria-label="Catatan {{ $s->nama_siswa }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada siswa di rombel ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan usulan</button></div>
        </form>
    @empty
        <div class="card p-10 text-center text-slate-500">Anda belum ditetapkan sebagai wali kelas pada tahun ajaran aktif.</div>
    @endforelse
@endsection
