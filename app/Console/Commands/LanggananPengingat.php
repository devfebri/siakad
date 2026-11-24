<?php

namespace App\Console\Commands;

use App\Langganan;
use App\LogAktivitas;
use App\Mail\LanggananMail;
use App\Sekolah;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Pengingat langganan berakhir H-30, H-7, dan H-1 ke admin sekolah (email lewat antrian). Dijadwalkan harian.
 * Tiap tahap dikirim sekali per langganan; bila terlewat (mis. server mati), dikirim pada hari berikutnya yang masih sesuai.
 * Hanya langganan terkini sekolah yang diingatkan (yang sudah diperpanjang tidak), dan sekolah nonaktif/tanpa batas dilewati.
 */
class LanggananPengingat extends Command
{
    /** batas sisa hari => kolom penanda */
    public const TAHAP = [30 => 'pengingat_h30_at', 7 => 'pengingat_h7_at', 1 => 'pengingat_h1_at'];

    protected $signature = 'langganan:pengingat {--kering : Hanya tampilkan yang akan dikirim}';
    protected $description = 'Kirim pengingat langganan akan berakhir (H-30, H-7, H-1) ke admin sekolah';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');
        $terkirim = 0;

        foreach (Sekolah::where('status', 'aktif')->where('tanpa_batas', false)->get() as $sekolah) {
            $l = $sekolah->langgananTerkini();
            if (! $l) {
                continue;
            }

            $sisa = (int) today()->diffInDays($l->berakhir, false);
            if ($sisa < 0 || $sisa > 30) {
                continue;
            }

            // tahap paling dekat yang sudah masuk jendelanya dan belum dikirim; tahap yang lebih jauh dianggap terlewati
            $tahap = collect(array_keys(self::TAHAP))->sort()->first(fn (int $batas) => $sisa <= $batas && $l->{self::TAHAP[$batas]} === null);
            if ($tahap === null) {
                continue;
            }

            $email = User::withoutGlobalScopes()->where('sekolah_id', $sekolah->id)->denganPeran('admin')->whereNotNull('email')->pluck('email')->unique();
            $this->line(sprintf('%s: berakhir %s (sisa %d hari), tahap H-%d -> %d admin', $sekolah->nama, $l->berakhir->toDateString(), $sisa, $tahap, $email->count()));

            if (! $kering) {
                foreach ($email as $alamat) {
                    Mail::to($alamat)->queue(new LanggananMail($l, $sisa));
                }
                \App\Wa\Kabar::langganan($sekolah, $l, $sisa, $tahap); // WhatsApp ke nomor admin (bila sekolah menyalakannya)
                // tahap yang lebih jauh ikut ditandai agar tidak terkirim susulan di hari berikutnya
                foreach (self::TAHAP as $batas => $kolom) {
                    if ($batas >= $tahap && $l->{$kolom} === null) {
                        $l->{$kolom} = now();
                    }
                }
                $l->save();
                LogAktivitas::create(['sekolah_id' => $sekolah->id, 'aksi' => 'langganan.pengingat', 'deskripsi' => "Pengingat H-{$tahap} dikirim ke {$email->count()} admin {$sekolah->nama}."]);
            }
            $terkirim++;
        }

        $this->info(($kering ? '[simulasi] ' : '')."{$terkirim} sekolah diingatkan.");

        return self::SUCCESS;
    }
}
