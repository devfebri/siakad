@extends('layouts.admin')

@section('title', 'Paket & Modul')

@section('content')
    <x-admin.page-header title="Paket & Modul" subtitle="Atur harga per durasi dan modul yang termasuk di setiap paket. Fitur inti selalu aktif di semua paket." />

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach ($paket as $p)
            <form data-ajax-form data-method="PUT" novalidate action="{{ route('platform/paket.update', $p) }}" class="card">
                <div class="card-header">{{ $p->nama }} <span class="badge badge-muted">{{ $p->kode }}</span></div>
                <div class="space-y-3 p-5">
                    <div><label class="label" for="pn{{ $p->id }}">Nama paket</label><input id="pn{{ $p->id }}" name="nama" value="{{ $p->nama }}" class="input" maxlength="80" required></div>
                    <div><label class="label" for="pd{{ $p->id }}">Deskripsi</label><textarea id="pd{{ $p->id }}" name="deskripsi" rows="2" class="textarea" maxlength="300">{{ $p->deskripsi }}</textarea></div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($durasi as $d)
                            <div><label class="label" for="ph{{ $p->id }}_{{ $d }}">{{ $d }} bln (Rp)</label><input id="ph{{ $p->id }}_{{ $d }}" name="harga[{{ $d }}]" type="number" min="0" value="{{ $p->hargaUntuk($d) ?? 0 }}" class="input" required></div>
                        @endforeach
                    </div>
                    <fieldset>
                        <legend class="label">Modul</legend>
                        <div class="space-y-1.5">
                            @foreach ($modul as $m)
                                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="modul[]" value="{{ $m->kode }}" class="check" @checked(in_array($m->kode, $p->kodeModul(), true))> {{ $m->nama }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="aktif" value="1" class="check" @checked($p->aktif)> Paket ditawarkan</label>
                </div>
                <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan</button></div>
            </form>
        @endforeach

        <form data-ajax-form data-reset novalidate action="{{ route('platform/paket.store') }}" class="card border-dashed">
            <div class="card-header">Paket Baru</div>
            <div class="space-y-3 p-5">
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="pk_kode">Kode</label><input id="pk_kode" name="kode" class="input" maxlength="40" required placeholder="mis. enterprise"></div>
                    <div><label class="label" for="pk_nama">Nama</label><input id="pk_nama" name="nama" class="input" maxlength="80" required></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($durasi as $d)
                        <div><label class="label" for="pkh_{{ $d }}">{{ $d }} bln (Rp)</label><input id="pkh_{{ $d }}" name="harga[{{ $d }}]" type="number" min="0" value="0" class="input" required></div>
                    @endforeach
                </div>
                <fieldset>
                    <legend class="label">Modul</legend>
                    <div class="space-y-1.5">@foreach ($modul as $m)<label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="modul[]" value="{{ $m->kode }}" class="check"> {{ $m->nama }}</label>@endforeach</div>
                </fieldset>
                <input type="hidden" name="aktif" value="1">
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-outline btn-sm"><i class="bi bi-plus-lg"></i> Tambah Paket</button></div>
        </form>
    </div>
@endsection
