@extends('layouts.admin')

@section('title', 'Tahun Ajaran')

@section('content')
    <x-admin.page-header title="Tahun Ajaran & Semester" subtitle="Atur periode akademik, semester aktif, KKM, dan bobot nilai. Jadwal, absensi, dan nilai mengikuti semester aktif." />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @foreach ($daftar as $ta)
                <div class="card">
                    <div class="card-header">
                        <span>Tahun Ajaran {{ $ta->nama }} <span class="badge badge-{{ ['aktif' => 'success', 'draf' => 'warning', 'ditutup' => 'muted'][$ta->status] ?? 'muted' }} ml-2">{{ \App\TahunAjaran::STATUS[$ta->status] ?? $ta->status }}</span>@if ($ta->status === 'draf' && $ta->dari_tahun_id) <a href="{{ route('admin/proses-tahunan') }}" class="ml-2 text-xs">Proses tahunan</a>@endif</span>
                        <form data-ajax-form data-method="DELETE" action="{{ route('admin/tahun-ajaran.destroy', $ta) }}">
                            <button type="submit" class="btn btn-soft btn-sm" title="Hapus tahun ajaran"><i class="bi bi-trash"></i> Hapus</button>
                        </form>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($ta->semester as $s)
                            <div class="p-5">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <strong>Semester {{ $s->jenis }}</strong>
                                    @if ($s->aktif)
                                        <span class="badge badge-success">Aktif</span>
                                    @else
                                        <form data-ajax-form action="{{ route('admin/semester.aktifkan', $s) }}"><button type="submit" class="btn btn-outline btn-sm"><i class="bi bi-check2-circle"></i> Jadikan aktif</button></form>
                                    @endif
                                </div>
                                <form data-ajax-form data-method="PUT" novalidate action="{{ route('admin/semester.update', $s) }}" class="grid gap-3 sm:grid-cols-4">
                                    <div><label class="label" for="sm{{ $s->id }}m">Mulai</label><input id="sm{{ $s->id }}m" type="date" name="mulai" value="{{ $s->mulai->toDateString() }}" class="input" required></div>
                                    <div><label class="label" for="sm{{ $s->id }}s">Selesai</label><input id="sm{{ $s->id }}s" type="date" name="selesai" value="{{ $s->selesai->toDateString() }}" class="input" required></div>
                                    <div><label class="label" for="sm{{ $s->id }}k">KKM</label><input id="sm{{ $s->id }}k" type="number" name="kkm" min="0" max="100" value="{{ $s->kkm }}" class="input" required></div>
                                    <div class="flex items-end"><button type="submit" class="btn btn-primary btn-sm btn-block"><i class="bi bi-check2"></i> Simpan</button></div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-6">
            <form data-ajax-form data-reset novalidate action="{{ route('admin/tahun-ajaran.store') }}" class="card border-dashed">
                <div class="card-header">Tahun Ajaran Baru</div>
                <div class="space-y-3 p-5">
                    <div>
                        <label class="label" for="ta_nama">Tahun ajaran</label>
                        <input id="ta_nama" name="nama" class="input" placeholder="mis. {{ now()->year + 1 }}/{{ now()->year + 2 }}" maxlength="9" required>
                        <p class="mt-1 text-xs text-slate-500">Semester Ganjil (Jul–Des) dan Genap (Jan–Jun) dibuat otomatis; tanggal dan KKM bisa diubah.</p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</button></div>
            </form>

            <form data-ajax-form data-method="PUT" novalidate action="{{ route('admin/tahun-ajaran.bobot') }}" class="card">
                <div class="card-header">Bobot Nilai Akhir</div>
                <div class="space-y-3 p-5">
                    @foreach ($bobot as $kode => $b)
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm" for="bw_{{ $kode }}">{{ $b['label'] }}</label>
                            <div class="flex w-28 items-center gap-1"><input id="bw_{{ $kode }}" type="number" name="bobot[{{ $kode }}]" min="0" max="100" value="{{ $b['bobot'] }}" class="input" required><span class="text-sm text-slate-500">%</span></div>
                        </div>
                    @endforeach
                    <p class="text-xs text-slate-500">Total harus 100%. Jenis yang belum terisi dibagi ulang saat menghitung nilai akhir.</p>
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan bobot</button></div>
            </form>
        </div>
    </div>
@endsection
