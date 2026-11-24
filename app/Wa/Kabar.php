<?php

namespace App\Wa;

use App\Absensi;
use App\Langganan;
use App\OrangTua;
use App\PembayaranSpp;
use App\PpdbPendaftar;
use App\Sekolah;
use App\Support\SekolahAktif;
use App\Tagihan;
use Illuminate\Support\Collection;

/** Merangkai data tiap kejadian menjadi pesan WhatsApp (penerima, referensi unik, dan nilai placeholder). Semua lewat Wa::kabari(). */
class Kabar
{
    private static function rp(int $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }

    /** Orang tua (dengan nomor) dari sekumpulan siswa, dipetakan siswa_id => [OrangTua, ...]. */
    private static function ortuPerSiswa(array $siswaIds): array
    {
        $peta = [];
        $semua = OrangTua::whereNotNull('no_hp')->whereHas('anak', fn ($q) => $q->whereIn('siswa.id', $siswaIds))->with(['anak' => fn ($q) => $q->whereIn('siswa.id', $siswaIds)])->get();
        foreach ($semua as $ortu) {
            foreach ($ortu->anak as $anak) {
                $peta[$anak->id][] = $ortu;
            }
        }

        return $peta;
    }

    // ------------------------------------------------------------ siswa alpa

    public static function alpa(Absensi $a): void
    {
        if (! $a->sekolah_id || ! Wa::pengaturanAktif($a->sekolah_id)) {
            return;
        }

        SekolahAktif::jalankan($a->sekolah_id, function () use ($a) {
            $siswa = $a->siswa()->with('kelas:id,nama_kelas')->first();
            if (! $siswa) {
                return;
            }
            foreach (self::ortuPerSiswa([$siswa->id])[$siswa->id] ?? [] as $ortu) {
                Wa::kabari('alpa', 'alpa:'.$siswa->id.':'.$a->tanggal->toDateString(), $ortu->no_hp, [
                    'nama_ortu' => $ortu->nama, 'nama_siswa' => $siswa->nama_siswa, 'kelas' => $siswa->kelas?->nama_kelas ?? '-', 'tanggal' => $a->tanggal->translatedFormat('d F Y'),
                ], $a->sekolah_id);
            }
        });
    }

    // ------------------------------------------------------------ tagihan terbit (satu pesan per siswa per pembuatan)

    /** @param Collection<int, Tagihan> $baru tagihan yang baru dibuat */
    public static function tagihanTerbit(Collection $baru, string $kunciBatch): void
    {
        $sekolahId = SekolahAktif::instance()->id();
        if ($baru->isEmpty() || ! $sekolahId || ! Wa::pengaturanAktif($sekolahId)) {
            return;
        }

        $perSiswa = $baru->groupBy('siswa_id');
        $ortu = self::ortuPerSiswa($perSiswa->keys()->all());
        $nama = \App\Siswa::whereIn('id', $perSiswa->keys())->pluck('nama_siswa', 'id');

        foreach ($perSiswa as $siswaId => $tagihan) {
            $pertama = $tagihan->sortBy('jatuh_tempo')->first();
            foreach ($ortu[$siswaId] ?? [] as $o) {
                Wa::kabari('tagihan_terbit', 'terbit:'.$siswaId.':'.$kunciBatch, $o->no_hp, [
                    'nama_ortu' => $o->nama, 'nama_siswa' => $nama[$siswaId] ?? '-',
                    'nama_tagihan' => $tagihan->count() > 1 ? $pertama->nama.' dan '.($tagihan->count() - 1).' tagihan lain' : $pertama->nama,
                    'nominal' => self::rp((int) $tagihan->sum('jumlah')), 'jatuh_tempo' => $pertama->jatuh_tempo?->translatedFormat('d F Y') ?? '-',
                ], $sekolahId);
            }
        }
    }

    // ------------------------------------------------------------ pembayaran diterima (satu pesan per orang tua)

    /** @param Collection<int, PembayaranSpp> $pembayaran */
    public static function pembayaran(Collection $pembayaran, string $referensi): void
    {
        $sekolahId = $pembayaran->first()?->sekolah_id;
        if (! $sekolahId || ! Wa::pengaturanAktif($sekolahId)) {
            return;
        }

        SekolahAktif::jalankan($sekolahId, function () use ($pembayaran, $referensi, $sekolahId) {
            $pembayaran = \Illuminate\Database\Eloquent\Collection::make($pembayaran->all());
            $pembayaran->loadMissing('tagihan.siswa:id,nama_siswa');
            $perSiswa = $pembayaran->groupBy(fn (PembayaranSpp $p) => $p->tagihan?->siswa_id);
            $ortu = self::ortuPerSiswa($perSiswa->keys()->filter()->all());

            // gabungkan per orang tua supaya satu pembayaran massal hanya satu pesan
            $perOrtu = [];
            foreach ($perSiswa as $siswaId => $daftar) {
                foreach ($ortu[$siswaId] ?? [] as $o) {
                    $perOrtu[$o->id]['ortu'] = $o;
                    $perOrtu[$o->id]['pembayaran'] = array_merge($perOrtu[$o->id]['pembayaran'] ?? [], $daftar->all());
                }
            }

            foreach ($perOrtu as $baris) {
                $p = collect($baris['pembayaran']);
                Wa::kabari('pembayaran_diterima', $referensi, $baris['ortu']->no_hp, [
                    'nama_ortu' => $baris['ortu']->nama,
                    'nama_siswa' => $p->map(fn ($x) => $x->tagihan?->siswa?->nama_siswa)->filter()->unique()->implode(', '),
                    'nominal' => self::rp((int) $p->sum('jumlah')), 'nomor_kuitansi' => $p->pluck('nomor')->implode(', '),
                    'tanggal' => $p->first()->tanggal->translatedFormat('d F Y'), 'metode' => \App\PembayaranSpp::METODE[$p->first()->metode] ?? $p->first()->metode,
                ], $sekolahId);
            }
        });
    }

    // ------------------------------------------------------------ hasil PPDB

    public static function ppdb(PpdbPendaftar $p): void
    {
        if (! $p->sekolah_id || ! Wa::pengaturanAktif($p->sekolah_id)) {
            return;
        }

        Wa::kabari('ppdb', 'ppdb:'.$p->id.':'.$p->status, $p->telepon, [
            'nama_pendaftar' => $p->nama, 'nama_ortu' => $p->nama_ortu ?: 'Orang Tua/Wali', 'status' => mb_strtoupper(PpdbPendaftar::STATUS[$p->status]['label'] ?? $p->status), 'tahun_ajaran' => $p->tahun_ajaran,
        ], $p->sekolah_id);
    }

    // ------------------------------------------------------------ pengingat tagihan (harian)

    /** Pengingat 3 hari sebelum jatuh tempo dan tunggakan (hari ke-1, 8, 15, 22, 29 setelah jatuh tempo). Dijalankan dalam konteks sekolah. */
    public static function pengingatTagihan(Sekolah $sekolah): int
    {
        if (! Wa::pengaturanAktif($sekolah->id)) {
            return 0;
        }

        return SekolahAktif::jalankan($sekolah, function () use ($sekolah) {
            $hari = today();
            $jumlah = 0;

            $segera = Tagihan::belumLunas()->whereDate('jatuh_tempo', $hari->copy()->addDays(3)->toDateString())->with('siswa:id,nama_siswa')->get();
            $ortu = self::ortuPerSiswa($segera->pluck('siswa_id')->unique()->all());
            foreach ($segera as $t) {
                foreach ($ortu[$t->siswa_id] ?? [] as $o) {
                    $jumlah += Wa::kabari('jatuh_tempo', 'jt:'.$t->id, $o->no_hp, [
                        'nama_ortu' => $o->nama, 'nama_siswa' => $t->siswa?->nama_siswa, 'nama_tagihan' => $t->nama, 'nominal' => self::rp($t->sisa), 'jatuh_tempo' => $t->jatuh_tempo->translatedFormat('d F Y'),
                    ], $sekolah->id) ? 1 : 0;
                }
            }

            $telat = Tagihan::belumLunas()->whereDate('jatuh_tempo', '<', $hari->toDateString())->with('siswa:id,nama_siswa')->get()->groupBy('siswa_id');
            $ortuTelat = self::ortuPerSiswa($telat->keys()->all());
            foreach ($telat as $siswaId => $daftar) {
                $hariTelat = (int) $daftar->min('jatuh_tempo')->diffInDays($hari);
                if (! in_array($hariTelat, [1, 8, 15, 22, 29], true)) {
                    continue;
                }
                foreach ($ortuTelat[$siswaId] ?? [] as $o) {
                    $jumlah += Wa::kabari('tunggakan', 'tg:'.$siswaId.':'.$hari->format('Ymd'), $o->no_hp, [
                        'nama_ortu' => $o->nama, 'nama_siswa' => $daftar->first()->siswa?->nama_siswa, 'nominal' => self::rp((int) $daftar->sum(fn ($t) => $t->sisa)), 'jumlah_tagihan' => $daftar->count(),
                    ], $sekolah->id) ? 1 : 0;
                }
            }

            return $jumlah;
        });
    }

    // ------------------------------------------------------------ pengingat langganan (ke admin)

    public static function langganan(Sekolah $sekolah, Langganan $l, int $sisaHari, int $tahap): void
    {
        $pg = Wa::pengaturanAktif($sekolah->id);
        if (! $pg || ! $pg->nomor_admin) {
            return;
        }

        Wa::kabari('langganan', 'langganan:'.$l->id.':H'.$tahap, $pg->nomor_admin, [
            'paket' => $l->paket?->nama ?? '-', 'tanggal_berakhir' => $l->berakhir->translatedFormat('d F Y'), 'sisa_hari' => max(0, $sisaHari),
        ], $sekolah->id);
    }
}
