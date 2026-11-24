<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip Konsultasi Online</title>
    <style>
        @page { margin: 28px 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        .kop { width: 100%; border-bottom: 3px solid {{ $warna }}; padding-bottom: 8px; margin-bottom: 4px; }
        .kop td { vertical-align: middle; }
        .nama { font-size: 16px; font-weight: bold; color: {{ $warna }}; }
        .kecil { font-size: 9.5px; color: #475569; }
        .garis { border-top: 1px solid #f59e0b; margin: 0 0 14px; }
        h1 { font-size: 14px; margin: 0 0 8px; }
        .info { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .info td { padding: 3px 6px; border: 1px solid #e2e8f0; }
        .info td.k { width: 22%; background: #f1f5f9; color: #475569; }
        .pesan { margin-bottom: 9px; page-break-inside: avoid; }
        .meta { font-size: 9px; color: #64748b; }
        .isi { white-space: pre-wrap; padding: 6px 9px; border-radius: 6px; margin-top: 2px; }
        .pengunjung .isi { background: #e0e7ff; }
        .petugas .isi { background: #f1f5f9; border-left: 3px solid #f59e0b; }
        .sistem { text-align: center; font-size: 9.5px; color: #64748b; font-style: italic; margin: 6px 0; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="kop"><tr>
        @if ($logo)<td style="width:54px"><img src="{{ $logo }}" style="width:48px;height:48px;"></td>@endif
        <td>
            <div class="nama">{{ $sekolah }}</div>
            @if ($alamat)<div class="kecil">{{ $alamat }}</div>@endif
            @if ($kontak)<div class="kecil">{{ $kontak }}</div>@endif
        </td>
    </tr></table>
    <div class="garis"></div>

    <h1>Transkrip Konsultasi Online</h1>
    <table class="info">
        <tr><td class="k">Nama</td><td>{{ $sesi->nama }}</td><td class="k">Topik</td><td>{{ $sesi->topik }}</td></tr>
        <tr><td class="k">Email</td><td>{{ $sesi->email }}</td><td class="k">Tanggal</td><td>{{ $sesi->created_at?->translatedFormat('d F Y, H:i') }} WIB</td></tr>
        <tr><td class="k">Petugas</td><td>{{ $sesi->agent?->name ?? '-' }}</td><td class="k">Status</td><td>{{ ucfirst($sesi->status) }}</td></tr>
        @if ($sesi->rating)<tr><td class="k">Penilaian</td><td colspan="3">{{ str_repeat('★', $sesi->rating) }}{{ str_repeat('☆', 5 - $sesi->rating) }} ({{ $sesi->rating }}/5){{ $sesi->rating_komentar ? ' — '.$sesi->rating_komentar : '' }}</td></tr>@endif
    </table>

    @foreach ($pesan as $m)
        @if ($m->sender === 'system')
            <div class="sistem">{{ $m->body }}</div>
        @else
            <div class="pesan {{ $m->sender === 'visitor' ? 'pengunjung' : 'petugas' }}">
                <div class="meta"><strong>{{ $m->sender === 'visitor' ? $sesi->nama : ($m->sender === 'bot' ? 'Asisten' : ($m->user?->name ?? 'Petugas')) }}</strong> · {{ $m->created_at?->format('d/m/Y H:i') }}</div>
                @if ($m->body !== '')<div class="isi">{{ $m->body }}</div>@endif
                @foreach ($m->attachments as $a)<div class="isi">[Lampiran: {{ $a->nama_asli }}]</div>@endforeach
            </div>
        @endif
    @endforeach

    <div class="footer">Dokumen dibuat otomatis oleh layanan Konsultasi Online {{ $sekolah }} pada {{ now()->translatedFormat('d F Y H:i') }} WIB</div>
</body>
</html>
