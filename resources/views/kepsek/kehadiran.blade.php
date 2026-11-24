@extends('layouts.admin')

@section('title', 'Kehadiran')

@section('content')
    <x-admin.page-header title="Rekap Kehadiran" subtitle="Per rombel, {{ $label }}.">
        <form method="GET" class="flex items-center gap-2">
            <input type="month" name="bulan" value="{{ $bulan }}" max="{{ now()->format('Y-m') }}" class="input !w-auto" aria-label="Bulan" onchange="this.form.submit()">
        </form>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="table-clean">
            <thead><tr><th>Rombel</th><th>Wali Kelas</th><th>Siswa</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Kehadiran</th></tr></thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td class="font-semibold"><a href="{{ url('kepsek/kelas/open/'.$b['kelas']->id) }}">{{ $b['kelas']->nama_kelas }}</a></td>
                        <td class="text-slate-600">{{ $b['kelas']->wali?->nama_guru ?? '-' }}</td>
                        <td>{{ $b['kelas']->anggota_count }}</td>
                        <td>{{ $b['hadir'] }}</td><td>{{ $b['izin'] }}</td><td>{{ $b['sakit'] }}</td><td>{{ $b['alpa'] }}</td>
                        <td><span class="badge badge-{{ $b['persen'] === null ? 'muted' : ($b['persen'] >= 90 ? 'success' : ($b['persen'] >= 75 ? 'warning' : 'danger')) }}">{{ $b['persen'] === null ? '-' : number_format($b['persen'], 1, ',', '.').'%' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">Belum ada rombel pada tahun ajaran aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
