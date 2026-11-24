@extends('layouts.admin')

@section('title', $p->no_pendaftaran)

@section('content')
    <x-admin.page-header :title="$p->nama" :subtitle="$p->no_pendaftaran.' · Tahun ajaran '.$p->tahun_ajaran" :back="route('admin/ppdb')">
        <span class="badge badge-{{ \App\PpdbPendaftar::STATUS[$p->status]['badge'] ?? 'muted' }} text-sm" id="badge-status">{{ $p->status_label }}</span>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header">Data Calon Siswa</div>
            <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                @foreach ([
                    'Nama Lengkap' => $p->nama,
                    'Jenis Kelamin' => $p->jenis_kelamin,
                    'Agama' => $p->agama ?: '-',
                    'Tempat, Tanggal Lahir' => $p->tempat_lahir.', '.$p->tanggal_lahir->translatedFormat('d F Y'),
                    'NISN' => $p->nisn ?: '-',
                    'Asal Sekolah' => $p->asal_sekolah,
                    'Jalur' => $p->jalur,
                    'Peminatan' => $p->peminatan ?: '-',
                    'Nilai Rata-rata' => $p->nilai_rata !== null ? $p->nilai_rata : '-',
                    'Nama Orang Tua/Wali' => $p->nama_ortu,
                    'Telepon/WhatsApp' => $p->telepon,
                    'Email' => $p->email ?: '-',
                    'Mendaftar' => $p->created_at?->translatedFormat('d F Y, H:i'),
                ] as $label => $value)
                    <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                @endforeach
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Alamat</dt><dd class="font-semibold">{{ $p->alamat }}</dd></div>
                @if ($p->berkas)
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Berkas pendukung</dt>
                        <dd><a href="{{ asset($p->berkas) }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm mt-1"><i class="bi bi-paperclip"></i> Buka berkas</a></dd></div>
                @endif
            </dl>
        </div>

        <form data-ajax-form data-method="PUT" novalidate action="{{ route('admin/ppdb.status', $p) }}" class="card h-fit">
            @csrf
            <div class="card-header">Verifikasi</div>
            <div class="space-y-4 p-5">
                <x-admin.field name="status" label="Status" as="select" required>
                    @foreach (\App\PpdbPendaftar::STATUS as $key => $s)<option value="{{ $key }}" @selected($p->status === $key)>{{ $s['label'] }}</option>@endforeach
                </x-admin.field>
                <x-admin.field name="catatan" label="Catatan untuk pendaftar" as="textarea" rows="4" maxlength="1000" optional hint="Tampil saat pendaftar mengecek statusnya.">{{ $p->catatan }}</x-admin.field>
                <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-check2-circle"></i> Simpan Status</button>
                @if ($p->siswa_id)
                    <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900"><i class="bi bi-person-check-fill"></i> Sudah dijadikan siswa @if ($p->dijadikan_siswa_pada)pada {{ $p->dijadikan_siswa_pada->translatedFormat('d M Y') }}@endif.</div>
                    <a href="{{ route('admin/siswa', ['cari' => $p->siswa?->nisn]) }}" class="btn btn-soft btn-block"><i class="bi bi-mortarboard"></i> Lihat di Data Siswa</a>
                @elseif ($p->status === 'diterima')
                    <a href="{{ route('admin/ppdb.jadikan', $p) }}" class="btn btn-accent btn-block"><i class="bi bi-person-plus-fill"></i> Jadikan Siswa</a>
                @endif
                <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/\D/', '', $p->telepon)) }}" target="_blank" rel="noopener" class="btn btn-outline btn-block"><i class="bi bi-whatsapp"></i> Hubungi via WhatsApp</a>
            </div>
        </form>
    </div>
@endsection
