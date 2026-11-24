# Sistem Informasi Sekolah

Aplikasi web sekolah (situs publik + panel admin/guru/siswa) berbasis Laravel 13 + Tailwind CSS (tema navy-oranye). Satu kode untuk banyak sekolah: nama, logo, dan warna diatur lewat `.env`.

## Menjalankan di lokal
```bash
composer install
cp .env.example .env        # lalu isi DB_* dan SCHOOL_*; php artisan key:generate
php artisan migrate
php artisan db:seed         # data uji (hanya lokal)
npm install && npm run build
php artisan serve
```
Login memakai **username** (bukan email). Akun uji (password `password`): username `admin`, `guru`, `siswa`. Akun lama otomatis mendapat username dari bagian depan email-nya (mis. `budi@sekolah.id` menjadi `budi`); admin dapat mengubahnya di menu Pengguna, Data Guru, atau Data Siswa. Akun guru/siswa baru memakai NIP/NISN sebagai username bila tidak diisi.

## Modul
- **Situs publik**: beranda (hero, statistik, program unggulan, berita, galeri, testimoni, banner PPDB + hitung mundur), profil, akademik, berita, galeri, kontak, PPDB online.
- **Admin**: dashboard (kehadiran mingguan, rata-rata nilai per kelas, jadwal hari ini, pendaftar PPDB), data siswa/guru, kelas/mapel/jadwal (deteksi bentrok), nilai & rapor (bobot tugas 20, UH 20, UTS 25, UAS 35; KKM di Pengaturan), absensi harian + rekap, materi, berita, galeri, pengumuman, agenda, PPDB, pengguna, pengaturan situs.
- **Guru**: jadwal mengajar, absensi, nilai mapel sendiri, materi, tugas/soal. **Siswa**: jadwal, kehadiran, nilai/rapor, mengerjakan tugas.

## Data contoh & foto
`php artisan db:seed` juga mengisi foto nyata (hero, kepala sekolah, profil, berita, galeri, testimoni) dari `database/seeders/foto/` (sumber Unsplash, lisensi bebas pakai). Semuanya dapat diganti lewat menu admin: Pengaturan (identitas, beranda, program unggulan, testimoni, halaman profil), Berita, dan Galeri. Seeder tidak menimpa data yang sudah diedit.

## Deploy (cPanel tanpa Node)
`public/build` sudah di-commit; jalankan `npm run build` di lokal bila mengubah CSS/JS. Server: PHP ≥ 8.3, `upload_max_filesize` ≥ 10M, lalu `php artisan migrate`. Folder lama `public/template` boleh dihapus.

**Unduhan**: menu Unduhan di situs publik (`/unduhan`) menampilkan berkas yang diunggah admin di Konten Web > Unduhan (formulir, panduan, brosur, dll; PDF/Word/Excel/PowerPoint/ZIP/gambar, maks. 10 MB) lengkap dengan pencarian, filter kategori, dan penghitung unduhan.

## Multi-sekolah (SaaS)
Satu instalasi melayani banyak sekolah. Sekolah dikenali dari alamat: domain kustom (`sekolah.domain_kustom`), subdomain dari `SEKOLAH_DOMAIN_UTAMA` (mis. `smpn1.siakad.id` menjadi `smpn1`), lalu sekolah bawaan `SEKOLAH_DEFAULT`. Bila `SEKOLAH_DEFAULT` kosong dan hanya ada satu sekolah, sekolah itu dipakai. Di lokal tambahkan `?sekolah=<subdomain>` ke alamat (diaktifkan otomatis di `APP_ENV=local`/`testing`; jangan aktifkan `SEKOLAH_QUERY` di produksi). Semua tabel data sekolah punya `sekolah_id` dan dibatasi otomatis oleh trait `BelongsToSekolah`; NISN, NIP, username, email, dan slug berita unik per sekolah. Migrasi memasukkan data lama ke satu sekolah bawaan.

**Peran**: satu akun boleh punya banyak peran (`super_admin`, `admin`, `kepsek`, `guru`, `wali_kelas`, `bendahara`, `siswa`, `orang_tua`) lewat tabel `peran` dan `peran_user`. Panel admin dibuka peran `admin`, panel guru oleh `guru`/`wali_kelas`, panel siswa oleh `siswa`; akun dengan beberapa panel dapat berpindah dari menu profil. Admin mengatur peran di menu Pengguna (tombol Peran). Peran kepsek, bendahara, dan orang_tua baru punya panel pada poin berikutnya.

**Panel Super Admin** (`/platform`): kelola sekolah penyewa (subdomain/domain, aktif/nonaktif, admin pertama), paket dan modul, harga 1/6/12 bulan, langganan, invoice dengan konfirmasi bayar manual, login-sebagai admin sekolah, dan log aktivitas. Buat akun pertama dengan `php artisan platform:super-admin <username>` (password ditanyakan), lalu masuk di `/platform/login`. Set `SEKOLAH_PLATFORM_URL` bila panel platform berada di domain berbeda dari situs sekolah.

**Aturan langganan**: langganan aktif sampai tanggal berakhir; setelahnya ada masa tenggang 7 hari (banner peringatan, semua fitur tetap jalan), lalu situs masuk mode baca-saja (POST/PUT/PATCH/DELETE ditolak dengan pesan jelas; situs publik tetap tampil; login, formulir PPDB/chat pengunjung, dan pengajuan perpanjangan tetap bisa). Modul di luar paket (Basic: absensi, nilai, konten web; Pro: + PPDB, konsultasi online, unduhan; Premium: + portal orang tua) disembunyikan dari menu dan ditolak di route; atur pemetaan di `config/modul-akses.php`. Admin sekolah mengajukan perpanjangan lewat menu **Langganan Saya**, lalu super admin mengonfirmasi pembayaran. Sekolah bertanda "Tanpa batas" (sekolah lama hasil migrasi, demo/internal) tidak pernah dibatasi. Isi petunjuk bayar lewat `SEKOLAH_PETUNJUK_PEMBAYARAN`. Pengingat email H-30/H-7/H-1 dikirim oleh `php artisan langganan:pengingat` (terjadwal harian 08:00 lewat `schedule:run`, perlu cron dan queue worker).

**Tahun ajaran & semester**: menu admin *Tahun Ajaran* mengelola tabel `tahun_ajaran` dan `semester` (satu semester aktif per sekolah, tanggal mulai/selesai, KKM per semester) serta bobot nilai (`bobot_nilai`, total 100). Jadwal, absensi, dan nilai menunjuk `semester_id`; absensi mengikuti semester menurut tanggalnya. Sekolah yang belum punya semester otomatis dibuatkan dari tanggal saat pertama kali dibutuhkan. Isian lama di Pengaturan (tahun ajaran, semester, KKM) dipindahkan oleh migrasi.

**Rombel & wali kelas**: kelas kini berupa rombongan belajar per tahun ajaran (tabel `rombel`, anggota di `rombel_siswa`, satu rombel per siswa per tahun ajaran). Admin mengatur nama, tingkat, wali kelas (guru yang ditunjuk otomatis mendapat peran `wali_kelas`), anggota, dan bisa menyalin struktur rombel ke tahun ajaran baru. `siswa.kelas_id` selalu menunjuk rombel pada tahun ajaran aktif dan ikut berganti saat semester di tahun ajaran lain diaktifkan; riwayat rombel tahun lalu tetap tersimpan.

**Kepala sekolah, wali kelas, orang tua**: peran `kepsek` membuka panel `/kepsek` (dashboard, daftar rombel, rekap kehadiran, rapor; semuanya hanya-baca). Guru yang menjadi wali kelas mendapat menu *Kelas Wali* (kehadiran bulan ini, rata-rata nilai, orang tua, rapor siswa di rombelnya). Portal orang tua (`/ortu`, paket Premium, modul `portal_orang_tua`): admin membuat akun di menu *Orang Tua* (login memakai username, password awal `ortu123`) dan menghubungkannya ke siswa (satu orang tua banyak anak, satu siswa banyak wali); orang tua memilih anak lalu melihat kehadiran, nilai, dan rapor. Satu akun boleh memegang beberapa peran dan berpindah panel dari menu profil.

**Pengaturan awal & impor Excel**: sekolah baru dipandu menu *Pengaturan Awal* (identitas, tahun ajaran/semester/KKM, mata pelajaran dan rombel, lalu impor guru dan siswa); dashboard admin menampilkan pengingat sampai ditandai selesai (sekolah lama yang sudah berisi data otomatis dianggap selesai). Menu *Impor Data* menyediakan template Excel guru dan siswa; berkas `.xlsx`/`.csv` (maks. 1000 baris, 2 MB) diperiksa dulu per baris (duplikat NIP/NISN/username/email, format tanggal, jenis kelamin, agama, rombel), baris bermasalah dilewati dan dilaporkan, sisanya diimpor dalam satu transaksi. Password awal guru `guru123`, siswa `siswa123`; login memakai username (NIP/NISN bila kosong).

**Proses tahunan**: menu *Proses Tahunan* (admin) membuka tahun ajaran baru berstatus Draf dengan rombel salinan (opsional wali kelas dan jadwal). Wali kelas mengusulkan naik/tinggal/lulus per siswa (*Usulan Kenaikan*); admin memproses per rombel atau per siswa (naik ke tingkat berikutnya dengan paralel yang sama, tinggal di tingkat sama, tingkat akhir lulus/tidak lulus dengan nomor ijazah). Aktivasi ditolak selama masih ada siswa belum diproses; saat aktif, tahun ajaran lama otomatis *Ditutup* (baca-saja untuk jadwal, absensi, nilai, dan rombelnya), lulusan masuk *Data Alumni* (filter angkatan, pencarian, ekspor Excel), dan akun siswa serta akun orang tua tanpa anak aktif dinonaktifkan. Selama Draf semua proses bisa dibatalkan. Aksi penting dicatat di `log_aktivitas` per sekolah. Data contoh: `php artisan db:seed --class=ProsesTahunanSeeder`.

## Konsultasi Online (live chat)
Widget chat di situs publik (jQuery + AJAX, polling) dan panel petugas di admin (menu **Konsultasi Online**: Percakapan Langsung, Riwayat, Statistik, Pengaturan). Fitur: antrian dengan estimasi waktu tunggu, pembagian otomatis ke petugas, notifikasi browser, email berantrian, lampiran jpg/png/pdf (maks 5 MB), riwayat dengan tautan unik, PDF dan Excel, rating, chatbot FAQ di luar jam layanan, serta statistik.

**Instalasi / pembaruan di hosting**
1. `composer install --no-dev` lalu `php artisan migrate --force` (membuat tabel chat, `jobs`, dan data bawaan FAQ serta template balasan).
2. Di `.env`: `QUEUE_CONNECTION=database` dan isi `MAIL_*` dengan SMTP sekolah (tanpa SMTP email hanya masuk log).
3. Cron cPanel, setiap menit:
   ```
   * * * * * cd /path/ke/project && php artisan schedule:run >> /dev/null 2>&1
   ```
   Cron ini menjalankan antrian email (`queue:work --stop-when-empty`) tiap menit dan `chat:bersihkan` tiap pukul 02.00 (menutup chat yang menggantung dan menghapus riwayat lewat masa simpan, bawaan 365 hari).
4. `public/build` sudah di-commit; tidak perlu Node di server. Pastikan folder `storage/` dapat ditulis (lampiran chat tersimpan privat di `storage/app/private/chat`).
5. Buka **Konsultasi Online > Pengaturan**: atur jam layanan, hari libur, zona waktu (WIB/WITA/WIT), batas chat per petugas, masa simpan, FAQ chatbot, dan template balasan cepat. Petugas menekan **Status saya: Online** di panel agar chat dibagikan kepadanya; petugas dianggap offline bila 2 menit tanpa aktivitas.

Endpoint pengunjung: `POST /chat/start`, `POST /chat/send`, `GET /chat/poll`, `POST /chat/typing`, `POST /chat/upload`, `POST /chat/end`, `POST /chat/rate`, `GET /chat/export/{token}`, `GET|POST /chat/faq`, `GET /chat/status`, `POST /chat/leave`. Petugas (role admin): `GET /admin/chat/queue`, `POST /admin/chat/{id}/take|reply|transfer|close|upload`, `POST /admin/chat/status`. Pertanyaan keagamaan/fatwa tidak dijawab chatbot (dialihkan ke halaman Kontak).

## Domain kustom per sekolah & SSL otomatis

Sekolah (atau Super Admin) mendaftarkan domainnya di menu **Domain Website**, lalu memasang dua rekaman DNS yang ditampilkan di halaman itu:

| Rekaman | Fungsi |
|---|---|
| `TXT  _siakad-verifikasi.<domain>` = `siakad-verifikasi=<token>` | bukti bahwa domain milik sekolah (wajib; domain tanpa bukti **tidak** pernah dipakai mengenali sekolah) |
| `CNAME <domain>` → `<subdomain>.<domain-utama>` **atau** `A <domain>` → IP server | mengarahkan lalu lintas ke server |

Atur tujuan DNS di `.env`: `SEKOLAH_DOMAIN_UTAMA` (CNAME otomatis `<subdomain>.<domain_utama>`), `SEKOLAH_CNAME_TARGET` (bila ingin CNAME lain), dan `SEKOLAH_SERVER_IP` (untuk A record, domain tanpa "www"). Aplikasi mengecek DNS saat tombol **Cek Sekarang** ditekan dan otomatis tiap jam (`domain:periksa`, perlu cron `schedule:run`).

**Aplikasi tidak mengubah konfigurasi server atau sertifikat.** SSL diurus di server, pilih salah satu:

**A. Caddy (disarankan, sertifikat otomatis per domain).** Aplikasi menyediakan endpoint izin `GET /_domain/izin?domain=<host>` yang menjawab `200` hanya untuk domain terdaftar dan terverifikasi (atau subdomain platform), selain itu `404`. Caddy memakainya agar tidak menerbitkan sertifikat untuk sembarang domain:

```caddyfile
{
    on_demand_tls {
        ask http://127.0.0.1:8000/_domain/izin   # alamat aplikasi di server yang sama
    }
}

https:// {
    tls {
        on_demand
    }
    root * /var/www/siakad/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

**B. certbot (Nginx/Apache).** Wildcard untuk subdomain platform: `certbot certonly --dns-<penyedia> -d "*.siakad.id" -d siakad.id`. Untuk setiap domain kustom yang sudah **terverifikasi**: `certbot --nginx -d sekolahku.sch.id -d www.sekolahku.sch.id`, lalu pastikan `server_name` Nginx mengarah ke folder `public` yang sama. Pembaruan sertifikat otomatis lewat `certbot renew` (timer bawaan).

Catatan: pastikan server meneruskan `Host` asli ke aplikasi (aplikasi mengenali sekolah dari host), dan sesi/cookie otomatis terpisah per domain.

## Backup, pemantauan, dan log (panel Super Admin)

- **Pemantauan** (`/platform/pemantauan`): siswa aktif, guru, akun, penyimpanan berkas, login terakhir, request API 30 hari, dan status langganan tiap sekolah.
- **Log Aktivitas** (`/platform/log`): saring menurut sekolah, pengguna, jenis tindakan, dan tanggal; unduh CSV.
- **Backup** (`/platform/backup`, dan menu **Backup Data** untuk admin sekolah): zip berisi seluruh data sekolah (JSON per tabel), berkas unggahan, dan `manifest.json`. Kata sandi, `cek_password`, token, dan kunci API **tidak** diekspor. Disimpan di `storage/app/private/backup-sekolah/<id>/` dan hanya bisa diunduh lewat halaman berotorisasi.
  - Terjadwal tiap Minggu 01.30 (`backup:sekolah`, bisa dimatikan dan diatur jumlah simpannya di Pengaturan Platform); manual oleh admin sekolah dibatasi 3 kali per hari.
  - Sekolah yang nonaktif 90 hari dibuatkan backup **arsip** otomatis (`sekolah:arsipkan`, harian 04.00). Perintah ini tidak menghapus data apa pun.
  - Backup dikerjakan di antrian: pastikan `queue:work` atau cron `schedule:run` berjalan. Zip memuat data pribadi, jadi sebaiknya disalin juga ke penyimpanan luar server (mis. rsync/rclone ke object storage) dan folder `storage/app/private` tidak boleh terbuka dari web.

## Impor data dari Excel

Menu **Impor Data** (admin) menerima guru, siswa, dan orang tua dari `.xlsx`/`.csv`. Template bisa diunduh dari menu itu (orang tua sudah berisi lembar *Daftar Siswa* berisi NISN sekolah), atau ambil salinan umum di [docs/templat-impor/](docs/templat-impor/). Baris ke-2 template adalah contoh (berawalan CONTOH) dan otomatis dilewati. Salinan umum dibuat ulang dengan `Impor::template($jenis, $path, false)` bila kolom berubah.

## Pengujian
```bash
php artisan test
```

Panduan deploy, variabel `.env`, arsitektur, dan catatan upgrade ada di [UPGRADE_NOTES.md](UPGRADE_NOTES.md).
