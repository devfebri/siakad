@extends('layouts.admin')

@section('title', 'Proses Tahunan')

@section('content')
    <x-admin.page-header title="Proses Tahunan" subtitle="Buka tahun ajaran baru, proses kenaikan dan kelulusan, lalu aktifkan. Selama masih draf, semuanya bisa dibatalkan." />

    {{-- Setelah setiap aksi, hanya wilayah ini yang dimuat ulang lewat jQuery AJAX (lihat refreshRegion di crud.js). --}}
    <div id="pt-region" data-ajax-region>
    @unless ($aktif)
        <div class="card p-10 text-center text-slate-500">Belum ada tahun ajaran aktif. Atur dulu di menu <a href="{{ route('admin/tahun-ajaran') }}">Tahun Ajaran</a>.</div>
    @elseif (! $baru)
        <form data-ajax-form novalidate action="{{ route('admin/proses-tahunan.buka') }}" class="card max-w-2xl">
            <div class="card-header">Buka Tahun Ajaran Baru <span class="badge badge-success">Aktif sekarang: {{ $aktif->nama }}</span></div>
            <div class="space-y-3 p-5">
                <div><label class="label" for="pt_nama">Tahun ajaran baru</label><input id="pt_nama" name="nama" class="input" maxlength="9" placeholder="{{ (int) substr($aktif->nama, 0, 4) + 1 }}/{{ (int) substr($aktif->nama, 0, 4) + 2 }}" required></div>
                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="salin_wali" value="1" class="check"> Salin wali kelas ke rombel baru</label>
                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="salin_jadwal" value="1" class="check"> Salin jadwal pelajaran semester terakhir ke semester Ganjil</label>
                <p class="text-xs text-slate-500">Rombel tahun ajaran aktif disalin (tanpa anggota). Tahun ajaran baru berstatus <strong>Draf</strong> dan baru berlaku setelah diaktifkan di halaman ini.</p>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-calendar-plus"></i> Buka tahun ajaran</button></div>
        </form>
    @else
        <div class="card mb-6 flex flex-wrap items-center gap-3 p-5">
            <div class="min-w-0 flex-1">
                <p class="font-display text-lg font-bold">{{ $aktif->nama }} → {{ $baru->nama }} <span class="badge badge-warning">Draf</span></p>
                <p class="text-sm text-slate-600">{{ $belum > 0 ? $belum.' siswa belum diproses.' : 'Semua siswa sudah diproses.' }}</p>
            </div>
            <form data-ajax-form action="{{ route('admin/proses-tahunan.aktifkan') }}"><button type="submit" class="btn btn-accent btn-sm" @disabled($belum > 0)><i class="bi bi-play-circle"></i> Aktifkan {{ $baru->nama }}</button></form>
            <form data-ajax-form data-method="DELETE" action="{{ route('admin/proses-tahunan.batal-semua') }}"><button type="submit" class="btn btn-outline btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Batalkan semua proses</button></form>
            <form data-ajax-form data-method="DELETE" action="{{ route('admin/proses-tahunan.batal-tahun') }}"><button type="submit" class="btn btn-soft btn-sm"><i class="bi bi-trash"></i> Batalkan tahun ajaran draf</button></form>
        </div>

        @foreach ($rombel as $r)
            @php $k = $r['kelas']; @endphp
            <div class="card mb-6">
                <div class="card-header flex-wrap">
                    <span>Rombel {{ $k->nama_kelas }} <span class="badge badge-muted">{{ $r['diproses'] }}/{{ $r['siswa']->count() }} diproses</span> @if ($r['akhir'])<span class="badge badge-primary">Tingkat akhir</span>@endif</span>
                    <span class="text-xs font-normal text-slate-500">Wali kelas: {{ $k->wali?->nama_guru ?? '-' }}</span>
                </div>

                <form data-ajax-form novalidate action="{{ route('admin/proses-tahunan.rombel', $k) }}" class="grid items-end gap-3 border-b border-slate-100 p-4 sm:grid-cols-5">
                    @unless ($r['akhir'])
                        <div><label class="label" for="tn{{ $k->id }}">Tujuan naik</label><select id="tn{{ $k->id }}" name="tujuan_naik" class="select"><option value="">- otomatis -</option>@foreach ($rombelBaru as $b)<option value="{{ $b->id }}" @selected($b->id === $r['tujuanNaik'])>{{ $b->nama_kelas }}</option>@endforeach</select></div>
                    @endunless
                    <div><label class="label" for="tt{{ $k->id }}">Tujuan tinggal</label><select id="tt{{ $k->id }}" name="tujuan_tinggal" class="select"><option value="">- otomatis -</option>@foreach ($rombelBaru as $b)<option value="{{ $b->id }}" @selected($b->id === $r['tujuanTinggal'])>{{ $b->nama_kelas }}</option>@endforeach</select></div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="tanpa_usulan" value="1" class="check"> Siswa tanpa usulan dianggap {{ $r['akhir'] ? 'lulus' : 'naik kelas' }}</label>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-lightning-charge"></i> Proses rombel</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="table-clean">
                        <thead><tr><th>Nama</th><th>Usulan wali</th><th>Hasil</th><th class="text-right">Ubah hasil</th></tr></thead>
                        <tbody>
                            @forelse ($r['siswa'] as $s)
                                @php $kn = $s['kenaikan']; $u = $s['usulan']; $m = $s['model']; @endphp
                                <tr>
                                    <td class="font-semibold">{{ $m->nama_siswa }}<br><span class="text-xs font-normal text-slate-500">{{ $m->nisn }}</span></td>
                                    <td>@if ($u)<span class="badge badge-muted">{{ \App\UsulanKenaikan::USULAN[$u->usulan] }}</span>@if ($u->catatan)<br><span class="text-xs text-slate-500">{{ $u->catatan }}</span>@endif @else <span class="text-slate-400">-</span>@endif</td>
                                    <td>@if ($kn)<span class="badge badge-{{ in_array($kn->hasil, ['naik', 'lulus']) ? 'success' : 'warning' }}">{{ $hasil[$kn->hasil] }}</span>@if ($kn->rombel_tujuan_id)<span class="text-xs text-slate-500"> → {{ $rombelBaru->firstWhere('id', $kn->rombel_tujuan_id)?->nama_kelas }}</span>@endif @else <span class="badge badge-danger">Belum diproses</span>@endif</td>
                                    <td class="text-right">
                                        <form data-ajax-form novalidate action="{{ route('admin/proses-tahunan.siswa', $m->id) }}" class="inline-flex flex-wrap items-center justify-end gap-1">
                                            <select name="hasil" class="select !w-auto" aria-label="Hasil {{ $m->nama_siswa }}">
                                                @foreach ($hasil as $kode => $label)
                                                    @continue(($r['akhir'] && $kode === 'naik') || (! $r['akhir'] && in_array($kode, ['lulus', 'tidak_lulus'])))
                                                    <option value="{{ $kode }}" @selected(($kn?->hasil ?? $u?->usulan) === $kode)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @if ($r['akhir'])<input name="no_ijazah" value="{{ $kn?->no_ijazah }}" class="input !w-36" placeholder="No. ijazah" maxlength="60" aria-label="Nomor ijazah {{ $m->nama_siswa }}">@endif
                                            <button type="submit" class="btn btn-soft btn-sm" title="Simpan hasil"><i class="bi bi-check2"></i></button>
                                        </form>
                                        @if ($kn)<form data-ajax-form data-method="DELETE" action="{{ route('admin/proses-tahunan.siswa.batal', $m->id) }}" class="inline"><button type="submit" class="btn btn-ghost btn-sm" title="Batalkan"><i class="bi bi-x-lg"></i></button></form>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-slate-500">Rombel ini tidak punya siswa aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endunless
    </div>
@endsection
