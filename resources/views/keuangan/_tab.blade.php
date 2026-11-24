@php $p = auth()->user()->panel; @endphp
<x-admin.subnav :items="[
    ['Ringkasan', route($p === 'bendahara' ? 'bendahara/dashboard' : 'admin/keuangan'), [$p === 'bendahara' ? 'bendahara/dashboard' : 'admin/keuangan']],
    ['Tagihan', route($p.'/keuangan/tagihan'), [$p.'/keuangan/tagihan*']],
    ['Pembayaran', route($p.'/keuangan/pembayaran'), [$p.'/keuangan/pembayaran*']],
    ['Jenis Biaya', route($p.'/keuangan/biaya'), [$p.'/keuangan/biaya*']],
    ...($p === 'admin' ? [['Pembayaran Online', route('admin/keuangan/online'), ['admin/keuangan/online*']]] : []),
]" />
