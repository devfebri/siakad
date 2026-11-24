@extends('layouts.admin')

@section('title', 'Tagihan')

@section('content')
    @include('keuangan._tab')
    <x-admin.crud title="Tagihan Siswa" subtitle="Buat tagihan untuk satu kelas atau semua siswa sekaligus, lalu catat pembayarannya dengan tombol Bayar." name="Tagihan"
        :url="route($panel.'/keuangan/tagihan.data')" :store="route($panel.'/keuangan/tagihan.massal')" add-label="Buat Tagihan" add-icon="receipt">
        <x-slot:actions>
            <select data-crud-filter="status" class="select !w-auto" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach (\App\Tagihan::STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <select data-crud-filter="kelas_id" class="select !w-auto" aria-label="Filter kelas">
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
            </select>
            <select data-crud-filter="terlambat" class="select !w-auto" aria-label="Filter jatuh tempo">
                <option value="">Semua tanggal</option>
                <option value="1" @selected(request('terlambat'))>Lewat jatuh tempo</option>
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="siswa" class="no-sort">Siswa</th>
            <th data-col="kelas" class="no-sort">Kelas</th>
            <th data-col="nama">Tagihan</th>
            <th data-col="jumlah" class="no-sort">Jumlah</th>
            <th data-col="sisa" class="no-sort">Sisa</th>
            <th data-col="jatuh_tempo" class="no-sort">Jatuh tempo</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <p class="rounded-xl bg-primary-soft p-3 text-sm sm:col-span-2">Tagihan dibuat untuk <strong>siswa aktif</strong> yang sudah punya kelas. Tagihan yang sudah ada tidak dibuat dua kali, jadi aman diulang.</p>
            <x-admin.field name="jenis_biaya_id" id="tg_biaya" label="Jenis biaya" as="select" class="sm:col-span-2" required>
                <option value="">Pilih biaya...</option>
                @foreach ($biaya as $b)<option value="{{ $b->id }}">{{ $b->nama }} · Rp {{ number_format($b->nominal, 0, ',', '.') }} ({{ $b->jenis === 'bulanan' ? 'bulanan' : 'sekali bayar' }})</option>@endforeach
            </x-admin.field>
            <x-admin.field name="kelas_id" id="tg_kelas" label="Untuk kelas" as="select" class="sm:col-span-2">
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)<option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="bulan_mulai" id="tg_bulan" label="Mulai bulan" type="month" value="{{ now()->format('Y-m') }}" required />
            <x-admin.field name="jumlah_bulan" id="tg_jml" label="Untuk berapa bulan" type="number" min="1" max="12" value="1" hint="Hanya untuk biaya bulanan. 12 = setahun penuh." />
            <x-admin.field name="tanggal_jatuh_tempo" id="tg_tgl" label="Jatuh tempo tiap tanggal" type="number" min="1" max="28" value="10" required />
            <x-admin.field name="nominal" id="tg_nominal" label="Nominal khusus (Rp)" type="number" min="1" optional hint="Kosongkan untuk memakai nominal dari Jenis Biaya." />
        </x-slot:fields>
    </x-admin.crud>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrf = $('meta[name=csrf-token]').attr('content');
        const kirim = (url, data, method = 'POST') => $.ajax({ url, method, data, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        const rp = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');
        const galat = (xhr) => Object.values(xhr.responseJSON?.errors || {}).flat()[0] || xhr.responseJSON?.message || 'Gagal.';
        const base = @js(url($panel.'/keuangan/tagihan'));
        const metode = @js(collect($metode)->except('online')->all());

        $(document).on('draw.dt', 'table', function () {
            const tabel = $(this).DataTable();
            $(this).find('tbody tr').each(function () {
                const d = tabel.row(this).data();
                if (!d || $(this).find('[data-bayar]').length || !['belum', 'sebagian'].includes(d.status_raw)) return;
                const $aksi = $(this).find('td:last > div');

                $('<button type="button" class="btn btn-success btn-sm" data-bayar><i class="bi bi-cash-coin"></i> Bayar</button>').prependTo($aksi).on('click', () => {
                    const pilihan = Object.entries(metode).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
                    Swal.fire({
                        title: 'Catat pembayaran', showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal', reverseButtons: true, focusConfirm: false,
                        html: `<div style="text-align:left"><p style="margin:0 0 8px"><b>${d.siswa}</b> · ${d.nama}<br>Sisa tagihan: <b>${rp(d.sisa_raw)}</b></p>
                            <label class="label" for="by_jumlah">Jumlah dibayar (Rp)</label><input id="by_jumlah" type="number" min="1" max="${d.sisa_raw}" value="${d.sisa_raw}" class="input">
                            <label class="label" for="by_metode" style="margin-top:8px">Cara bayar</label><select id="by_metode" class="select">${pilihan}</select>
                            <label class="label" for="by_cat" style="margin-top:8px">Catatan (boleh kosong)</label><input id="by_cat" type="text" maxlength="300" class="input"></div>`,
                        preConfirm: () => ({ jumlah: $('#by_jumlah').val(), metode: $('#by_metode').val(), catatan: $('#by_cat').val() }),
                    }).then((r) => {
                        if (!r.isConfirmed) return;
                        kirim(`${base}/${d.id}/bayar`, r.value)
                            .done((res) => {
                                tabel.ajax.reload(null, false);
                                Swal.fire({ icon: 'success', title: 'Pembayaran dicatat', text: res.message, showCancelButton: true, confirmButtonText: 'Unduh kuitansi', cancelButtonText: 'Tutup' })
                                    .then((x) => x.isConfirmed && window.open(res.kuitansi, '_blank'));
                            })
                            .fail((xhr) => window.toast(galat(xhr), 'error'));
                    });
                });

                if (d.status_raw === 'belum') {
                    $('<button type="button" class="btn btn-soft btn-sm" title="Batalkan tagihan"><i class="bi bi-x-circle"></i></button>').appendTo($aksi).on('click', () => {
                        Swal.fire({ title: 'Batalkan tagihan?', text: `${d.siswa} · ${d.nama}`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Tidak', reverseButtons: true, confirmButtonColor: '#dc2626' })
                            .then((r) => r.isConfirmed && kirim(`${base}/${d.id}/batal`, {})
                                .done((res) => { window.toast(res.message); tabel.ajax.reload(null, false); })
                                .fail((xhr) => window.toast(galat(xhr), 'error')));
                    });
                }
            });
        });
    });
</script>
@endpush
