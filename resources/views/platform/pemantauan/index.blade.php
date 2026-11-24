@extends('layouts.admin')

@section('title', 'Pemantauan Sekolah')

@php
    $warnaSewa = ['aktif' => 'success', 'tenggang' => 'warning', 'baca_saja' => 'danger', 'belum_ada' => 'danger', 'tanpa_batas' => 'primary'];
    $labelSewa = ['aktif' => 'Aktif', 'tenggang' => 'Tenggang', 'baca_saja' => 'Baca-saja', 'belum_ada' => 'Belum berlangganan', 'tanpa_batas' => 'Tanpa batas'];
@endphp

@section('content')
    <x-admin.page-header title="Pemantauan Sekolah" subtitle="Penggunaan setiap sekolah: pengguna, penyimpanan, aktivitas masuk, akses API, dan status langganan.">
        <form method="POST" action="{{ route('platform/pemantauan.segarkan') }}">@csrf<button class="btn btn-outline"><i class="bi bi-arrow-repeat"></i> Hitung ulang penyimpanan</button></form>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Sekolah" :value="$baris->count()" icon="buildings" color="primary" />
        <x-admin.stat-card label="Siswa aktif" :value="number_format($total['siswa'], 0, ',', '.')" icon="mortarboard" color="emerald" />
        <x-admin.stat-card label="Penyimpanan berkas" :value="\App\Support\Pemantauan::ukuranTeks((int) $total['ukuran'])" icon="hdd" color="sky" />
        <x-admin.stat-card label="Request API (30 hari)" :value="number_format($total['api'], 0, ',', '.')" icon="plug" color="accent" />
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" name="cari" value="{{ $cari }}" class="input !w-auto" placeholder="Cari nama atau subdomain" aria-label="Cari sekolah">
        <select name="status" class="select !w-auto" aria-label="Status sekolah">
            <option value="">Semua sekolah</option><option value="aktif" @selected($status === 'aktif')>Aktif</option><option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
        </select>
        <button class="btn btn-primary">Saring</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-clean">
            <thead><tr><th>Sekolah</th><th class="text-right">Siswa</th><th class="text-right">Guru</th><th class="text-right">Akun</th><th class="text-right">Penyimpanan</th><th>Login terakhir</th><th class="text-right">API 30 hari</th><th>Langganan</th><th>Backup terakhir</th></tr></thead>
            <tbody>
                @forelse ($baris as $b)
                    @php $s = $b['sekolah']; @endphp
                    <tr>
                        <td><a href="{{ route('platform/sekolah.show', $s) }}" class="font-semibold no-underline">{{ $s->nama }}</a><br><span class="text-xs text-slate-500">{{ $s->subdomain }}@unless ($s->isAktif()) · <span class="text-red-600">nonaktif</span>@endunless</span></td>
                        <td class="text-right">{{ number_format($b['siswa'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($b['guru'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($b['pengguna'], 0, ',', '.') }}</td>
                        <td class="text-right whitespace-nowrap">{{ \App\Support\Pemantauan::ukuranTeks($b['ukuran']) }}</td>
                        <td class="whitespace-nowrap">{{ $b['login_terakhir']?->diffForHumans() ?? 'Belum pernah' }}</td>
                        <td class="text-right">{{ number_format($b['api_30_hari'], 0, ',', '.') }}@if ($b['api_hari_ini'])<br><span class="text-xs text-slate-500">hari ini {{ number_format($b['api_hari_ini'], 0, ',', '.') }}</span>@endif</td>
                        <td><span class="badge badge-{{ $warnaSewa[$b['status_sewa']] }}">{{ $labelSewa[$b['status_sewa']] }}</span>@if ($b['paket'])<br><span class="text-xs text-slate-500">{{ $b['paket'] }}@if ($b['berakhir']) s.d. {{ $b['berakhir']->translatedFormat('d M Y') }}@endif</span>@endif</td>
                        <td class="whitespace-nowrap">{{ $b['backup_terakhir']?->diffForHumans() ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-500">Tidak ada sekolah.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
