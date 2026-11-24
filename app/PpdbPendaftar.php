<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PpdbPendaftar extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'ppdb_pendaftar';
    protected $fillable = [
        'tahun_ajaran', 'nama', 'jenis_kelamin', 'agama', 'tempat_lahir', 'tanggal_lahir', 'nisn', 'asal_sekolah', 'alamat',
        'nama_ortu', 'telepon', 'email', 'jalur', 'peminatan', 'nilai_rata', 'berkas', 'status', 'catatan',
    ];
    protected $casts = ['tanggal_lahir' => 'date', 'nilai_rata' => 'decimal:2', 'dijadikan_siswa_pada' => 'datetime'];

    public const STATUS = [
        'menunggu' => ['label' => 'Menunggu', 'badge' => 'warning'],
        'diterima' => ['label' => 'Diterima', 'badge' => 'success'],
        'ditolak' => ['label' => 'Ditolak', 'badge' => 'danger'],
    ];
    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'];
    public const JALUR = ['Reguler', 'Prestasi', 'Afirmasi', 'Zonasi'];
    public const PEMINATAN = ['IPA', 'IPS', 'Bahasa'];

    /**
     * Aturan validasi data pendaftar (formulir publik dan admin).
     *
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>, 2: array<string, string>} [aturan, pesan, nama atribut]
     */
    public static function aturan(): array
    {
        return [
            [
                'nama' => ['required', 'string', 'min:3', 'max:120'],
                'jenis_kelamin' => ['required', Rule::in(['Laki-Laki', 'Perempuan'])],
                'agama' => ['nullable', Rule::in(self::AGAMA)],
                'tempat_lahir' => ['required', 'string', 'max:80'],
                'tanggal_lahir' => ['required', 'date', 'before:today', 'after:'.now()->subYears(25)->toDateString()],
                'nisn' => ['nullable', 'string', 'regex:/^\d{8,20}$/'],
                'asal_sekolah' => ['required', 'string', 'max:120'],
                'alamat' => ['required', 'string', 'max:500'],
                'nama_ortu' => ['required', 'string', 'max:120'],
                'telepon' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
                'email' => ['nullable', 'email', 'max:150'],
                'jalur' => ['required', Rule::in(self::JALUR)],
                'peminatan' => ['nullable', Rule::in(self::PEMINATAN)],
                'nilai_rata' => ['nullable', 'numeric', 'between:0,100'],
                'berkas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            ],
            [
                'tanggal_lahir.after' => 'Tanggal lahir tidak valid.',
                'nisn.regex' => 'NISN harus berupa angka (8–20 digit).',
                'telepon.regex' => 'Nomor telepon tidak valid.',
            ],
            [
                'nama' => 'nama lengkap', 'tempat_lahir' => 'tempat lahir', 'tanggal_lahir' => 'tanggal lahir', 'asal_sekolah' => 'asal sekolah',
                'nama_ortu' => 'nama orang tua/wali', 'telepon' => 'nomor telepon', 'nilai_rata' => 'nilai rata-rata', 'berkas' => 'berkas',
            ],
        ];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status]['label'] ?? ucfirst($this->status);
    }

    /** Buat pendaftar baru beserta nomor pendaftaran berurutan per tahun ajaran, mis. PPDB-2027-0012. */
    public static function daftar(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $pendaftar = new static($data + ['status' => 'menunggu']);
            $pendaftar->no_pendaftaran = 'TMP-'.Str::random(24); // diganti setelah id diketahui
            $pendaftar->save();
            $tahun = substr($pendaftar->tahun_ajaran, 0, 4);
            $pendaftar->forceFill(['no_pendaftaran' => sprintf('PPDB-%s-%04d', $tahun, $pendaftar->id)])->save();

            return $pendaftar;
        });
    }
}
