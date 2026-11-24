@extends('layouts.admin')

@section('title', 'Agenda')

@section('content')
    <x-admin.crud title="Agenda" subtitle="Jadwal kegiatan sekolah." name="Agenda" size="lg"
        :url="route('admin/agenda.data')" :store="route('admin/agenda.store')"
        :update="url('admin/agenda/:id')" :destroy="url('admin/agenda/:id')">
        <x-slot:columns>
            <th data-col="no" class="w-14">No</th>
            <th data-col="agenda_nama">Agenda</th>
            <th data-col="tanggal" class="no-sort">Tanggal</th>
            <th data-col="agenda_waktu">Waktu</th>
            <th data-col="agenda_tempat">Tempat</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="agenda_nama" label="Nama Agenda" class="sm:col-span-2" maxlength="200" required />
            <x-admin.field name="agenda_mulai" label="Tanggal Mulai" type="date" required />
            <x-admin.field name="agenda_selesai" label="Tanggal Selesai" type="date" required />
            <x-admin.field name="agenda_waktu" label="Waktu" maxlength="30" placeholder="mis. 08.00 - 12.00" required />
            <x-admin.field name="agenda_tempat" label="Tempat" maxlength="100" required />
            <x-admin.field name="agenda_deskripsi" label="Deskripsi" as="textarea" class="sm:col-span-2" rows="3" required />
            <x-admin.field name="agenda_keterangan" label="Keterangan" class="sm:col-span-2" maxlength="200" optional />
        </x-slot:fields>
    </x-admin.crud>
@endsection
