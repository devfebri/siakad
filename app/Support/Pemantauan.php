<?php

namespace App\Support;

use App\Backup\BerkasSekolah;
use App\BackupSekolah;
use App\Sekolah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Data pemantauan penggunaan per sekolah untuk panel Super Admin. Hitungan dikelompokkan dalam beberapa query (bukan satu per sekolah). */
class Pemantauan
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function semua(?string $cari = null, ?string $status = null, bool $denganUkuran = true): Collection
    {
        $sekolah = Sekolah::query()
            ->when($cari, function ($q) use ($cari) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $cari).'%';
                $q->where(fn ($w) => $w->whereRaw("nama like ? escape '!'", [$like])->orWhereRaw("subdomain like ? escape '!'", [$like]));
            })
            ->when(in_array($status, ['aktif', 'nonaktif'], true), fn ($q) => $q->where('status', $status))
            ->orderBy('nama')->get();

        $hitung = fn (string $tabel, ?callable $saring = null) => DB::table($tabel)->when($saring, fn ($q) => $saring($q))->selectRaw('sekolah_id, count(*) as n')->groupBy('sekolah_id')->pluck('n', 'sekolah_id');
        $siswa = $hitung('siswa', fn ($q) => $q->where('status', 'aktif'));
        $guru = $hitung('guru');
        $pengguna = $hitung('users');
        $login = DB::table('users')->whereNotNull('login_terakhir_at')->selectRaw('sekolah_id, max(login_terakhir_at) as t')->groupBy('sekolah_id')->pluck('t', 'sekolah_id');
        $api30 = DB::table('api_request_log')->where('tanggal', '>=', today()->subDays(29)->toDateString())->selectRaw('sekolah_id, sum(jumlah) as n')->groupBy('sekolah_id')->pluck('n', 'sekolah_id');
        $apiHariIni = DB::table('api_request_log')->where('tanggal', today()->toDateString())->pluck('jumlah', 'sekolah_id');
        $backup = BackupSekolah::where('status', 'selesai')->selectRaw('sekolah_id, max(created_at) as t')->groupBy('sekolah_id')->pluck('t', 'sekolah_id');

        return $sekolah->map(function (Sekolah $s) use ($siswa, $guru, $pengguna, $login, $api30, $apiHariIni, $backup, $denganUkuran) {
            $langganan = $s->langgananTerkini();

            return [
                'sekolah' => $s,
                'siswa' => (int) ($siswa[$s->id] ?? 0),
                'guru' => (int) ($guru[$s->id] ?? 0),
                'pengguna' => (int) ($pengguna[$s->id] ?? 0),
                'login_terakhir' => isset($login[$s->id]) ? \Illuminate\Support\Carbon::parse($login[$s->id]) : null,
                'api_30_hari' => (int) ($api30[$s->id] ?? 0),
                'api_hari_ini' => (int) ($apiHariIni[$s->id] ?? 0),
                'status_sewa' => $s->tanpa_batas ? 'tanpa_batas' : $s->statusLangganan(),
                'paket' => $s->tanpa_batas ? null : $langganan?->paket?->nama,
                'berakhir' => $langganan?->berakhir,
                'backup_terakhir' => isset($backup[$s->id]) ? \Illuminate\Support\Carbon::parse($backup[$s->id]) : null,
                'ukuran' => $denganUkuran ? BerkasSekolah::ukuran($s) : null,
            ];
        });
    }

    /** Ukuran byte menjadi teks yang mudah dibaca. */
    public static function ukuranTeks(?int $byte): string
    {
        if ($byte === null) {
            return '-';
        }
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $satuan) {
            if ($byte < 1024 ** ($i + 1) || $satuan === 'GB') {
                return ($i === 0 ? $byte : number_format($byte / 1024 ** $i, 1, ',', '.')).' '.$satuan;
            }
        }

        return $byte.' B';
    }
}
