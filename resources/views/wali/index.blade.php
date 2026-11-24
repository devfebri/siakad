@extends('layouts.admin')

@section('title', 'Kelas Wali')

@section('content')
    <x-admin.page-header title="Kelas Wali" subtitle="Siswa di rombel yang Anda ampu · {{ $semester->label }}" />

    @forelse ($rombel as $r)
        <div class="card mb-6 overflow-x-auto">
            <div class="card-header">Rombel {{ $r['kelas']->nama_kelas }} <span class="badge badge-muted">{{ $r['siswa']->count() }} siswa</span></div>
            <table class="table-clean">
                <thead><tr><th class="w-12">No</th><th>Nama</th><th>Kehadiran bulan ini</th><th>Rata-rata nilai</th><th>Orang tua/wali</th><th class="text-right">Rapor</th></tr></thead>
                <tbody>
                    @forelse ($r['siswa'] as $s)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="font-semibold">{{ $s['model']->nama_siswa }}<br><span class="text-xs font-normal text-slate-500">{{ $s['model']->nisn }}</span></td>
                            <td>
                                @if ($s['kehadiran']['persen'] === null) <span class="text-slate-400">-</span>
                                @else {{ number_format($s['kehadiran']['persen'], 0) }}% <span class="text-xs text-slate-500">(S {{ $s['kehadiran']['sakit'] }} · I {{ $s['kehadiran']['izin'] }} · A {{ $s['kehadiran']['alpa'] }})</span>
                                @endif
                            </td>
                            <td>{{ $s['rata'] === null ? '-' : number_format($s['rata'], 1, ',', '.') }}</td>
                            <td class="text-slate-600">{{ $s['orangTua'] ?: '-' }}</td>
                            <td class="text-right"><a href="{{ route('guru/rapor', $s['model']->id) }}" class="btn btn-soft btn-sm"><i class="bi bi-file-earmark-text"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada siswa di rombel ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="card p-10 text-center text-slate-500">Anda belum ditetapkan sebagai wali kelas pada tahun ajaran aktif.</div>
    @endforelse
@endsection
