@extends('layouts.admin')

@section('title', 'Langganan')

@section('content')
    <x-admin.crud title="Langganan" subtitle="Masa sewa semua sekolah. Koreksi tanggal atau paket bila perlu (tercatat di log)." name="Langganan"
        :url="route('platform/langganan.data')" :update="url('platform/langganan/:id')">
        <x-slot:actions>
            <select data-crud-filter="status" class="select !w-auto" aria-label="Filter status">
                <option value="">Semua</option>
                <option value="aktif">Berjalan</option>
                <option value="berakhir">Berakhir</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="sekolah" class="no-sort">Sekolah</th>
            <th data-col="paket" class="no-sort">Paket</th>
            <th data-col="durasi_bulan" class="no-sort">Durasi</th>
            <th data-col="periode" class="no-sort">Periode</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="paket_id" id="lg_paket" label="Paket" as="select" required>
                @foreach ($paket as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="status_raw" id="lg_status" label="Status" as="select" required>
                <option value="aktif">Aktif</option><option value="dibatalkan">Dibatalkan</option>
            </x-admin.field>
            <x-admin.field name="mulai" id="lg_mulai" label="Mulai" type="date" required />
            <x-admin.field name="berakhir" id="lg_berakhir" label="Berakhir" type="date" required />
            <x-admin.field name="catatan" id="lg_cat" label="Catatan" class="sm:col-span-2" maxlength="300" optional />
        </x-slot:fields>
    </x-admin.crud>
@endsection
