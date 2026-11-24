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
            {{-- Kop --}}
            <tr><td style="background:{{ $warna }};padding:20px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                    <td style="padding-right:12px;">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="" width="44" height="44" style="display:block;border-radius:10px;background:#fff;object-fit:contain;">
                        @else
                            <div style="width:44px;height:44px;border-radius:10px;background:#f59e0b;text-align:center;line-height:44px;font-size:22px;">🎓</div>
                        @endif
                    </td>
                    <td>
                        <div style="color:#ffffff;font-size:16px;font-weight:700;line-height:1.2;">{{ $sekolah }}</div>
                        <div style="color:#f59e0b;font-size:12px;font-weight:600;">Konsultasi Online</div>
                    </td>
                </tr></table>
            </td></tr>
            <tr><td style="height:4px;background:#f59e0b;font-size:0;line-height:0;">&nbsp;</td></tr>

            {{-- Isi --}}
            <tr><td style="padding:28px 24px 8px;font-size:15px;line-height:1.6;">
                @if ($jenis === 'dimulai')
                    <p style="margin:0 0 12px;">Halo <strong>{{ $sesi->nama }}</strong>,</p>
                    <p style="margin:0 0 12px;">Terima kasih telah menghubungi {{ $sekolah }}. Percakapan Anda tentang <strong>{{ $sesi->topik }}</strong> sudah kami terima dan masuk antrian.</p>
                    <p style="margin:0;">Petugas akan membalas secepatnya. Jika Anda menutup halaman, balasan petugas akan kami kirim ke email ini.</p>
                    <p style="margin:16px 0 0;"><a href="{{ $riwayat }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Lihat Percakapan Saya</a></p>
                @elseif ($jenis === 'balasan')
                    <p style="margin:0 0 12px;">Halo <strong>{{ $sesi->nama }}</strong>,</p>
                    <p style="margin:0 0 12px;">Petugas {{ $sekolah }}{{ !empty($data['petugas']) ? ' ('.$data['petugas'].')' : '' }} membalas pesan Anda:</p>
                    <div style="margin:0 0 12px;padding:14px 16px;background:#f1f5f9;border-left:4px solid #f59e0b;border-radius:8px;white-space:pre-wrap;">{{ $data['isi'] ?? '' }}</div>
                    <p style="margin:0;">Buka kembali halaman website kami untuk melanjutkan percakapan, atau lihat riwayatnya di bawah.</p>
                    <p style="margin:16px 0 0;"><a href="{{ $riwayat }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Lihat Percakapan Saya</a></p>
                @elseif ($jenis === 'diteruskan')
                    <p style="margin:0 0 12px;">Halo <strong>{{ $sesi->nama }}</strong>,</p>
                    <p style="margin:0 0 12px;">Pertanyaan Anda belum ada di daftar jawaban otomatis, sehingga kami teruskan ke petugas {{ $sekolah }}:</p>
                    <div style="margin:0 0 12px;padding:14px 16px;background:#f1f5f9;border-left:4px solid #f59e0b;border-radius:8px;white-space:pre-wrap;">{{ $data['isi'] ?? '' }}</div>
                    <p style="margin:0;">Petugas akan membalas ke email ini pada jam layanan berikutnya.</p>
                    <p style="margin:16px 0 0;"><a href="{{ $riwayat }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Lihat Percakapan Saya</a></p>
                @elseif ($jenis === 'transkrip')
                    <p style="margin:0 0 12px;">Halo <strong>{{ $sesi->nama }}</strong>,</p>
                    <p style="margin:0 0 16px;">Percakapan Anda tentang <strong>{{ $sesi->topik }}</strong> telah selesai. Berikut ringkasannya:</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                        @foreach ($pesan as $m)
                            <tr><td style="padding:0 0 10px;">
                                <div style="font-size:12px;color:#64748b;">{{ $m->created_at?->format('d M Y H:i') }} · <strong style="color:#0f172a;">{{ $m->sender === 'visitor' ? $sesi->nama : ($m->sender === 'bot' ? 'Asisten' : ($m->user?->name ?? 'Petugas')) }}</strong></div>
                                <div style="white-space:pre-wrap;">{{ $m->body !== '' ? $m->body : '[lampiran]' }}</div>
                            </td></tr>
                        @endforeach
                    </table>
                    <p style="margin:8px 0 0;">Terima kasih telah menggunakan layanan Konsultasi Online kami.</p>
                    <p style="margin:16px 0 0;"><a href="{{ $riwayat }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Unduh PDF &amp; Beri Penilaian</a></p>
                @else
                    <p style="margin:0 0 12px;">Ada pesan masuk di Konsultasi Online saat tidak ada petugas yang online.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:0 0 16px;">
                        <tr><td style="padding:2px 12px 2px 0;color:#64748b;">Nama</td><td>{{ $sesi->nama }}</td></tr>
                        <tr><td style="padding:2px 12px 2px 0;color:#64748b;">Email</td><td>{{ $sesi->email }}</td></tr>
                        @if ($sesi->whatsapp)<tr><td style="padding:2px 12px 2px 0;color:#64748b;">WhatsApp</td><td>{{ $sesi->whatsapp }}</td></tr>@endif
                        <tr><td style="padding:2px 12px 2px 0;color:#64748b;">Topik</td><td>{{ $sesi->topik }}</td></tr>
                    </table>
                    @if (!empty($data['isi']))
                        <div style="margin:0 0 16px;padding:14px 16px;background:#f1f5f9;border-left:4px solid #f59e0b;border-radius:8px;white-space:pre-wrap;">{{ $data['isi'] }}</div>
                    @endif
                    <a href="{{ $tautanAdmin }}" style="display:inline-block;background:#f59e0b;color:#0f172a;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:10px;">Buka Panel Konsultasi</a>
                @endif
            </td></tr>

            <tr><td style="padding:20px 24px 24px;font-size:12px;color:#94a3b8;">
                &copy; {{ date('Y') }} {{ $sekolah }}. Email ini dikirim otomatis oleh layanan Konsultasi Online.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
