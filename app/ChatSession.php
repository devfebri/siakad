<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Satu percakapan konsultasi online antara pengunjung dan petugas. */
class ChatSession extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const TOPIK = [
        'Informasi Umum',
        'PPDB (Penerimaan Siswa Baru)',
        'Akademik & Jadwal',
        'Administrasi & Surat',
        'Saran & Pengaduan',
        'Lainnya',
    ];

    protected $fillable = [
        'token', 'riwayat_token', 'nama', 'email', 'whatsapp', 'topik', 'status', 'agent_id', 'persetujuan', 'balas_email', 'visitor_typing_at', 'agent_typing_at',
        'last_visitor_at', 'taken_at', 'closed_at', 'email_balasan_at', 'email_admin_at', 'rating', 'rating_komentar', 'ip',
    ];

    protected $casts = [
        'persetujuan' => 'boolean', 'balas_email' => 'boolean', 'rating' => 'integer', 'visitor_typing_at' => 'datetime', 'agent_typing_at' => 'datetime',
        'last_visitor_at' => 'datetime', 'taken_at' => 'datetime', 'closed_at' => 'datetime',
        'email_balasan_at' => 'datetime', 'email_admin_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatSession $s) {
            $s->riwayat_token ??= Str::random(40);
        });
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'selesai';
    }

    /** Tambah pesan ke percakapan ini. */
    public function addMessage(string $sender, string $body, ?int $userId = null, ?array $meta = null): ChatMessage
    {
        return $this->messages()->create(['sekolah_id' => $this->sekolah_id, 'sender' => $sender, 'body' => $body, 'user_id' => $userId, 'meta' => $meta]);
    }
}
