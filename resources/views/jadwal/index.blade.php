@extends('layouts.admin')

@section('title', 'Kelas & Jadwal')

@section('content')
    @include('kelas._tab')

    <x-admin.crud title="Jadwal Pelajaran" name="Jadwal" size="lg" subtitle="Jadwal tahun ajaran {{ $akademik['label'] }}"
        :url="route('admin/jadwal.data')" :store="route('admin/jadwal.store')" :update="url('admin/jadwal/:id')" :destroy="url('admin/jadwal/:id')">
        <x-slot:actions>
            <select id="filter-kelas" data-crud-filter="kelas_id" class="select !w-auto" aria-label="Pilih kelas">
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nama_hari">Hari</th>
            <th data-col="waktu" class="no-sort">Waktu</th>
            <th data-col="kelas" class="no-sort">Kelas</th>
            <th data-col="mapel" class="no-sort">Mata Pelajaran</th>
            <th data-col="guru" class="no-sort">Guru</th>
            <th data-col="ruang" class="no-sort">Ruang</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="kelas_id" id="jd_kelas" label="Kelas" as="select" required>
                <option value="">- pilih -</option>@foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="hari" id="jd_hari" label="Hari" as="select" required>
                <option value="">- pilih -</option>@foreach ($hari as $no => $nama)<option value="{{ $no }}">{{ $nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="mapel_id" id="jd_mapel" label="Mata Pelajaran" as="select" required>
                <option value="">- pilih -</option>@foreach ($mapel as $m)<option value="{{ $m->id }}">{{ $m->mapel_nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="guru_id" id="jd_guru" label="Guru Pengajar" as="select" optional>
                <option value="">- belum ditentukan -</option>@foreach ($guru as $g)<option value="{{ $g->id }}">{{ $g->nama_guru }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="jam_mulai" id="jd_mulai" label="Jam Mulai" type="time" required />
            <x-admin.field name="jam_selesai" id="jd_selesai" label="Jam Selesai" type="time" required />
            <x-admin.field name="ruang" id="jd_ruang" label="Ruang" class="sm:col-span-2" maxlength="40" optional />
        </x-slot:fields>
    </x-admin.crud>

    <section class="mt-8" aria-labelledby="judul-grid">
        <h2 id="judul-grid" class="mb-3 font-display text-lg font-bold">Tampilan Mingguan <span class="text-sm font-normal text-slate-500">(sesuai kelas yang dipilih di filter)</span></h2>
        <x-admin.jadwal-grid :url="route('admin/jadwal.grid')" kelas-selector="#filter-kelas" :tampilkan-kelas="true" />
    </section>
@endsection
