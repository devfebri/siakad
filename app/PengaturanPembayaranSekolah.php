<?php

namespace App;

use App\Pembayaran\Driver\FakeGateway;
use App\Pembayaran\Driver\MidtransGateway;
use App\Pembayaran\Driver\XenditGateway;
use App\Pembayaran\PaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Pengaturan pembayaran online SPP milik satu sekolah. Kunci terenkripsi (cast) dan disembunyikan dari JSON;
 * dana masuk langsung ke akun gerbang milik sekolah, bukan ke platform.
 */
class PengaturanPembayaranSekolah extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const DRIVER = ['nonaktif' => 'Nonaktif (hanya bayar di sekolah)', 'midtrans' => 'Midtrans', 'xendit' => 'Xendit', 'fake' => 'Simulasi (hanya lokal/uji)'];
    public const DITANGGUNG = ['sekolah' => 'Ditanggung sekolah', 'ortu' => 'Dibebankan ke orang tua'];

    protected $table = 'pengaturan_pembayaran_sekolah';
    protected $fillable = ['driver', 'sandbox', 'midtrans_server_key', 'xendit_secret_key', 'xendit_callback_token', 'biaya_admin_ditanggung', 'biaya_admin_tetap', 'biaya_admin_persen'];
    protected $hidden = ['midtrans_server_key', 'xendit_secret_key', 'xendit_callback_token', 'webhook_token'];
    protected $casts = [
        'sandbox' => 'boolean', 'biaya_admin_tetap' => 'integer', 'biaya_admin_persen' => 'float',
        'midtrans_server_key' => 'encrypted', 'xendit_secret_key' => 'encrypted', 'xendit_callback_token' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->webhook_token ??= Str::random(40);
        });
    }

    /** Pengaturan sekolah aktif (dibuat bila belum ada). */
    public static function milikSekolahAktif(): self
    {
        return static::first() ?? static::create(['driver' => 'nonaktif']);
    }

    /** Gerbang untuk driver tertentu dengan kunci sekolah ini, atau null bila kuncinya belum lengkap. */
    public function gerbang(?string $driver = null): ?PaymentGateway
    {
        return match ($driver ?? $this->driver) {
            'midtrans' => $this->midtrans_server_key ? new MidtransGateway($this->midtrans_server_key, $this->sandbox) : null,
            'xendit' => $this->xendit_secret_key ? new XenditGateway($this->xendit_secret_key, (string) $this->xendit_callback_token) : null,
            'fake' => app()->isProduction() ? null : new FakeGateway('pembayaran.simulasi-spp'),
            default => null,
        };
    }

    /** Gerbang yang dipakai membuat tagihan baru; null = tombol bayar online tidak tampil. */
    public function gerbangAktif(): ?PaymentGateway
    {
        return $this->gerbang($this->driver);
    }

    /** Biaya admin gerbang untuk jumlah tagihan tertentu. */
    public function biayaAdmin(int $subtotal): int
    {
        return (int) $this->biaya_admin_tetap + (int) ceil($subtotal * ((float) $this->biaya_admin_persen) / 100);
    }

    /** Alamat callback yang diisi di dashboard gerbang milik sekolah. */
    public function urlCallback(string $driver): string
    {
        return url("/api/webhook/spp/{$this->webhook_token}/{$driver}");
    }

    /** Tampilan aman sebuah kunci: hanya 4 karakter terakhir. */
    public function tersembunyi(string $kolom): string
    {
        $nilai = $this->{$kolom};

        return $nilai ? str_repeat('•', 10).substr($nilai, -4) : '';
    }
}
