<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $p->nomor }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .kop { border-bottom: 3px solid {{ $warna }}; padding-bottom: 8px; }
        .kop h1 { margin: 0; font-size: 16px; color: {{ $warna }}; }
        .kop p { margin: 1px 0 0; font-size: 9px; color: #475569; }
        h2 { text-align: center; font-size: 14px; letter-spacing: 2px; margin: 12px 0 2px; }
        .nomor { text-align: center; color: #64748b; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .label { width: 24%; color: #64748b; }
        .jumlah { font-size: 15px; font-weight: bold; border: 2px solid {{ $warna }}; padding: 6px 12px; display: inline-block; }
        .terbilang { font-style: italic; background: #f8fafc; padding: 6px 8px; border-left: 3px solid #f59e0b; margin: 8px 0; }
        .batal { color: #dc2626; font-weight: bold; text-align: center; font-size: 13px; }
        .ttd { text-align: center; width: 40%; }
    </style>
</head>
<body>
    <div class="kop">
        <table><tr>
            @if ($logo)<td style="width:54px"><img src="{{ $logo }}" style="height:46px"></td>@endif
            <td><h1>{{ $sekolah }}</h1><p>{{ $alamat }}</p><p>{{ $kontak }}</p></td>
        </tr></table>
    </div>

    <h2>KUITANSI</h2>
    <div class="nomor">Nomor {{ $p->nomor }}</div>
    @if ($p->dibatalkan())<div class="batal">PEMBAYARAN INI DIBATALKAN ({{ $p->alasan_batal }})</div>@endif

    <table>
        <tr><td class="label">Telah diterima dari</td><td>: Orang tua/wali <strong>{{ $p->tagihan?->siswa?->nama_siswa }}</strong> (kelas {{ $p->tagihan?->siswa?->kelas?->nama_kelas ?? '-' }})</td></tr>
        <tr><td class="label">Untuk pembayaran</td><td>: {{ $p->tagihan?->nama }}</td></tr>
        <tr><td class="label">Metode</td><td>: {{ \App\PembayaranSpp::METODE[$p->metode] ?? $p->metode }}@if ($p->referensi) · {{ $p->referensi }}@endif</td></tr>
        <tr><td class="label">Jumlah</td><td><span class="jumlah">Rp {{ number_format($p->jumlah, 0, ',', '.') }}</span></td></tr>
    </table>
    <div class="terbilang">Terbilang: {{ ucfirst($terbilang) }}</div>
    @if ($p->tagihan)
        <p style="margin:4px 0;color:#475569">Sisa tagihan setelah pembayaran ini: <strong>Rp {{ number_format($p->tagihan->sisa, 0, ',', '.') }}</strong>@if ($p->tagihan->status === 'lunas') (LUNAS)@endif</p>
    @endif

    <table style="margin-top:10px"><tr>
        <td></td>
        <td class="ttd">{{ $p->tanggal?->translatedFormat('d F Y') }}<br>Penerima,<br><br><br><br><strong>{{ $p->penerima?->name ?? '________________' }}</strong></td>
    </tr></table>
</body>
</html>
