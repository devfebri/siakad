@extends('layouts.admin')

@section('title', 'Invoice')

@section('content')
    <x-admin.crud title="Invoice Sewa" subtitle="Konfirmasi pembayaran manual membuat atau memperpanjang langganan sekolah." name="Invoice" add-label="Buat Invoice" add-icon="receipt"
        :url="route('platform/invoice.data')" :store="route('platform/invoice.store')">
        <x-slot:actions>
            <select data-crud-filter="status" class="select !w-auto" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach (\App\Invoice::STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nomor">Nomor</th>
            <th data-col="sekolah" class="no-sort">Sekolah</th>
            <th data-col="paket" class="no-sort">Paket</th>
            <th data-col="jumlah" class="no-sort">Jumlah</th>
            <th data-col="jatuh_tempo" class="no-sort">Jatuh tempo</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="sekolah_id" id="iv_sekolah" label="Sekolah" as="select" class="sm:col-span-2" required>
                @foreach ($sekolah as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="paket_id" id="iv_paket" label="Paket" as="select" required>
                @foreach ($paket as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach
            </x-admin.field>
            <x-admin.field name="durasi_bulan" id="iv_durasi" label="Durasi" as="select" required>
                @foreach ($durasi as $d)<option value="{{ $d }}">{{ $d }} bulan</option>@endforeach
            </x-admin.field>
            <x-admin.field name="catatan" id="iv_cat" label="Catatan" class="sm:col-span-2" maxlength="300" optional />
        </x-slot:fields>
    </x-admin.crud>
@endsection

@push('scripts')
<script>
    // Tombol Lunas / Batal pada baris invoice yang masih menunggu pembayaran.
    document.addEventListener('DOMContentLoaded', () => {
        const kirim = (url, data) => $.ajax({ url, method: 'POST', data, dataType: 'json', headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') } });

        $(document).on('draw.dt', 'table', function () {
            const tabel = $(this).DataTable();
            $(this).find('tbody tr').each(function () {
                const d = tabel.row(this).data();
                if (!d) return;
                const $aksi = $(this).find('td:last > div');
                if (!$aksi.find('[data-pdf]').length) {
                    $(`<a href="{{ url('platform/invoice') }}/${d.id}/pdf" class="btn btn-soft btn-sm" data-pdf title="Invoice PDF"><i class="bi bi-file-earmark-pdf"></i></a>`).appendTo($aksi);
                    if (d.status_raw === 'lunas') $(`<a href="{{ url('platform/invoice') }}/${d.id}/kuitansi" class="btn btn-soft btn-sm" title="Kuitansi PDF"><i class="bi bi-receipt"></i></a>`).appendTo($aksi);
                }
                if (d.status_raw !== 'menunggu' || $(this).find('[data-lunas]').length) return;

                $('<button type="button" class="btn btn-success btn-sm" data-lunas><i class="bi bi-check2-circle"></i> Lunas</button>').prependTo($aksi).on('click', () => {
                    Swal.fire({ title: 'Konfirmasi pembayaran?', html: `Invoice <b>${d.nomor}</b> (${d.jumlah}) ditandai lunas dan langganan diaktifkan.`, icon: 'question', input: 'text', inputPlaceholder: 'Metode (mis. Transfer BCA)', showCancelButton: true, confirmButtonText: 'Ya, lunas', cancelButtonText: 'Batal', reverseButtons: true })
                        .then((r) => r.isConfirmed && kirim(`{{ url('platform/invoice') }}/${d.id}/lunas`, { metode: r.value })
                            .done((res) => { window.toast(res.message); tabel.ajax.reload(null, false); })
                            .fail((xhr) => window.toast(xhr.responseJSON?.errors?.status?.[0] || xhr.responseJSON?.message || 'Gagal.', 'error')));
                });

                $('<button type="button" class="btn btn-soft btn-sm" data-batal title="Batalkan invoice"><i class="bi bi-x-circle"></i></button>').appendTo($aksi).on('click', () => {
                    Swal.fire({ title: 'Batalkan invoice?', text: d.nomor, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Tidak', reverseButtons: true, confirmButtonColor: '#dc2626' })
                        .then((r) => r.isConfirmed && kirim(`{{ url('platform/invoice') }}/${d.id}/batal`)
                            .done((res) => { window.toast(res.message); tabel.ajax.reload(null, false); })
                            .fail((xhr) => window.toast(xhr.responseJSON?.errors?.status?.[0] || 'Gagal.', 'error')));
                });
            });
        });
    });
</script>
@endpush
