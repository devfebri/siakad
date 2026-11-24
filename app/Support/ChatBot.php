<?php

namespace App\Support;

use App\ChatFaq;
use App\ChatMessage;
use App\ChatSession;
use App\ChatSetting;

/**
 * Chatbot FAQ sederhana (tanpa AI): mencocokkan kata kunci pesan dengan tabel chat_faqs yang dikelola admin.
 * Aktif hanya untuk chat yang belum dilayani petugas, saat di luar jam layanan atau tidak ada petugas online.
 * Pertanyaan keagamaan/fatwa tidak dijawab; pengunjung diarahkan ke halaman kontak. Pertanyaan tanpa jawaban
 * diteruskan ke petugas (dibalas lewat email).
 */
class ChatBot
{
    public const MAKS_TOMBOL = 5;
    public const KATA_ALIHKAN = 'fatwa, hukum islam, hukumnya, halal atau haram, halal haram, haram, syariat, hukum agama, boleh tidak dalam islam';

    public static function aktif(ChatSession $s): bool
    {
        if (! (bool) ChatSetting::get('bot_aktif', '1') || $s->status !== 'menunggu') {
            return false;
        }

        return ! ChatHours::buka() || ChatQueue::onlineAgents()->isEmpty();
    }

    /** Pesan pembuka: sapaan, info jam layanan, dan tombol pilihan cepat. */
    public static function sapa(ChatSession $s): ChatMessage
    {
        $sapaan = trim((string) ChatSetting::get('bot_sapaan', 'Halo'));
        $keadaan = ChatHours::buka()
            ? 'Saat ini belum ada petugas yang online.'
            : 'Saat ini di luar jam layanan petugas.';

        return $s->addMessage('bot',
            "{$sapaan}, {$s->nama}! {$keadaan}\nJam layanan: ".ChatHours::label()."\n\nPilih topik di bawah ini atau tulis pertanyaan Anda. Pertanyaan yang belum terjawab akan kami teruskan ke petugas.",
            null, ['buttons' => self::tombol()],
        );
    }

    /**
     * Tanggapi pesan pengunjung (sudah tersimpan). $pilihan = FAQ yang dipilih lewat tombol.
     *
     * @return array{pesan: array<int, ChatMessage>, diteruskan: bool}
     */
    public static function tangani(ChatSession $s, string $teks, ?ChatFaq $pilihan = null): array
    {
        $balas = fn (string $isi, bool $tombol = true) => $s->addMessage('bot', $isi, null, $tombol ? ['buttons' => self::tombol()] : null);

        if (! $pilihan && self::dialihkan($teks)) {
            return ['pesan' => [$balas((string) ChatSetting::get('bot_pesan_alihkan', self::pesanAlihkan()))], 'diteruskan' => false];
        }

        $faq = $pilihan ?? self::cocok($teks);
        if ($faq) {
            return ['pesan' => [$balas(self::isi($faq->jawaban)."\n\nAda lagi yang bisa kami bantu?")], 'diteruskan' => false];
        }

        if (self::sapaanSaja($teks)) {
            return ['pesan' => [$balas('Silakan pilih topik di bawah ini atau tulis pertanyaan Anda.')], 'diteruskan' => false];
        }

        $sudah = $s->balas_email;
        $s->update(['balas_email' => true]);

        return [
            'pesan' => [$balas($sudah
                ? 'Pesan tambahan Anda sudah kami catat dan diteruskan ke petugas.'
                : "Pertanyaan Anda belum ada di daftar jawaban otomatis kami, jadi akan kami teruskan ke petugas. Balasan akan dikirim ke email {$s->email}.", $sudah)],
            'diteruskan' => ! $sudah,
        ];
    }

    /** FAQ aktif yang paling cocok dengan teks (skor = jumlah kata kunci yang muncul), null bila tidak ada. */
    public static function cocok(string $teks): ?ChatFaq
    {
        $t = ' '.self::normalisasi($teks).' ';
        $terbaik = null;
        $skorTerbaik = 0;

        foreach (ChatFaq::where('aktif', true)->orderBy('urutan')->orderBy('id')->get() as $faq) {
            $skor = 0;
            foreach (explode(',', $faq->kata_kunci) as $kunci) {
                $kunci = self::normalisasi($kunci);
                if ($kunci === '') {
                    continue;
                }
                // kata kunci pendek harus cocok utuh; yang panjang cocok sebagai potongan (mendaftar ~ daftar)
                $cocok = mb_strlen($kunci) >= 4 ? str_contains($t, $kunci) : str_contains($t, ' '.$kunci.' ');
                $skor += $cocok ? 1 : 0;
            }

            if ($skor > $skorTerbaik) {
                $terbaik = $faq;
                $skorTerbaik = $skor;
            }
        }

        return $terbaik;
    }

    /** Pertanyaan keagamaan yang tidak boleh dijawab bot. */
    public static function dialihkan(string $teks): bool
    {
        $t = ' '.self::normalisasi($teks).' ';
        foreach (explode(',', (string) ChatSetting::get('bot_kata_alihkan', self::KATA_ALIHKAN)) as $kata) {
            $kata = self::normalisasi($kata);
            if ($kata !== '' && str_contains($t, ' '.$kata.' ')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{id: int, label: string}> */
    public static function tombol(): array
    {
        return ChatFaq::where('aktif', true)->orderBy('urutan')->orderBy('id')->limit(self::MAKS_TOMBOL)->get(['id', 'pertanyaan'])
            ->map(fn (ChatFaq $f) => ['id' => $f->id, 'label' => $f->pertanyaan])->all();
    }

    /** Ganti penanda {nama_sekolah}, {alamat}, dst. dengan data sekolah saat ini. */
    public static function isi(string $teks): string
    {
        $peta = [
            '{nama_sekolah}' => config('school.name'),
            '{alamat}' => config('school.alamat') ?: '-',
            '{telepon}' => config('school.telepon') ?: '-',
            '{email}' => config('school.email') ?: '-',
            '{whatsapp}' => config('school.whatsapp') ?: '-',
            '{jam_layanan}' => ChatHours::label(),
            '{ppdb_url}' => route('ppdb'),
            '{agenda_url}' => route('agenda'),
            '{kontak_url}' => route('kontak'),
        ];

        return strtr($teks, $peta);
    }

    public static function pesanAlihkan(): string
    {
        return 'Mohon maaf, asisten otomatis tidak dapat menjawab pertanyaan keagamaan/hukum agama. Silakan tanyakan kepada guru agama atau tokoh agama terpercaya, atau hubungi kami melalui halaman Kontak: '.route('kontak');
    }

    private static function sapaanSaja(string $teks): bool
    {
        return (bool) preg_match('/^(halo|hai|hi|hallo|assalamu ?alaikum|assalamualaikum|selamat (pagi|siang|sore|malam)|permisi|tes|test|p)$/u', self::normalisasi($teks));
    }

    private static function normalisasi(string $teks): string
    {
        $teks = mb_strtolower($teks);
        $teks = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $teks);

        return trim(preg_replace('/\s+/u', ' ', $teks));
    }
}
