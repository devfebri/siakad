<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $kuitansi ? 'Kuitansi' : 'Invoice' }} {{ $invoice->nomor }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .kop { background: {{ $warna }}; color: #fff; padding: 18px 24px; }
        .kop h1 { margin: 0; font-size: 18px; }
        .kop p { margin: 2px 0 0; font-size: 10px; color: #fde68a; }
        .garis { height: 4px; background: #f59e0b; }
        .isi { padding: 22px 24px; }
        h2 { font-size: 16px; margin: 0 0 4px; color: {{ $warna }}; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 7px 8px; text-align: left; vertical-align: top; }
        .rincian th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; color: #475569; }
        .rincian td { border-bottom: 1px solid #e2e8f0; }
        .kanan { text-align: right; }
        .total td { font-size: 13px; font-weight: bold; border-top: 2px solid {{ $warna }}; }
        .cap { display: inline-block; border: 3px solid #059669; color: #059669; padding: 4px 14px; font-size: 18px; font-weight: bold; letter-spacing: 2px; }
        .cap.menunggu { border-color: #d97706; color: #d97706; }
        .cap.batal { border-color: #64748b; color: #64748b; }
        .abu { color: #64748b; }
        .kotak { border: 1px solid #cbd5e1; padding: 10px 12px; margin-top: 14px; }
        .terbilang { font-style: italic; background: #f8fafc; padding: 8px 10px; border-left: 3px solid #f59e0b; margin: 8px 0; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $produk }}</h1><p>{{ $kontak ?: 'Layanan sistem informasi sekolah' }}</p></div>
    <div class="garis"></div>

    <div class="isi">
        <table>
            <tr>
                <td style="width:60%">
                    <h2>{{ $kuitansi ? 'KUITANSI PEMBAYARAN' : 'INVOICE' }}</h2>
                    <div class="abu">Nomor</div><strong>{{ $invoice->nomor }}</strong>
                </td>
                <td class="kanan">
                    @if ($kuitansi || $invoice->status === 'lunas')
                        <span class="cap">LUNAS</span>
                    @elseif ($invoice->status === 'batal')
                        <span class="cap batal">DIBATALKAN</span>
                    @else
                        <span class="cap menunggu">BELUM DIBAYAR</span>
                    @endif
                </td>
            </tr>
        </table>

        <table style="margin-top:10px">
            <tr>
                <td style="width:55%">
                    <div class="abu">{{ $kuitansi ? 'Diterima dari' : 'Ditagihkan kepada' }}</div>
                    <strong>{{ $invoice->sekolah?->nama }}</strong><br>
                    @if ($admin){{ $admin->name }} · {{ $admin->email }}<br>@endif
                    @if ($invoice->sekolah?->npsn)NPSN {{ $invoice->sekolah->npsn }}@endif
                </td>
                <td>
                    <div class="abu">Tanggal terbit</div>{{ $invoice->created_at?->translatedFormat('d F Y') }}<br>
                    @if ($kuitansi)
                        <div class="abu" style="margin-top:6px">Tanggal pembayaran</div>{{ $invoice->dibayar_pada?->translatedFormat('d F Y') }}<br>
                        <div class="abu" style="margin-top:6px">Metode</div>{{ $invoice->metode ?: '-' }}
                    @elseif ($invoice->jatuh_tempo)
                        <div class="abu" style="margin-top:6px">Jatuh tempo</div>{{ $invoice->jatuh_tempo->translatedFormat('d F Y') }}
                    @endif
                </td>
            </tr>
        </table>

        <table class="rincian" style="margin-top:16px">
            <thead><tr><th>Rincian</th><th>Periode</th><th class="kanan">Jumlah</th></tr></thead>
            <tbody>
                <tr>
                    <td>Sewa paket <strong>{{ $invoice->paket?->nama }}</strong> · {{ $invoice->durasi_bulan }} bulan</td>
                    <td>@if ($invoice->langganan){{ $invoice->langganan->mulai->translatedFormat('d M Y') }} – {{ $invoice->langganan->berakhir->translatedFormat('d M Y') }}@else - @endif</td>
                    <td class="kanan">Rp {{ number_format($invoice->jumlah, 0, ',', '.') }}</td>
                </tr>
            </tbody>
            <tfoot><tr class="total"><td colspan="2" class="kanan">Total</td><td class="kanan">Rp {{ number_format($invoice->jumlah, 0, ',', '.') }}</td></tr></tfoot>
        </table>

        @if ($kuitansi)
            <div class="terbilang"><span class="abu">Terbilang:</span> {{ ucfirst($terbilang) }}</div>
            <p class="abu">Untuk pembayaran sewa layanan {{ $produk }} atas invoice {{ $invoice->nomor }}. Terima kasih.</p>
        @elseif ($invoice->status === 'menunggu' && $petunjuk)
            <div class="kotak"><strong>Cara pembayaran</strong><br>{{ $petunjuk }}</div>
        @endif
    </div>
</body>
</html>
