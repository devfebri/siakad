@extends('layouts.admin')

@section('title', 'Backup Data')

@php $ukuran = fn ($n) => \App\Support\Pemantauan::ukuranTeks((int) $n); @endphp

@section('content')
    <x-admin.page-header title="Backup Data" subtitle="Simpan salinan seluruh data dan berkas sekolah Anda ke komputer, untuk berjaga-jaga." />

    <div class="card mb-6 p-6">
        <h2 class="font-display text-lg font-bold">Buat backup sekarang</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">Hasilnya berupa satu berkas zip yang berisi data siswa, guru, nilai, keuangan, berita, dan semua foto atau dokumen yang pernah diunggah. Proses bisa memakan beberapa menit; muat ulang halaman ini lalu tekan Unduh bila sudah selesai.</p>
        <form method="POST" action="{{ route('admin/backup.buat') }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf
            <button class="btn btn-primary btn-lg" @disabled($berjalan || $sisaHariIni < 1)><i class="bi bi-archive"></i> Buat Backup</button>
            <span class="text-sm text-slate-500">
                @if ($berjalan)<i class="bi bi-hourglass-split"></i> Backup sedang dibuat...
                @else Sisa kesempatan hari ini: {{ $sisaHariIni }} dari {{ \App\Http\Controllers\BackupSayaController::BATAS_HARIAN }}@endif
            </span>
        </form>
        <p class="mt-4 rounded-xl bg-accent-soft p-3 text-sm"><i class="bi bi-shield-lock"></i> Berkas ini berisi <strong>data pribadi</strong> siswa dan guru. Simpan di tempat aman dan jangan dibagikan. Kata sandi akun dan kunci pembayaran/WhatsApp tidak ikut dimasukkan.</p>
    </div>

    <div class="card overflow-x-auto">
        <div class="card-header">Daftar backup</div>
        <table class="table-clean">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Status</th><th class="text-right">Ukuran</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($backup as $b)
                    <tr>
                        <td class="whitespace-nowrap">{{ $b->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ \App\BackupSekolah::JENIS[$b->jenis] ?? $b->jenis }}</td>
                        <td><span class="badge badge-{{ \App\BackupSekolah::WARNA[$b->status] }}">{{ \App\BackupSekolah::STATUS[$b->status] }}</span>@if ($b->galat)<br><span class="text-xs text-red-600">Gagal dibuat. Hubungi pengelola.</span>@endif</td>
                        <td class="text-right whitespace-nowrap">{{ $b->status === 'selesai' ? $ukuran($b->ukuran) : '-' }}</td>
                        <td class="text-right">@if ($b->adaBerkas())<a href="{{ route('admin/backup.unduh', $b) }}" class="btn btn-soft btn-sm"><i class="bi bi-download"></i> Unduh</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada backup.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
