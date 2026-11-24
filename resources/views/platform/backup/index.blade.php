@extends('layouts.admin')

@section('title', 'Backup Sekolah')

@php $ukuran = fn ($n) => \App\Support\Pemantauan::ukuranTeks((int) $n); @endphp

@section('content')
    <x-admin.page-header title="Backup Sekolah" subtitle="Arsip zip berisi seluruh data dan berkas satu sekolah. Backup terjadwal berjalan tiap minggu; sekolah yang nonaktif 90 hari diarsipkan otomatis." />

    <div class="card mb-6 p-5">
        <h2 class="font-display text-lg font-bold">Buat backup sekarang</h2>
        <form method="POST" action="{{ route('platform/backup.buat') }}" class="mt-3 flex flex-wrap items-start gap-3">@csrf
            <div>
                <select name="sekolah_id" class="select" aria-label="Pilih sekolah" required>
                    <option value="">Pilih sekolah...</option>
                    @foreach ($sekolah as $s)<option value="{{ $s->id }}" @selected($sekolahId === $s->id)>{{ $s->nama }}{{ $s->isAktif() ? '' : ' (nonaktif)' }}</option>@endforeach
                </select>
                @error('sekolah_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary"><i class="bi bi-archive"></i> Buat Backup</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">Dikerjakan di antrian, jadi butuh <code>queue:work</code> atau <code>schedule:run</code> berjalan. Password, kunci API, dan token tidak disertakan dalam backup.</p>
    </div>

    <form method="GET" class="mb-3 flex flex-wrap items-center gap-2">
        <select name="sekolah_id" class="select !w-auto" aria-label="Saring sekolah" onchange="this.form.submit()">
            <option value="">Semua sekolah</option>
            @foreach ($sekolah as $s)<option value="{{ $s->id }}" @selected($sekolahId === $s->id)>{{ $s->nama }}</option>@endforeach
        </select>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-clean">
            <thead><tr><th>Waktu</th><th>Sekolah</th><th>Jenis</th><th>Status</th><th class="text-right">Ukuran</th><th class="text-right">Isi</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($backup as $b)
                    <tr>
                        <td class="whitespace-nowrap">{{ $b->created_at->translatedFormat('d M Y H:i') }}<br><span class="text-xs text-slate-500">{{ $b->pembuat?->name ?? 'Otomatis' }}</span></td>
                        <td>{{ $b->sekolah?->nama ?? '-' }}</td>
                        <td>{{ \App\BackupSekolah::JENIS[$b->jenis] ?? $b->jenis }}</td>
                        <td><span class="badge badge-{{ \App\BackupSekolah::WARNA[$b->status] }}">{{ \App\BackupSekolah::STATUS[$b->status] }}</span>@if ($b->galat)<br><span class="text-xs text-red-600">{{ $b->galat }}</span>@endif</td>
                        <td class="text-right whitespace-nowrap">{{ $b->status === 'selesai' ? $ukuran($b->ukuran) : '-' }}</td>
                        <td class="text-right text-xs text-slate-500">{{ $b->status === 'selesai' ? number_format($b->jumlah_baris, 0, ',', '.').' baris · '.$b->jumlah_berkas.' berkas' : '' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($b->adaBerkas())<a href="{{ route('platform/backup.unduh', $b) }}" class="btn btn-soft btn-sm"><i class="bi bi-download"></i> Unduh</a>@endif
                            <form method="POST" action="{{ route('platform/backup.hapus', $b) }}" class="inline" onsubmit="return confirm('Hapus backup ini? Berkasnya hilang permanen.')">@csrf @method('DELETE')<button class="btn btn-soft btn-sm" title="Hapus"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada backup.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
