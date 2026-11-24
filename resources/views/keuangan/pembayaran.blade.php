@extends('layouts.admin')

@section('title', 'Pembayaran')

@section('content')
    @include('keuangan._tab')
    <x-admin.crud title="Riwayat Pembayaran" subtitle="Semua pembayaran yang tercatat. Unduh kuitansi atau batalkan bila salah catat." name="Pembayaran"
        :url="route($panel.'/keuangan/pembayaran.data')">
        <x-slot:actions>
            <select data-crud-filter="metode" class="select !w-auto" aria-label="Filter cara bayar">
                <option value="">Semua cara bayar</option>
                @foreach ($metode as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <select data-crud-filter="batal" class="select !w-auto" aria-label="Filter status">
                <option value="">Semua</option><option value="1">Yang dibatalkan</option>
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="nomor">Kuitansi</th>
            <th data-col="tanggal" class="no-sort">Tanggal</th>
            <th data-col="siswa" class="no-sort">Siswa</th>
            <th data-col="tagihan" class="no-sort">Untuk</th>
            <th data-col="jumlah" class="no-sort">Jumlah</th>
            <th data-col="metode" class="no-sort">Cara bayar</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
    </x-admin.crud>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrf = $('meta[name=csrf-token]').attr('content');
        const base = @js(url($panel.'/keuangan/pembayaran'));

        $(document).on('draw.dt', 'table', function () {
            const tabel = $(this).DataTable();
            $(this).find('tbody tr').each(function () {
                const d = tabel.row(this).data();
                if (!d || $(this).find('[data-kuitansi]').length) return;
                const $aksi = $(this).find('td:last > div');

                $(`<a href="${base}/${d.id}/kuitansi" class="btn btn-soft btn-sm" data-kuitansi><i class="bi bi-receipt"></i> Kuitansi</a>`).prependTo($aksi);

                if (!d.batal) {
                    $('<button type="button" class="btn btn-soft btn-sm" title="Batalkan pembayaran"><i class="bi bi-x-circle"></i></button>').appendTo($aksi).on('click', () => {
                        Swal.fire({ title: 'Batalkan pembayaran?', html: `${d.nomor} · ${d.jumlah}<br><small>Tagihan akan kembali belum lunas.</small>`, icon: 'warning', input: 'text', inputPlaceholder: 'Alasan (wajib)', showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Tidak', reverseButtons: true, confirmButtonColor: '#dc2626' })
                            .then((r) => r.isConfirmed && $.ajax({ url: `${base}/${d.id}/batal`, method: 'POST', data: { alasan: r.value }, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                                .done((res) => { window.toast(res.message); tabel.ajax.reload(null, false); })
                                .fail((xhr) => window.toast(Object.values(xhr.responseJSON?.errors || {}).flat()[0] || xhr.responseJSON?.message || 'Gagal.', 'error')));
                    });
                }
            });
        });
    });
</script>
@endpush
