<?php

namespace App\Support;

use App\JenisBiaya;
use App\LogAktivitas;
use App\PembayaranSpp;
use App\Siswa;
use App\Tagihan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan keuangan sekolah: membuat tagihan massal, mencatat pembayaran (dengan kuitansi), membatalkan, dan ringkasan.
 * Semua perubahan saldo lewat sini supaya terbayar dan status tagihan selalu konsisten dengan pembayaran.
 */
class Keuangan
{
    /**
     * Buat tagihan untuk siswa aktif. Tagihan yang sudah ada (siswa + biaya + periode sama) dilewati, jadi aman dijalankan ulang.
     *
     * @param array<int, int> $kelasIds kosong = semua siswa aktif yang sudah punya kelas
     * @return array{dibuat: int, dilewati: int}
     */
    public static function buatMassal(JenisBiaya $biaya, array $kelasIds, string $bulanMulai, int $jumlahBulan, int $tanggalJatuhTempo, ?int $nominal = null): array
    {
        $nominal = $nominal ?? $biaya->nominal;
        if ($nominal <= 0) {
            throw ValidationException::withMessages(['jenis_biaya_id' => 'Nominal biaya harus lebih dari nol. Atur dulu di menu Jenis Biaya.']);
        }

        $tahunAjaranId = Akademik::semesterAktif()->tahun_ajaran_id;
        $siswa = Siswa::query()->where('status', 'aktif')->whereNotNull('kelas_id')
            ->when($kelasIds, fn ($q) => $q->whereIn('kelas_id', $kelasIds))->get(['id']);

        // daftar periode: bulanan = beberapa bulan berurutan; sekali bayar = satu tagihan per tahun ajaran
        $periode = [];
        if ($biaya->jenis === 'bulanan') {
            $awal = Carbon::createFromFormat('Y-m-d', $bulanMulai.'-01')->startOfMonth();
            foreach (range(0, max(1, min(12, $jumlahBulan)) - 1) as $i) {
                $bulan = $awal->copy()->addMonthsNoOverflow($i);
                $periode[] = [$bulan->format('Y-m'), $biaya->nama.' '.$bulan->translatedFormat('F Y'), $bulan->copy()->day(min($tanggalJatuhTempo, $bulan->daysInMonth))];
            }
        } else {
            $periode[] = ['TA'.$tahunAjaranId, $biaya->nama, Carbon::createFromFormat('Y-m-d', $bulanMulai.'-01')->day(min($tanggalJatuhTempo, 28))];
        }

        $dibuat = $dilewati = 0;
        $baru = collect();
        DB::transaction(function () use ($biaya, $siswa, $periode, $nominal, $tahunAjaranId, &$dibuat, &$dilewati, $baru) {
            foreach ($siswa as $s) {
                foreach ($periode as [$kode, $nama, $jatuhTempo]) {
                    $ada = Tagihan::where('siswa_id', $s->id)->where('jenis_biaya_id', $biaya->id)->where('periode', $kode)->exists();
                    if ($ada) {
                        $dilewati++;

                        continue;
                    }
                    $baru->push(Tagihan::create([
                        'siswa_id' => $s->id, 'jenis_biaya_id' => $biaya->id, 'tahun_ajaran_id' => $tahunAjaranId, 'nama' => $nama, 'periode' => $kode,
                        'jumlah' => $nominal, 'terbayar' => 0, 'status' => 'belum', 'jatuh_tempo' => $jatuhTempo->toDateString(),
                    ]));
                    $dibuat++;
                }
            }
        });

        // satu pesan WhatsApp per siswa untuk tagihan yang baru terbit (bila sekolah menyalakannya)
        \App\Wa\Kabar::tagihanTerbit($baru, $biaya->id.':'.$bulanMulai.':'.$jumlahBulan);

        LogAktivitas::catat('keuangan.tagihan_massal', "Tagihan {$biaya->nama} dibuat untuk {$dibuat} siswa/periode ({$dilewati} sudah ada).", null, ['jenis_biaya_id' => $biaya->id, 'dibuat' => $dibuat]);

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati];
    }

    /**
     * Catat pembayaran satu tagihan. Jumlah tidak boleh melebihi sisa tagihan. Mengembalikan pembayaran (dengan nomor kuitansi).
     *
     * @throws ValidationException
     */
    public static function bayar(Tagihan $tagihan, int $jumlah, string $metode = 'tunai', ?string $tanggal = null, ?string $catatan = null, ?string $referensi = null, ?string $transaksiId = null): PembayaranSpp
    {
        return DB::transaction(function () use ($tagihan, $jumlah, $metode, $tanggal, $catatan, $referensi, $transaksiId) {
            $t = Tagihan::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();

            if (in_array($t->status, ['lunas', 'batal'], true)) {
                throw ValidationException::withMessages(['jumlah' => 'Tagihan ini sudah '.strtolower(Tagihan::STATUS[$t->status]).'.']);
            }
            if ($jumlah < 1 || $jumlah > $t->sisa) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah harus antara Rp 1 dan sisa tagihan Rp '.number_format($t->sisa, 0, ',', '.').'.']);
            }

            $pembayaran = self::simpanDenganNomor([
                'tagihan_id' => $t->id, 'transaksi_id' => $transaksiId, 'jumlah' => $jumlah, 'metode' => $metode,
                'tanggal' => $tanggal ?: today()->toDateString(), 'referensi' => $referensi, 'catatan' => $catatan, 'diterima_oleh' => auth()->id(),
            ]);
            self::hitungUlang($t);

            // pembayaran manual: kabari orang tua setelah transaksi selesai (pembayaran online dikabari per transaksi oleh listener)
            if ($transaksiId === null) {
                app('db.transactions')->addCallback(fn () => \App\Wa\Kabar::pembayaran(collect([$pembayaran]), 'bayar:'.$pembayaran->nomor));
            }

            LogAktivitas::catat('keuangan.bayar', "Pembayaran {$pembayaran->nomor} Rp ".number_format($jumlah, 0, ',', '.')." untuk tagihan {$t->nama} (siswa #{$t->siswa_id}).", null, ['tagihan_id' => $t->id]);

            return $pembayaran;
        });
    }

    /** Batalkan pembayaran (koreksi salah catat). Pembayaran tetap tersimpan sebagai riwayat. */
    public static function batalkanPembayaran(PembayaranSpp $pembayaran, string $alasan): void
    {
        DB::transaction(function () use ($pembayaran, $alasan) {
            $p = PembayaranSpp::whereKey($pembayaran->id)->lockForUpdate()->firstOrFail();
            if ($p->dibatalkan()) {
                throw ValidationException::withMessages(['alasan' => 'Pembayaran ini sudah dibatalkan.']);
            }
            $p->update(['dibatalkan_pada' => now(), 'alasan_batal' => mb_substr($alasan, 0, 200)]);
            self::hitungUlang(Tagihan::whereKey($p->tagihan_id)->lockForUpdate()->firstOrFail());

            LogAktivitas::catat('keuangan.batal_bayar', "Pembayaran {$p->nomor} dibatalkan: {$alasan}", null, ['pembayaran_id' => $p->id]);
        });
    }

    /** Batalkan tagihan yang belum ada pembayarannya. */
    public static function batalkanTagihan(Tagihan $tagihan): void
    {
        DB::transaction(function () use ($tagihan) {
            $t = Tagihan::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();
            if ($t->pembayaran()->aktif()->exists()) {
                throw ValidationException::withMessages(['status' => 'Tagihan yang sudah ada pembayarannya tidak bisa dibatalkan. Batalkan pembayarannya dulu.']);
            }
            $t->update(['status' => 'batal']);
            LogAktivitas::catat('keuangan.batal_tagihan', "Tagihan {$t->nama} (siswa #{$t->siswa_id}) dibatalkan.");
        });
    }

    /** terbayar dan status dihitung dari pembayaran yang tidak dibatalkan. */
    public static function hitungUlang(Tagihan $t): void
    {
        $terbayar = (int) $t->pembayaran()->aktif()->sum('jumlah');
        $status = match (true) {
            $t->status === 'batal' => 'batal',
            $terbayar >= $t->jumlah => 'lunas',
            $terbayar > 0 => 'sebagian',
            default => 'belum',
        };
        $t->update(['terbayar' => $terbayar, 'status' => $status]);
    }

    /** Nomor kuitansi KW-YYYYMM-0001 berurutan per sekolah per bulan; diulang bila bentrok akibat dua pembayaran bersamaan. */
    private static function simpanDenganNomor(array $data): PembayaranSpp
    {
        for ($coba = 0; ; $coba++) {
            $awalan = 'KW-'.now()->format('Ym').'-';
            $terakhir = PembayaranSpp::where('nomor', 'like', $awalan.'%')->orderByDesc('nomor')->value('nomor');
            $nomor = $awalan.sprintf('%04d', $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1);

            try {
                return PembayaranSpp::create($data + ['nomor' => $nomor]);
            } catch (QueryException $e) {
                if ($coba >= 4) {
                    throw $e;
                }
            }
        }
    }

    /** Angka ringkasan untuk dashboard keuangan. */
    public static function ringkasan(): array
    {
        $hariIni = today();
        $awalBulan = $hariIni->copy()->startOfMonth();

        $belumLunas = Tagihan::belumLunas();
        $masuk = fn ($dari, $sampai) => (int) PembayaranSpp::aktif()->whereDate('tanggal', '>=', $dari->toDateString())->whereDate('tanggal', '<=', $sampai->toDateString())->sum('jumlah');

        return [
            'sisa_tagihan' => (int) (clone $belumLunas)->selectRaw('COALESCE(SUM(jumlah - terbayar), 0) as s')->value('s'),
            'jumlah_menunggak' => (clone $belumLunas)->where('jatuh_tempo', '<', $hariIni->toDateString())->distinct('siswa_id')->count('siswa_id'),
            'tertagih_hari_ini' => $masuk($hariIni, $hariIni),
            'tertagih_bulan_ini' => $masuk($awalBulan, $hariIni->copy()->endOfMonth()),
            'tagihan_lunas_bulan_ini' => Tagihan::where('status', 'lunas')->whereBetween('updated_at', [$awalBulan, $hariIni->copy()->endOfMonth()])->count(),
        ];
    }
}
