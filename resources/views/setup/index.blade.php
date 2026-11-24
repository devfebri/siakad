@extends('layouts.admin')

@section('title', 'Pengaturan Awal')

@php
    $nomor = 0;
    $berikut = collect($langkah)->search(fn ($l) => ! $l['selesai']);
@endphp

@section('content')
    <x-admin.page-header title="Pengaturan Awal" subtitle="Lima langkah agar sekolah Anda siap dipakai. Urutan boleh dilewati, dan setiap langkah bisa diulang kapan saja." />

    <div class="mb-6 card p-5">
        <ol class="grid gap-3 sm:grid-cols-5">
            @foreach ($langkah as $kunci => $l)
                @php $nomor++; @endphp
                <li>
                    <a href="#langkah-{{ $kunci }}" class="flex items-center gap-3 rounded-xl border p-3 no-underline {{ $l['selesai'] ? 'border-emerald-200 bg-emerald-50' : ($kunci === $berikut ? 'border-primary bg-primary-soft' : 'border-slate-100') }}">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $l['selesai'] ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }}">
                            @if ($l['selesai'])<i class="bi bi-check-lg"></i>@else{{ $nomor }}@endif
                        </span>
                        <span class="text-sm font-semibold text-ink">{{ $l['judul'] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="space-y-6">
        {{-- 1. Identitas --}}
        <form id="langkah-identitas" data-ajax-form data-method="PUT" novalidate action="{{ route('admin/setup.identitas') }}" class="card">
            <div class="card-header">1. Identitas sekolah @if ($langkah['identitas']['selesai'])<span class="badge badge-success">Selesai</span>@endif</div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <div><label class="label" for="s_nama">Nama sekolah</label><input id="s_nama" name="nama_sekolah" value="{{ \App\Setting::get('nama_sekolah') }}" class="input" maxlength="150" required></div>
                <div><label class="label" for="s_singkat">Nama singkat</label><input id="s_singkat" name="nama_singkat" value="{{ \App\Setting::get('nama_singkat') }}" class="input" maxlength="40" required></div>
                <div><label class="label" for="s_npsn">NPSN <span class="font-normal text-slate-400">(opsional)</span></label><input id="s_npsn" name="npsn" value="{{ \App\Setting::get('npsn') }}" class="input" maxlength="20"></div>
                <div><label class="label" for="s_telp">Telepon <span class="font-normal text-slate-400">(opsional)</span></label><input id="s_telp" name="telepon" value="{{ \App\Setting::get('telepon') }}" class="input" maxlength="40"></div>
                <div class="sm:col-span-2"><label class="label" for="s_alamat">Alamat <span class="font-normal text-slate-400">(opsional)</span></label><input id="s_alamat" name="alamat" value="{{ \App\Setting::get('alamat') }}" class="input" maxlength="500"></div>
                <p class="text-xs text-slate-500 sm:col-span-2">Logo, warna, dan konten beranda diatur di menu <a href="{{ route('admin/pengaturan') }}">Pengaturan</a>.</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan</button></div>
        </form>

        {{-- 2. Tahun ajaran --}}
        <form id="langkah-akademik" data-ajax-form novalidate action="{{ route('admin/setup.akademik') }}" data-method="PUT" class="card">
            <div class="card-header">2. Tahun ajaran & semester @if ($langkah['akademik']['selesai'])<span class="badge badge-success">{{ $akademik['label'] }}</span>@endif</div>
            <div class="grid gap-3 p-5 sm:grid-cols-3">
                <div><label class="label" for="a_ta">Tahun ajaran</label><input id="a_ta" name="tahun_ajaran" value="{{ $akademik['tahun_ajaran'] }}" class="input" maxlength="9" placeholder="2026/2027" required></div>
                <div><label class="label" for="a_sm">Semester aktif</label><select id="a_sm" name="semester" class="select">@foreach (\App\Semester::JENIS as $j)<option @selected($akademik['semester'] === $j)>{{ $j }}</option>@endforeach</select></div>
                <div><label class="label" for="a_kkm">KKM</label><input id="a_kkm" type="number" name="kkm" min="0" max="100" value="{{ $akademik['kkm'] }}" class="input" required></div>
                <p class="text-xs text-slate-500 sm:col-span-3">Tanggal semester dan bobot nilai bisa diubah di menu <a href="{{ route('admin/tahun-ajaran') }}">Tahun Ajaran</a>.</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan & aktifkan</button></div>
        </form>

        {{-- 3. Mapel dan rombel --}}
        <form id="langkah-struktur" data-ajax-form data-reset novalidate action="{{ route('admin/setup.struktur') }}" class="card">
            <div class="card-header">3. Mata pelajaran & rombel @if ($langkah['struktur']['selesai'])<span class="badge badge-success">{{ $jumlah['mapel'] }} mapel · {{ $jumlah['rombel'] }} rombel</span>@endif</div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <div><label class="label" for="st_mapel">Mata pelajaran <span class="font-normal text-slate-400">(satu per baris)</span></label><textarea id="st_mapel" name="mapel" rows="6" class="textarea" placeholder="Matematika&#10;Bahasa Indonesia&#10;IPA"></textarea></div>
                <div><label class="label" for="st_rombel">Rombel tahun ajaran aktif <span class="font-normal text-slate-400">(satu per baris)</span></label><textarea id="st_rombel" name="rombel" rows="6" class="textarea" placeholder="VII A&#10;VII B&#10;VIII A"></textarea></div>
                <p class="text-xs text-slate-500 sm:col-span-2">Nama yang sudah ada dilewati. Wali kelas dan anggota rombel diatur di menu Kelas &amp; Jadwal.</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambahkan</button></div>
        </form>

        {{-- 4 & 5. Impor --}}
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (['guru' => ['4', 'Data guru', 'person-workspace'], 'siswa' => ['5', 'Data siswa', 'mortarboard']] as $kunci => [$no, $judul, $ikon])
                <div id="langkah-{{ $kunci }}" class="card">
                    <div class="card-header">{{ $no }}. {{ $judul }} @if ($langkah[$kunci]['selesai'])<span class="badge badge-success">{{ $jumlah[$kunci] }} data</span>@endif</div>
                    <div class="space-y-3 p-5 text-sm text-slate-600">
                        <p>Impor dari Excel memakai template, atau tambah satu per satu di menu <a href="{{ route('admin/'.$kunci) }}">Data {{ ucfirst($kunci) }}</a>.</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin/impor.template', $kunci) }}" class="btn btn-outline btn-sm"><i class="bi bi-download"></i> Unduh template</a>
                            <a href="{{ route('admin/impor') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Impor {{ $kunci }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card flex flex-wrap items-center justify-between gap-3 p-5">
            <p class="text-sm text-slate-600">{{ $selesai ? 'Pengaturan awal sudah ditandai selesai.' : ($tuntas ? 'Semua langkah sudah terisi. Tandai selesai agar pengingat di dashboard hilang.' : 'Anda bisa menandai selesai kapan saja, walau ada langkah yang dilewati.') }}</p>
            @unless ($selesai)
                <form data-ajax-form action="{{ route('admin/setup.selesai') }}"><button type="submit" class="btn btn-accent"><i class="bi bi-flag"></i> Tandai selesai</button></form>
            @endunless
        </div>
    </div>
@endsection
