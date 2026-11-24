@extends('layouts.admin')

@section('title', 'Rapor '.$siswa->nama_siswa)

@php
    $role = auth()->user()->panel;
    $kh = $rapor['kehadiran'];
@endphp

@section('content')
    @isset($daftarAnak)@include('ortu._anak', ['daftarAnak' => $daftarAnak, 'anak' => $anak])@endisset
    <div class="print:hidden">
        <x-admin.page-header title="Rapor Siswa" :subtitle="$siswa->nama_siswa" :back="in_array($role, ['siswa', 'ortu'], true) ? null : route($role === 'kepsek' ? 'kepsek/kelas' : $role.'/nilai')">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="tahun_ajaran" class="select !w-auto" aria-label="Tahun ajaran" onchange="this.form.submit()">
                    @foreach ($pilihanTahun as $t)<option value="{{ $t }}" @selected($t === $rapor['tahun_ajaran'])>{{ $t }}</option>@endforeach
                </select>
                <select name="semester" class="select !w-auto" aria-label="Semester" onchange="this.form.submit()">
                    @foreach (['Ganjil', 'Genap'] as $s)<option value="{{ $s }}" @selected($s === $rapor['semester'])>{{ $s }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-accent" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
            </form>
        </x-admin.page-header>
    </div>

    <article class="card mx-auto max-w-4xl p-6 sm:p-10 print:max-w-none print:rounded-none print:border-0 print:shadow-none">
        <header class="mb-6 flex items-center gap-4 border-b-2 border-primary pb-5">
            <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary text-4xl text-accent">
                @if (config('school.logo'))<img src="{{ asset(config('school.logo')) }}" alt="" class="size-full bg-white object-contain">@else<i class="bi bi-mortarboard-fill"></i>@endif
            </span>
            <div>
                <p class="font-display text-xl font-extrabold text-primary">{{ config('school.name') }}</p>
                <p class="text-sm text-slate-500">{{ config('school.alamat') ?: config('school.tagline') }}</p>
            </div>
            <p class="ml-auto font-display text-lg font-bold">LAPORAN HASIL BELAJAR</p>
        </header>

        <dl class="mb-6 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <div class="flex gap-2"><dt class="w-32 text-slate-500">Nama</dt><dd class="font-semibold">: {{ $siswa->nama_siswa }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 text-slate-500">Kelas</dt><dd class="font-semibold">: {{ $siswa->kelas?->nama_kelas ?? '-' }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 text-slate-500">NISN</dt><dd class="font-semibold">: {{ $siswa->nisn }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 text-slate-500">Tahun ajaran</dt><dd class="font-semibold">: {{ $rapor['tahun_ajaran'] }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 text-slate-500">Semester</dt><dd class="font-semibold">: {{ $rapor['semester'] }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 text-slate-500">KKM</dt><dd class="font-semibold">: {{ $rapor['kkm'] }}</dd></div>
        </dl>

        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="w-10">No</th><th>Mata Pelajaran</th>
                        @foreach ($jenis as $j)<th class="text-center">{{ $j['label'] }}</th>@endforeach
                        <th class="text-center">Nilai Akhir</th><th class="text-center">Predikat</th><th class="text-center">Ket.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rapor['baris'] as $b)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="font-semibold">{{ $b['mapel'] }}</td>
                            @foreach (array_keys($jenis) as $kode)<td class="text-center">{{ $b['jenis'][$kode] !== null ? rtrim(rtrim(number_format($b['jenis'][$kode], 2, ',', ''), '0'), ',') : '-' }}</td>@endforeach
                            <td class="text-center font-bold">{{ $b['akhir'] !== null ? number_format($b['akhir'], 2, ',', '') : '-' }}</td>
                            <td class="text-center"><span class="badge {{ in_array($b['predikat'], ['A', 'B']) ? 'badge-success' : ($b['predikat'] === 'C' ? 'badge-warning' : 'badge-danger') }}">{{ $b['predikat'] }}</span></td>
                            <td class="text-center text-xs">{{ $b['tuntas'] === null ? '-' : ($b['tuntas'] ? 'Tuntas' : 'Belum tuntas') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 5 + count($jenis) }}" class="py-10 text-center text-slate-500">Belum ada nilai pada semester ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($rapor['rata'] !== null)
                    <tfoot><tr><td colspan="{{ 2 + count($jenis) }}" class="px-4 py-3 text-right font-bold">Rata-rata nilai akhir</td><td class="px-4 py-3 text-center font-display text-lg font-extrabold text-primary">{{ number_format($rapor['rata'], 2, ',', '') }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>

        <section class="mt-6 grid gap-6 sm:grid-cols-2">
            <div>
                <h2 class="mb-2 font-display font-bold">Kehadiran</h2>
                <dl class="space-y-1 text-sm">
                    @foreach (['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Tanpa keterangan (alpa)'] as $k => $label)
                        <div class="flex justify-between border-b border-slate-100 py-1"><dt>{{ $label }}</dt><dd class="font-semibold">{{ $kh[$k] }} hari</dd></div>
                    @endforeach
                    <div class="flex justify-between py-1"><dt>Persentase kehadiran</dt><dd class="font-semibold">{{ $kh['persen'] !== null ? $kh['persen'].'%' : '-' }}</dd></div>
                </dl>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-2 text-center text-sm">
                <div><p class="mb-16 text-slate-500">Wali Kelas</p><p class="border-t border-slate-400 pt-1 font-semibold">(................................)</p></div>
                <div><p class="mb-16 text-slate-500">Kepala Sekolah</p><p class="border-t border-slate-400 pt-1 font-semibold">{{ config('school.kepsek_nama') ?: '(................................)' }}</p></div>
            </div>
        </section>

        <p class="mt-6 text-xs text-slate-400">Bobot nilai akhir: @foreach ($jenis as $j){{ $j['label'] }} {{ $j['bobot'] }}%{{ ! $loop->last ? ', ' : '' }}@endforeach. Predikat: A ≥ 90, B ≥ 80, C ≥ 70, D ≥ 60, E &lt; 60.</p>
    </article>
@endsection
