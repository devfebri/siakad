<?php

namespace App\Pembayaran;

use App\CallbackGateway;
use App\Invoice;
use App\LogAktivitas;
use App\PembayaranGateway;
use App\Support\Penyewaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembayaran SEWA invoice lewat gerbang: membuat tagihan online dan memproses callback.
 * Callback aman diulang: lunasnya invoice (dan perpanjangan langganan) terjadi paling banyak sekali,
 * karena invoice dikunci dan hanya invoice berstatus "menunggu" yang dilunasi lewat aturan Penyewaan::konfirmasiLunas.
 */
class Sewa
{
    /** Mulai atau lanjutkan pembayaran online invoice. Tagihan yang masih berlaku dipakai ulang. @throws GerbangException */
    public static function mulai(Invoice $invoice, string $namaPelanggan, ?string $emailPelanggan): PembayaranGateway
    {
        if ($invoice->status !== 'menunggu') {
            throw new GerbangException('Invoice ini sudah '.strtolower(Invoice::STATUS[$invoice->status] ?? $invoice->status).'.');
        }

        $gerbang = Gerbang::aktif();
        if (! $gerbang) {
            throw new GerbangException('Pembayaran online belum tersedia. Silakan bayar lewat transfer.');
        }

        $ada = PembayaranGateway::where('invoice_id', $invoice->id)->where('driver', $gerbang->kode())->where('status', 'menunggu')->latest('id')->first();
        if ($ada && $ada->masihBerlaku()) {
            return $ada;
        }

        $orderId = $invoice->nomor.'-'.Str::upper(Str::random(5));
        $hasil = $gerbang->buatTagihan(new PermintaanBayar(
            $orderId, (int) $invoice->jumlah, "Sewa {$invoice->paket?->nama} {$invoice->durasi_bulan} bulan ({$invoice->nomor})", $namaPelanggan, $emailPelanggan,
            $invoice->sekolah->url('/admin/langganan?bayar=kembali'), (int) config('pembayaran.kedaluwarsa_menit'),
        ));

        return PembayaranGateway::create([
            'invoice_id' => $invoice->id, 'driver' => $gerbang->kode(), 'order_id' => $orderId, 'jumlah' => $invoice->jumlah, 'status' => 'menunggu',
            'url_bayar' => $hasil->url, 'referensi' => $hasil->referensi, 'kedaluwarsa_pada' => $hasil->kedaluwarsa,
        ]);
    }

    /**
     * Terima callback dari gerbang `$kode`. Mengembalikan [kode HTTP, pesan]. Callback tanpa tanda tangan sah ditolak (401) dan dicatat.
     *
     * @return array{0: int, 1: string}
     */
    public static function terima(string $kode, Request $request): array
    {
        $gerbang = Gerbang::driver($kode);
        if (! $gerbang) {
            self::catat($kode, 'tak-dikenal|'.Str::random(8), null, null, false, 'ditolak', [], $request);

            return [404, 'Gerbang tidak dikonfigurasi.'];
        }

        $notif = $gerbang->terimaCallback($request);
        if (! $notif) {
            self::catat($kode, 'tak-sah|'.Str::random(8), null, null, false, 'ditolak', ['catatan' => 'tanda tangan atau isi tidak sah'], $request);

            return [401, 'Tanda tangan tidak sah.'];
        }

        if (CallbackGateway::where('driver', $kode)->where('kunci', $notif->kunci)->where('valid', true)->whereIn('hasil', ['diproses', 'diabaikan'])->exists()) {
            return [200, 'Sudah diterima sebelumnya.'];
        }

        $hasil = self::proses($notif);
        self::catat($kode, $notif->kunci, $notif->orderId, $notif->status, true, $hasil, $notif->mentah, $request);

        return [200, 'OK'];
    }

    /** @return string diproses | diabaikan */
    public static function proses(NotifikasiBayar $n): string
    {
        return DB::transaction(function () use ($n) {
            $bayar = PembayaranGateway::where('order_id', $n->orderId)->lockForUpdate()->first();
            if (! $bayar || $bayar->driver !== $n->driver) {
                return 'diabaikan'; // order tidak dikenal
            }

            if ($n->status !== 'berhasil') {
                if ($bayar->status === 'menunggu' && in_array($n->status, ['gagal', 'kedaluwarsa'], true)) {
                    $bayar->update(['status' => $n->status]);
                }

                return 'diproses';
            }

            if ($n->jumlah !== (int) $bayar->jumlah) {
                $bayar->update(['catatan' => "Jumlah callback {$n->jumlah} berbeda dari tagihan {$bayar->jumlah}; tidak dilunasi otomatis."]);
                LogAktivitas::catat('pembayaran.jumlah_beda', "Callback {$n->driver} untuk {$bayar->order_id}: jumlah {$n->jumlah}, tagihan {$bayar->jumlah}. Cek manual.", $bayar->invoice?->sekolah_id);

                return 'diabaikan';
            }

            if ($bayar->status === 'berhasil') {
                return 'diproses'; // sudah pernah dilunasi: callback ganda tidak menambah masa aktif lagi
            }

            $invoice = Invoice::whereKey($bayar->invoice_id)->lockForUpdate()->firstOrFail();
            $bayar->update(['status' => 'berhasil', 'metode' => $n->metode, 'dibayar_pada' => now()]);

            if ($invoice->status === 'menunggu') {
                Penyewaan::konfirmasiLunas($invoice, ucfirst($n->driver).($n->metode ? ' · '.$n->metode : ''), "Pembayaran online {$bayar->order_id}");
            } else {
                // Sudah dilunasi manual atau dibatalkan sebelum uang masuk: jangan perpanjang lagi, tandai untuk dicek pengelola.
                $bayar->update(['catatan' => 'Invoice sudah '.strtolower(Invoice::STATUS[$invoice->status] ?? $invoice->status).' saat pembayaran masuk. Periksa kemungkinan kelebihan bayar.']);
                LogAktivitas::catat('pembayaran.kelebihan', "Pembayaran online {$bayar->order_id} masuk untuk invoice {$invoice->nomor} yang sudah {$invoice->status}. Cek pengembalian dana.", $invoice->sekolah_id);
            }

            return 'diproses';
        });
    }

    private static function catat(string $driver, string $kunci, ?string $orderId, ?string $status, bool $valid, string $hasil, array $payload, Request $request): void
    {
        CallbackGateway::create([
            'driver' => $driver, 'kunci' => mb_substr($kunci, 0, 150), 'order_id' => $orderId, 'status' => $status, 'valid' => $valid, 'hasil' => $hasil,
            'payload' => mb_substr((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000), 'ip' => $request->ip(),
        ]);
    }
}
