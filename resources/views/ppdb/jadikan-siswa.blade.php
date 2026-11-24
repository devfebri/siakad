@extends('layouts.admin')

@section('title', 'Jadikan Siswa')

@section('content')
    <x-admin.page-header :title="'Jadikan Siswa: '.$p->nama" :subtitle="$p->no_pendaftaran.' · Tahun ajaran '.$p->tahun_ajaran" :back="route('admin/ppdb.show', $p)" />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-bold">Belum bisa disimpan:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- ============================================================ isian --}}
        <form method="POST" action="{{ route('admin/ppdb.jadikan.simpan', $p) }}" class="card lg:col-span-3" id="formKonversi">
            @csrf
            <div class="card-header">Lengkapi data</div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="agama">Agama</label>
                    <select id="agama" name="agama" class="select" required>
                        <option value="">Pilih agama...</option>
                        @foreach ($agama as $a)<option value="{{ $a }}" @selected(old('agama', $input['agama'] ?? $p->agama) === $a)>{{ $a }}</option>@endforeach
                    </select>
                    @unless ($p->agama)<p class="field-hint">Pendaftar ini belum mengisi agama. Tanyakan lalu pilih di sini.</p>@endunless
                </div>
                <div>
                    <label class="label" for="nisn">NISN <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <input id="nisn" name="nisn" value="{{ old('nisn', $input['nisn'] ?? $p->nisn) }}" class="input" inputmode="numeric" maxlength="20" placeholder="{{ $p->no_pendaftaran }}">
                    <p class="field-hint">Kosong = dipakai nomor pendaftaran sebagai pengganti sementara.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="rombel_id">Tempatkan di rombel</label>
                    <select id="rombel_id" name="rombel_id" class="select">
                        <option value="">Belum ditempatkan (atur nanti)</option>
                        @foreach ($kelas as $k)<option value="{{ $k->id }}" @selected((string) old('rombel_id', $input['rombel_id'] ?? '') === (string) $k->id)>{{ $k->nama_kelas }}</option>@endforeach
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="buat_ortu" value="1" class="check" @checked(old('buat_ortu', $input['buat_ortu'] ?? true))> Buatkan juga akun orang tua ({{ $p->nama_ortu }}) dan hubungkan ke siswa ini</label>
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <a href="{{ route('admin/ppdb.show', $p) }}" class="btn btn-outline">Batal</a>
                <button class="btn btn-accent btn-lg" @disabled(collect($r['galat'])->reject(fn ($g) => str_starts_with($g, 'Agama'))->isNotEmpty())><i class="bi bi-person-plus-fill"></i> Jadikan Siswa</button>
            </div>
        </form>

        {{-- ============================================================ pratinjau --}}
        <div class="card h-fit lg:col-span-2">
            <div class="card-header">Yang akan dibuat</div>
            <div class="space-y-4 p-5 text-sm">
                @foreach ($r['galat'] as $g)
                    <p class="flex gap-2 rounded-lg bg-red-50 p-3 text-red-800"><i class="bi bi-x-circle-fill mt-0.5"></i> <span>{{ $g }}</span></p>
                @endforeach
                @foreach ($r['peringatan'] as $w)
                    <p class="flex gap-2 rounded-lg bg-amber-50 p-3 text-amber-900"><i class="bi bi-exclamation-triangle-fill mt-0.5"></i> <span>{{ $w }}</span></p>
                @endforeach

                <div>
                    <p class="font-display font-bold"><i class="bi bi-mortarboard text-primary"></i> Siswa</p>
                    <dl class="mt-2 grid grid-cols-3 gap-y-1">
                        <dt class="text-slate-500">Nama</dt><dd class="col-span-2 font-semibold">{{ $p->nama }}</dd>
                        <dt class="text-slate-500">NISN</dt><dd class="col-span-2 font-semibold">{{ $r['nisn'] }}@if ($r['nisn_sementara']) <span class="badge badge-warning">sementara</span>@endif</dd>
                        <dt class="text-slate-500">Username</dt><dd class="col-span-2 font-mono">{{ $r['username'] }}</dd>
                        <dt class="text-slate-500">Password awal</dt><dd class="col-span-2 font-mono">{{ \App\Http\Controllers\SiswaController::DEFAULT_PASSWORD }}</dd>
                        <dt class="text-slate-500">Agama</dt><dd class="col-span-2">{{ $r['agama'] ?: '-' }}</dd>
                        <dt class="text-slate-500">Rombel</dt><dd class="col-span-2">{{ $kelas->firstWhere('id', $r['rombel_id'])?->nama_kelas ?? 'Belum ditempatkan' }}</dd>
                    </dl>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="font-display font-bold"><i class="bi bi-person-hearts text-primary"></i> Orang tua</p>
                    @php $o = $r['ortu']; @endphp
                    @if ($o['mode'] === 'baru')
                        <p class="mt-2">Akun <strong>baru</strong> untuk <strong>{{ $o['nama'] }}</strong> ({{ $o['no_hp'] }}), password awal <span class="font-mono">{{ \App\Http\Controllers\OrangTuaController::DEFAULT_PASSWORD }}</span>.</p>
                    @elseif ($o['mode'] === 'hubungkan')
                        <p class="mt-2">Dihubungkan ke akun orang tua yang <strong>sudah ada</strong>: {{ $o['ortu']->nama }} ({{ $o['ortu']->no_hp }}).</p>
                    @else
                        <p class="mt-2 text-slate-500">Tidak dibuat. {{ $o['alasan'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pratinjau mengikuti isian: setiap pilihan diubah, halaman dimuat ulang dengan isian pada alamat (tanpa menyimpan apa pun).
        document.querySelectorAll('#formKonversi select, #formKonversi input[type=checkbox]').forEach((el) => el.addEventListener('change', segarkan));
        document.getElementById('nisn').addEventListener('change', segarkan);
        function segarkan() {
            const f = document.getElementById('formKonversi');
            const p = new URLSearchParams();
            ['agama', 'nisn', 'rombel_id'].forEach((n) => p.set(n, f.elements[n].value));
            if (f.elements['buat_ortu'].checked) p.set('buat_ortu', '1');
            location.href = location.pathname + '?' + p.toString();
        }
    </script>
@endsection
