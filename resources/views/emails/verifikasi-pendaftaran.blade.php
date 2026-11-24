<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $produk }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.08);">
            <tr><td style="background:{{ $warna }};padding:20px 24px;">
                <div style="color:#ffffff;font-size:16px;font-weight:700;">{{ $produk }}</div>
                <div style="color:#f59e0b;font-size:12px;font-weight:600;">Aktivasi Sekolah</div>
            </td></tr>
            <tr><td style="height:4px;background:#f59e0b;font-size:0;line-height:0;">&nbsp;</td></tr>
            <tr><td style="padding:28px 24px;font-size:16px;line-height:1.7;">
                <p style="margin:0 0 12px;">Halo {{ $nama }},</p>
                <p style="margin:0 0 12px;">Terima kasih sudah mendaftarkan <strong>{{ $sekolah }}</strong>. Tekan tombol di bawah untuk mengaktifkan sekolah Anda.</p>
                <p style="margin:20px 0;"><a href="{{ $tautan }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:12px;font-size:16px;">Aktifkan Sekolah Saya</a></p>
                <p style="margin:0 0 12px;font-size:14px;color:#475569;">Tombol hanya berlaku {{ $jam }} jam dan hanya bisa dipakai satu kali. Jika tombol tidak bisa ditekan, salin alamat ini ke peramban:<br><span style="word-break:break-all;">{{ $tautan }}</span></p>
                <p style="margin:0;font-size:14px;color:#475569;">Kalau Anda tidak merasa mendaftar, abaikan saja email ini.</p>
            </td></tr>
            <tr><td style="padding:0 24px 24px;font-size:12px;color:#94a3b8;">Email ini dikirim otomatis. &copy; {{ date('Y') }} {{ $produk }}.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
