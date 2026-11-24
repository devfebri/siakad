<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Email konsultasi online masuk antrian database; cron hosting cukup menjalankan `php artisan schedule:run` tiap menit.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

// Tutup chat yang menggantung dan hapus riwayat konsultasi online yang melewati masa simpan.
Schedule::command('chat:bersihkan')->dailyAt('02:00');

// Hapus pendaftaran sekolah mandiri yang tidak pernah diverifikasi (sudah lewat 7 hari dari batas waktunya).
Schedule::call(fn () => \App\PendaftaranSekolah::whereNull('diverifikasi_pada')->where('kedaluwarsa_pada', '<', now()->subDays(7))->delete())->dailyAt('03:00');

// Backup mingguan semua sekolah aktif (dikerjakan di antrian) dan arsip sekolah yang nonaktif 90 hari.
Schedule::command('backup:sekolah')->weeklyOn(0, '01:30');
Schedule::command('sekolah:arsipkan')->dailyAt('04:00');

// Cek otomatis domain kustom yang menunggu verifikasi DNS.
Schedule::command('domain:periksa')->hourly();

// Pengingat jatuh tempo dan tunggakan tagihan lewat WhatsApp (hanya sekolah yang menyalakannya).
Schedule::command('wa:tagihan-pengingat')->dailyAt('08:30');

// Pengingat langganan berakhir (H-30, H-7, H-1) ke admin sekolah.
Schedule::command('langganan:pengingat')->dailyAt('08:00');
