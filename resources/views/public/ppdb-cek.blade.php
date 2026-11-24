@extends('layouts.public')

@section('title', 'Cek Status PPDB')

@section('content')
    <x-public.page-title title="Cek Status Pendaftaran" subtitle="Masukkan nomor pendaftaran dan tanggal lahir calon siswa." crumb="Cek Status" />

    <div class="mx-auto max-w-xl px-4 py-14 sm:px-6">
        <form method="POST" action="{{ route('ppdb.cek') }}" class="card space-y-4 p-6" novalidate>
            @csrf
            <div>
                <label for="no_pendaftaran" class="label">Nomor pendaftaran</label>
                <input id="no_pendaftaran" name="no_pendaftaran" value="{{ old('no_pendaftaran') }}" placeholder="PPDB-2027-0001" required class="input uppercase @error('no_pendaftaran') is-invalid @enderror">
                @error('no_pendaftaran')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tanggal_lahir" class="label">Tanggal lahir</label>
                <input id="tanggal_lahir" type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required class="input @error('tanggal_lahir') is-invalid @enderror">
                @error('tanggal_lahir')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-search"></i> Cek Status</button>
        </form>

        @if ($dicari && $hasil)
            @php $badge = \App\PpdbPendaftar::STATUS[$hasil->status]['badge'] ?? 'muted'; @endphp
            <div class="card mt-6 overflow-hidden" role="status">
                <div class="flex items-center justify-between gap-3 bg-primary px-6 py-4 text-white">
                    <div><p class="text-xs text-white/60">{{ $hasil->no_pendaftaran }}</p><p class="font-display text-lg font-bold">{{ $hasil->nama }}</p></div>
                    <span class="badge badge-{{ $badge }} text-sm">{{ $hasil->status_label }}</span>
                </div>
                <dl class="grid gap-4 p-6 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Asal sekolah</dt><dd class="font-semibold">{{ $hasil->asal_sekolah }}</dd></div>
                    <div><dt class="text-slate-500">Jalur</dt><dd class="font-semibold">{{ $hasil->jalur }}</dd></div>
                    <div><dt class="text-slate-500">Mendaftar</dt><dd class="font-semibold">{{ $hasil->created_at?->translatedFormat('d F Y') }}</dd></div>
                    <div><dt class="text-slate-500">Tahun ajaran</dt><dd class="font-semibold">{{ $hasil->tahun_ajaran }}</dd></div>
                    @if ($hasil->catatan)<div class="sm:col-span-2"><dt class="text-slate-500">Catatan panitia</dt><dd class="font-semibold">{{ $hasil->catatan }}</dd></div>@endif
                </dl>
            </div>
        @elseif ($dicari)
            <div class="card mt-6 border-red-200 bg-red-50 p-6 text-center text-sm text-red-700" role="alert">Data tidak ditemukan. Periksa kembali nomor pendaftaran dan tanggal lahir.</div>
        @endif
    </div>
@endsection
