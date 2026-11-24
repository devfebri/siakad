<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Paket langganan (Basic/Pro/Premium dst): harga per durasi dan modul yang termasuk. */
class Paket extends Model
{
    public const DURASI = [1, 6, 12];

    protected $table = 'paket';
    protected $fillable = ['kode', 'nama', 'deskripsi', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    public function harga()
    {
        return $this->hasMany(PaketHarga::class);
    }

    public function modul()
    {
        return $this->belongsToMany(Modul::class, 'paket_modul');
    }

    /** Harga untuk durasi tertentu (null bila belum diatur). */
    public function hargaUntuk(int $durasiBulan): ?int
    {
        $baris = $this->relationLoaded('harga') ? $this->harga->firstWhere('durasi_bulan', $durasiBulan) : $this->harga()->where('durasi_bulan', $durasiBulan)->first();

        return $baris?->harga;
    }

    /** @return array<int, string> kode modul paket ini */
    public function kodeModul(): array
    {
        return $this->modul->pluck('kode')->all();
    }
}
