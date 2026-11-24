<?php

namespace App\Pembayaran;

use App\CallbackGateway;
use App\Events\PembayaranSppDiterima;
use App\LogAktivitas;
use App\PengaturanPembayaranSekolah;
use App\Support\Keuangan;
use App\Support\SekolahAktif;
use App\Tagihan;
use App\TransaksiSpp;
use App\TransaksiSppItem;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pembayaran SPP online oleh orang tua dengan kunci gerbang MILIK SEKOLAH (dana langsung ke rekening sekolah).
 * Satu transaksi dapat membayar beberapa tagihan. Callback aman diulang: tiap tagihan dibayar paling banyak sekali per transaksi,
 * dan jumlah yang dibayar tidak pernah melebihi sisa tagihan (Keuangan::bayar).
 */
class SppOnline
{
    /**
     * Hitung jumlah yang harus dibayar.
     *
     * @return array{subtotal: int, biaya_admin: int, total: int, ditanggung: string}
     */
    public static function hitung(PengaturanPembayaranSekolah $pg, int $subtotal): array
    {
        $biaya = $pg->biayaAdmin($subtotal);

        return [
            'subtotal' => $subtotal, 'biaya_admin' => $biaya, 'ditanggung' => $pg->biaya_admin_ditanggung,
            'total' => $subtotal + ($pg->biaya_admin_ditanggung === 'ortu' ? $biaya : 0),
        ];
    }

    /**
     * Mulai pembayaran online beberapa tagihan anak-anak orang tua ini. Tagihan yang sama yang masih menunggu dipakai ulang.
     *
     * @param array<int, int> $tagihanIds
     * @throws GerbangException|ValidationException
     */
    public static function mulai(User $ortu, array $tagihanIds): TransaksiSpp
    {
        $pg = PengaturanPembayaranSekolah::first();
        $gerbang = $pg?->gerbangAktif();
        if (! $pg || ! $gerbang) {
            throw new GerbangException('Pembayaran online belum tersedia di sekolah ini. Silakan bayar langsung ke sekolah.');
        }

        $ids = array_values(array_unique(array_map('intval', $tagihanIds)));
        $anakIds = $ortu->orangTua?->anak()->pluck('siswa.id')->all() ?? [];
        $tagihan = Tagihan::whereIn('id', $ids)->whereIn('siswa_id', $anakIds)->belumLunas()->get();
        if (! $ids || $tagihan->count() !== count($ids) || $tagihan->contains(fn (Tagihan $t) => $t->sisa < 1)) {
            throw ValidationException::withMessages(['tagihan_id' => 'Tagihan yang dipilih tidak ditemukan atau sudah lunas. Muat ulang halaman lalu coba lagi.']);
        }

        // tagihan yang sama (persis) yang masih menunggu: lanjutkan, jangan buat transaksi baru
        $urut = collect($ids)->sort()->values()->all();
        $ada = TransaksiSpp::where('user_id', $ortu->id)->where('status', 'menunggu')->with('item')->latest('id')->get()
            ->first(fn (TransaksiSpp $t) => $t->masihBerlaku() && $t->item->pluck('tagihan_id')->sort()->values()->all() === $urut && $t->driver === $gerbang->kode());
        if ($ada) {
            return $ada;
        }

        $jumlah = self::hitung($pg, (int) $tagihan->sum('sisa'));
        $orderId = 'SPP'.$pg->sekolah_id.'-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        $email = filter_var($ortu->email, FILTER_VALIDATE_EMAIL) && ! str_ends_with($ortu->email, '.invalid') ? $ortu->email : null;

        $hasil = $gerbang->buatTagihan(new PermintaanBayar(
            $orderId, $jumlah['total'], 'SPP '.config('school.short_name').' ('.$tagihan->count().' tagihan)', $ortu->name, $email,
            url('/ortu/tagihan?bayar=kembali'), (int) config('pembayaran.kedaluwarsa_menit'),
        ));

        return DB::transaction(function () use ($ortu, $gerbang, $orderId, $jumlah, $hasil, $tagihan) {
            $t = TransaksiSpp::create([
                'user_id' => $ortu->id, 'driver' => $gerbang->kode(), 'order_id' => $orderId, 'subtotal' => $jumlah['subtotal'], 'biaya_admin' => $jumlah['biaya_admin'],
                'biaya_ditanggung' => $jumlah['ditanggung'], 'total' => $jumlah['total'], 'status' => 'menunggu', 'url_bayar' => $hasil->url,
                'referensi' => $hasil->referensi, 'kedaluwarsa_pada' => $hasil->kedaluwarsa,
            ]);
            foreach ($tagihan as $tg) {
                TransaksiSppItem::create(['transaksi_spp_id' => $t->id, 'tagihan_id' => $tg->id, 'jumlah' => $tg->sisa]);
            }

            return $t;
        });
    }

    /**
     * Terima callback gerbang milik sekolah `$pg`. Mengembalikan [kode HTTP, pesan].
     *
     * @return array{0: int, 1: string}
     */
    public static function terima(PengaturanPembayaranSekolah $pg, string $driver, Request $request): array
    {
        $gerbang = $pg->gerbang($driver);
        if (! $gerbang) {
            self::catat($pg, $driver, 'tak-dikenal|'.Str::random(8), null, null, false, 'ditolak', [], $request);

            return [404, 'Gerbang tidak dikonfigurasi untuk sekolah ini.'];
        }

        $notif = $gerbang->terimaCallback($request);
        if (! $notif) {
            self::catat($pg, $driver, 'tak-sah|'.Str::random(8), null, null, false, 'ditolak', ['catatan' => 'tanda tangan atau isi tidak sah'], $request);

            return [401, 'Tanda tangan tidak sah.'];
        }

        if (CallbackGateway::where('sekolah_id', $pg->sekolah_id)->where('driver', $driver)->where('kunci', $notif->kunci)->where('valid', true)->whereIn('hasil', ['diproses', 'diabaikan'])->exists()) {
            return [200, 'Sudah diterima sebelumnya.'];
        }

        [$hasil, $kejadian] = self::proses($pg->sekolah_id, $notif);
        self::catat($pg, $driver, $notif->kunci, $notif->orderId, $notif->status, true, $hasil, $notif->mentah, $request);

        if ($kejadian) {
            event($kejadian);
        }

        return [200, 'OK'];
    }

    /** @return array{0: string, 1: ?PembayaranSppDiterima} hasil (diproses|diabaikan) dan kejadian yang harus disiarkan setelah selesai */
    public static function proses(int $sekolahId, NotifikasiBayar $n): array
    {
        return SekolahAktif::jalankan($sekolahId, function () use ($sekolahId, $n) {
            return DB::transaction(function () use ($sekolahId, $n) {
                $t = TransaksiSpp::where('order_id', $n->orderId)->where('driver', $n->driver)->lockForUpdate()->first(); // terbatas pada sekolah aktif
                if (! $t) {
                    return ['diabaikan', null];
                }

                if ($n->status !== 'berhasil') {
                    if ($t->status === 'menunggu' && in_array($n->status, ['gagal', 'kedaluwarsa'], true)) {
                        $t->update(['status' => $n->status]);
                    }

                    return ['diproses', null];
                }

                if ($n->jumlah !== (int) $t->total) {
                    $t->update(['catatan' => "Jumlah callback {$n->jumlah} berbeda dari tagihan {$t->total}; tidak dicatat otomatis."]);
                    LogAktivitas::catat('spp_online.jumlah_beda', "Callback {$n->driver} untuk {$t->order_id}: jumlah {$n->jumlah}, seharusnya {$t->total}. Cek manual.", $sekolahId);

                    return ['diabaikan', null];
                }

                if ($t->status === 'berhasil') {
                    return ['diproses', null]; // callback ganda
                }

                $pembayaranIds = [];
                $dilewati = [];
                foreach ($t->item()->get() as $item) {
                    $tagihan = Tagihan::whereKey($item->tagihan_id)->lockForUpdate()->first();
                    if (! $tagihan || ! in_array($tagihan->status, ['belum', 'sebagian'], true) || $tagihan->sisa < 1) {
                        $dilewati[] = $item->tagihan_id; // sudah dilunasi lewat jalur lain sebelum uang ini masuk

                        continue;
                    }
                    $jumlah = min((int) $item->jumlah, $tagihan->sisa);
                    $pembayaranIds[] = Keuangan::bayar($tagihan, $jumlah, 'online', today()->toDateString(), 'Pembayaran online '.$t->order_id, $n->metode, $t->order_id)->id;
                }

                $t->update([
                    'status' => 'berhasil', 'metode' => $n->metode, 'dibayar_pada' => now(),
                    'catatan' => $dilewati ? 'Sebagian tagihan sudah lunas sebelum pembayaran masuk (id '.implode(', ', $dilewati).'). Periksa kemungkinan kelebihan bayar.' : null,
                ]);
                if ($dilewati) {
                    LogAktivitas::catat('spp_online.kelebihan', "Pembayaran online {$t->order_id}: tagihan ".implode(', ', $dilewati).' sudah lunas sebelumnya. Cek pengembalian dana.', $sekolahId);
                }

                return ['diproses', $pembayaranIds ? new PembayaranSppDiterima($t->fresh(), $pembayaranIds) : null];
            });
        });
    }

    private static function catat(PengaturanPembayaranSekolah $pg, string $driver, string $kunci, ?string $orderId, ?string $status, bool $valid, string $hasil, array $payload, Request $request): void
    {
        CallbackGateway::create([
            'sekolah_id' => $pg->sekolah_id, 'driver' => $driver, 'kunci' => mb_substr($kunci, 0, 150), 'order_id' => $orderId, 'status' => $status, 'valid' => $valid, 'hasil' => $hasil,
            'payload' => mb_substr((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000), 'ip' => $request->ip(),
        ]);
    }
}
