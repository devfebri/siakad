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
                <div style="color:#f59e0b;font-size:12px;font-weight:600;">Pengingat Langganan</div>
            </td></tr>
            <tr><td style="height:4px;background:#f59e0b;font-size:0;line-height:0;">&nbsp;</td></tr>
            <tr><td style="padding:28px 24px;font-size:15px;line-height:1.6;">
                <p style="margin:0 0 12px;">Yth. Admin {{ $sekolah }},</p>
                <p style="margin:0 0 12px;">Langganan <strong>paket {{ $paket ?? '-' }}</strong> sekolah Anda akan berakhir pada <strong>{{ $berakhir->translatedFormat('d F Y') }}</strong>
                    ({{ $sisaHari <= 0 ? 'hari ini' : ($sisaHari === 1 ? 'besok' : $sisaHari.' hari lagi') }}).</p>
                <p style="margin:0 0 16px;">Setelah tanggal tersebut ada masa tenggang {{ $tenggangHari }} hari. Lewat masa tenggang, situs berubah menjadi mode baca-saja: data masih bisa dilihat tetapi perubahan ditolak sampai langganan diperpanjang.</p>
                @if ($tautan)
                    <a href="{{ $tautan }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Perpanjang Langganan</a>
                @endif
            </td></tr>
            <tr><td style="padding:0 24px 24px;font-size:12px;color:#94a3b8;">Email ini dikirim otomatis. &copy; {{ date('Y') }} {{ $sekolah }}.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
