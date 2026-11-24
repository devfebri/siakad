<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sekolah }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.08);">
            <tr><td style="background:{{ $warna }};padding:20px 24px;">
                <div style="color:#ffffff;font-size:16px;font-weight:700;">{{ $sekolah }}</div>
                <div style="color:#f59e0b;font-size:12px;font-weight:600;">Pembayaran SPP Online</div>
            </td></tr>
            <tr><td style="height:4px;background:#f59e0b;font-size:0;line-height:0;">&nbsp;</td></tr>
            <tr><td style="padding:28px 24px;font-size:15px;line-height:1.6;">
                <p style="margin:0 0 12px;">Ada pembayaran SPP online yang baru masuk sebesar <strong>Rp {{ number_format($transaksi->subtotal, 0, ',', '.') }}</strong>.</p>
                <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                    @foreach ($pembayaran as $p)
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td>{{ $p->tagihan?->siswa?->nama_siswa }}<br><span style="color:#64748b;font-size:12px;">{{ $p->tagihan?->nama }} · {{ $p->nomor }}</span></td>
                            <td align="right" style="white-space:nowrap;">Rp {{ number_format($p->jumlah, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </table>
                @if ($transaksi->biaya_admin > 0)
                    <p style="margin:12px 0 0;font-size:13px;color:#475569;">Biaya admin gerbang pembayaran Rp {{ number_format($transaksi->biaya_admin, 0, ',', '.') }} {{ $transaksi->biaya_ditanggung === 'ortu' ? 'dibayar orang tua (total dibayar Rp '.number_format($transaksi->total, 0, ',', '.').')' : 'ditanggung sekolah' }}.</p>
                @endif
                <p style="margin:12px 0 0;font-size:13px;color:#475569;">Cara bayar: {{ $transaksi->metode ?: '-' }} · Nomor transaksi {{ $transaksi->order_id }}</p>
                @if ($tautan)
                    <p style="margin:20px 0 0;"><a href="{{ $tautan }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Lihat Pembayaran</a></p>
                @endif
            </td></tr>
            <tr><td style="padding:0 24px 24px;font-size:12px;color:#94a3b8;">Email ini dikirim otomatis. &copy; {{ date('Y') }} {{ $sekolah }}.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
