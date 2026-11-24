@extends('layouts.public')

@section('title', 'PPDB '.$status['tahun_ajaran'])
@section('description', 'Penerimaan peserta didik baru '.config('school.name').' tahun ajaran '.$status['tahun_ajaran'])

@section('content')
    <x-public.page-title :title="'PPDB '.$status['tahun_ajaran']" subtitle="Penerimaan Peserta Didik Baru {{ config('school.name') }}." crumb="PPDB" />

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-3 lg:px-8">
        <aside class="space-y-6 lg:order-2">
            <div @class(['card p-6', 'border-emerald-200 bg-emerald-50/60' => $status['state'] === 'buka'])>
                <p class="mb-1 text-xs font-bold tracking-widest text-slate-500 uppercase">Status</p>
                <p @class(['font-display text-2xl font-extrabold', 'text-emerald-600' => $status['state'] === 'buka', 'text-amber-600' => in_array($status['state'], ['belum', 'nonaktif']), 'text-red-600' => $status['state'] === 'tutup'])>{{ $status['label'] }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    @if ($status['buka'])<div class="flex justify-between gap-3"><dt class="text-slate-500">Dibuka</dt><dd class="font-semibold">{{ $status['buka']->translatedFormat('d M Y, H:i') }}</dd></div>@endif
                    @if ($status['tutup'])<div class="flex justify-between gap-3"><dt class="text-slate-500">Ditutup</dt><dd class="font-semibold">{{ $status['tutup']->translatedFormat('d M Y, H:i') }}</dd></div>@endif
                    @if (config('school.ppdb_kuota'))<div class="flex justify-between gap-3"><dt class="text-slate-500">Kuota</dt><dd class="font-semibold">{{ config('school.ppdb_kuota') }} siswa</dd></div>@endif
                </dl>
                @if ($status['state'] === 'buka' && $status['tutup'] && $status['tutup']->isFuture())
                    <div data-countdown="{{ $status['tutup']->format('Y-m-d\TH:i:s') }}" class="mt-5 grid grid-cols-4 gap-2 text-center" role="timer">
                        @foreach (['days' => 'Hari', 'hours' => 'Jam', 'minutes' => 'Mnt', 'seconds' => 'Dtk'] as $unit => $label)
                            <div class="rounded-xl bg-primary px-1 py-2 text-white"><span data-unit="{{ $unit }}" class="block font-display text-xl font-extrabold text-accent">00</span><span class="text-[0.65rem] text-white/70">{{ $label }}</span></div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($persyaratan)
                <div class="card p-6">
                    <h2 class="mb-3 font-display font-bold"><i class="bi bi-card-checklist text-accent-dark"></i> Persyaratan</h2>
                    <ul class="space-y-2 text-sm text-slate-600">
                        @foreach ($persyaratan as $p)<li class="flex gap-2"><i class="bi bi-check-circle-fill mt-0.5 text-emerald-500"></i> {{ $p }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <a href="{{ route('ppdb.cek') }}" class="card card-lift flex items-center gap-4 p-5 no-underline">
                <span class="grid size-12 place-items-center rounded-2xl bg-accent-soft text-2xl text-accent-dark"><i class="bi bi-search"></i></span>
                <span><span class="block font-display font-bold text-ink">Sudah mendaftar?</span><span class="text-sm text-slate-500">Cek status pendaftaran Anda</span></span>
            </a>
        </aside>

        <div class="lg:col-span-2 lg:order-1">
            @if (! $status['bisa_daftar'])
                <div class="card p-10 text-center">
                    <span class="mx-auto mb-4 grid size-16 place-items-center rounded-2xl bg-slate-100 text-3xl text-slate-400"><i class="bi bi-lock"></i></span>
                    <h2 class="font-display text-2xl font-extrabold">{{ $status['label'] }}</h2>
                    <p class="mt-2 text-slate-600">Formulir pendaftaran hanya tersedia saat PPDB dibuka. Pantau halaman ini atau hubungi sekolah untuk informasi lebih lanjut.</p>
                    <a href="{{ route('kontak') }}" class="btn btn-primary mt-6">Hubungi Kami</a>
                </div>
            @else
                <form method="POST" action="{{ route('ppdb.store') }}" enctype="multipart/form-data" class="card" novalidate>
                    @csrf
                    <div class="card-header"><span><i class="bi bi-pencil-square text-accent-dark"></i> Formulir Pendaftaran</span></div>

                    @if ($errors->any())
                        <div class="mx-5 mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">Mohon periksa kembali isian yang ditandai merah.</div>
                    @endif

                    <div class="space-y-8 p-5 sm:p-6">
                        @php
                            $f = fn ($name) => old($name);
                            $err = fn ($name) => $errors->first($name);
                        @endphp
                        <fieldset class="grid gap-4 sm:grid-cols-2">
                            <legend class="mb-3 font-display font-bold text-primary">1. Data Calon Siswa</legend>
                            <div class="sm:col-span-2"><label for="nama" class="label">Nama lengkap</label><input id="nama" name="nama" value="{{ $f('nama') }}" maxlength="120" required class="input @error('nama') is-invalid @enderror">@error('nama')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="jenis_kelamin" class="label">Jenis kelamin</label>
                                <select id="jenis_kelamin" name="jenis_kelamin" required class="select @error('jenis_kelamin') is-invalid @enderror"><option value="">- pilih -</option>@foreach (['Laki-Laki', 'Perempuan'] as $jk)<option value="{{ $jk }}" @selected($f('jenis_kelamin') === $jk)>{{ $jk }}</option>@endforeach</select>@error('jenis_kelamin')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="agama" class="label">Agama</label>
                                <select id="agama" name="agama" required class="select @error('agama') is-invalid @enderror"><option value="">- pilih -</option>@foreach (\App\PpdbPendaftar::AGAMA as $a)<option value="{{ $a }}" @selected($f('agama') === $a)>{{ $a }}</option>@endforeach</select>
                                @error('agama')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="nisn" class="label">NISN <span class="font-normal text-slate-400">(opsional)</span></label><input id="nisn" name="nisn" value="{{ $f('nisn') }}" inputmode="numeric" maxlength="20" class="input @error('nisn') is-invalid @enderror">@error('nisn')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="tempat_lahir" class="label">Tempat lahir</label><input id="tempat_lahir" name="tempat_lahir" value="{{ $f('tempat_lahir') }}" maxlength="80" required class="input @error('tempat_lahir') is-invalid @enderror">@error('tempat_lahir')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="tanggal_lahir" class="label">Tanggal lahir</label><input id="tanggal_lahir" type="date" name="tanggal_lahir" value="{{ $f('tanggal_lahir') }}" required class="input @error('tanggal_lahir') is-invalid @enderror">@error('tanggal_lahir')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label for="alamat" class="label">Alamat lengkap</label><textarea id="alamat" name="alamat" rows="2" maxlength="500" required class="textarea @error('alamat') is-invalid @enderror">{{ $f('alamat') }}</textarea>@error('alamat')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>

                        <fieldset class="grid gap-4 sm:grid-cols-2">
                            <legend class="mb-3 font-display font-bold text-primary">2. Data Akademik</legend>
                            <div class="sm:col-span-2"><label for="asal_sekolah" class="label">Asal sekolah</label><input id="asal_sekolah" name="asal_sekolah" value="{{ $f('asal_sekolah') }}" maxlength="120" required class="input @error('asal_sekolah') is-invalid @enderror">@error('asal_sekolah')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="jalur" class="label">Jalur pendaftaran</label>
                                <select id="jalur" name="jalur" required class="select @error('jalur') is-invalid @enderror"><option value="">- pilih -</option>@foreach ($jalur as $j)<option value="{{ $j }}" @selected($f('jalur') === $j)>{{ $j }}</option>@endforeach</select>@error('jalur')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="peminatan" class="label">Peminatan <span class="font-normal text-slate-400">(opsional)</span></label>
                                <select id="peminatan" name="peminatan" class="select"><option value="">- pilih -</option>@foreach ($peminatan as $p)<option value="{{ $p }}" @selected($f('peminatan') === $p)>{{ $p }}</option>@endforeach</select></div>
                            <div><label for="nilai_rata" class="label">Nilai rata-rata rapor <span class="font-normal text-slate-400">(opsional)</span></label><input id="nilai_rata" type="number" step="0.01" min="0" max="100" name="nilai_rata" value="{{ $f('nilai_rata') }}" class="input @error('nilai_rata') is-invalid @enderror">@error('nilai_rata')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="berkas" class="label">Berkas pendukung <span class="font-normal text-slate-400">(opsional)</span></label><input id="berkas" type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" class="input @error('berkas') is-invalid @enderror"><p class="field-hint">PDF/JPG/PNG, maks. 2 MB (mis. KK atau rapor).</p>@error('berkas')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>

                        <fieldset class="grid gap-4 sm:grid-cols-2">
                            <legend class="mb-3 font-display font-bold text-primary">3. Orang Tua / Wali</legend>
                            <div class="sm:col-span-2"><label for="nama_ortu" class="label">Nama orang tua/wali</label><input id="nama_ortu" name="nama_ortu" value="{{ $f('nama_ortu') }}" maxlength="120" required class="input @error('nama_ortu') is-invalid @enderror">@error('nama_ortu')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="telepon" class="label">Telepon / WhatsApp</label><input id="telepon" name="telepon" value="{{ $f('telepon') }}" inputmode="tel" maxlength="20" required class="input @error('telepon') is-invalid @enderror">@error('telepon')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label for="email" class="label">Email <span class="font-normal text-slate-400">(opsional)</span></label><input id="email" type="email" name="email" value="{{ $f('email') }}" maxlength="150" class="input @error('email') is-invalid @enderror">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>
                    </div>

                    <div class="card-footer flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">Dengan mendaftar, Anda menyatakan data yang diisi benar.</p>
                        <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-send"></i> Kirim Pendaftaran</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
