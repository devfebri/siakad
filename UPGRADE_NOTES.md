# Catatan Upgrade dan Redesign

Aplikasi sistem informasi sekolah, dinaikkan dari **Laravel 6.20 (PHP 7.4)** ke **Laravel 13.38 (PHP 8.3)** dan tampilan adminnya ditulis ulang
(Bootstrap 5, Vite, jQuery AJAX). Satu kode untuk banyak sekolah: nama, logo, dan warna diatur lewat `.env`.

Branch: `upgrade-laravel` (upgrade + migration + seeder) lalu `ui-admin-redesign` (tampilan baru). Struktur database lama **tidak diubah**;
migration hanya membuat tabel yang belum ada dan menambah kolom yang kurang.

## 1. Kebutuhan
- PHP **8.3+** (ekstensi umum Laravel + `fileinfo`; `gd` untuk uji gambar), Composer 2, MySQL/MariaDB.
- Node.js hanya untuk mengubah CSS/JS (`npm install`, `npm run build`). Hasil build `public/build` sudah di-commit, jadi **server tidak perlu Node**.
- PHP ini: `upload_max_filesize` dan `post_max_size` minimal **10M** (materi sampai 10 MB; bawaan PHP 2M).

## 2. Pengaturan `.env` (jangan salin `.env.example` mentah-mentah)
- Ganti nama variabel lama: `MAIL_DRIVER` → `MAIL_MAILER`, `CACHE_DRIVER` → `CACHE_STORE`, `BROADCAST_DRIVER` → `BROADCAST_CONNECTION`.
- `APP_LOCALE=id` dan `APP_FALLBACK_LOCALE=id` (tanggal dan pesan validasi berbahasa Indonesia).
- `SESSION_DRIVER=file` (atau `database` bila memakai tabel `sessions`), `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`.
- Identitas sekolah: `SCHOOL_NAME`, `SCHOOL_SHORT_NAME`, `SCHOOL_TAGLINE`, `SCHOOL_LOGO` (path di folder `public`, kosong = huruf inisial), `SCHOOL_PRIMARY_COLOR` (mis. `#4F46E5`).
- Opsional: `LARAVEL_PAGE_SPEED_ENABLE` (default `false`).

## 3. Deploy
1. **Backup database dan folder `public/storage`.**
2. Pastikan PHP server ≥ 8.3, lalu unggah kode (tanpa `vendor`, `node_modules`, `.env`).
3. `composer install --no-dev --optimize-autoloader`.
4. Perbarui `.env` (bagian 2). `php artisan key:generate` hanya bila `APP_KEY` kosong.
5. `php artisan migrate:status`, lalu `php artisan migrate --force`. Aman untuk database lama: tabel yang ada tidak diubah dan data tidak disentuh.
   Kolom yang ditambahkan: `users.role`, `berita.berita_img_slider`, `materi.user_id`, `tugas.guru_id`, serta kolom pengerjaan di `tugas_siswa`.
   Kolom lama `tugas.pembuat` dijadikan nullable.
6. `public/storage` harus ada dan dapat ditulis (folder biasa atau `php artisan storage:link`). Berkas lama tetap dipakai di tempatnya.
7. Hapus folder lama `public/template/admin` dan `public/template/admin.js` di server bila masih ada (tidak dipakai lagi; ±90 MB).
8. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
9. Ganti versi PHP di cPanel/VPS ke 8.3, lalu uji halaman utama dan login.

Data uji lokal: `php artisan db:seed` (ditolak di production kecuali `--force`). Semua akun berpassword `password`:
Username: `admin`, `guru`, `guru2`, `siswa`, `siswa2`, `siswa3` (login memakai username, bukan email).

## 4. Yang berubah untuk pengguna
- **Admin:** menu dikelompokkan (Pengguna, Akademik, Konten Web). Semua tabel memakai paginasi server-side, aman untuk puluhan ribu data.
  Tambah/ubah/hapus tanpa muat ulang halaman. Mode terang/gelap. Profil dan Keluar ada di menu pengguna (kanan atas).
- **Guru:** Tugas dan Berita aktif kembali (sebelumnya route-nya dimatikan). Guru hanya melihat dan mengubah materi, berita, dan tugas miliknya.
- **Siswa:** Kelas Saya menampilkan tugas, materi, dan teman sekelas. Siswa dapat **mengerjakan tugas online**: timer, pilihan ganda dinilai otomatis,
  essay tersimpan untuk dibaca guru, hasil tampil setelah dikumpulkan (Latihan menampilkan pembahasan, Ujian tidak). Satu kali pengerjaan per siswa.
- **Akun baru** guru/siswa dibuat otomatis dengan password awal `guru123` / `siswa123` (konstanta `DEFAULT_PASSWORD`); ganti password sekarang meminta password lama.
- **Penghapusan dilindungi** (balasan 409 dengan penjelasan) agar tidak ada data yatim: mapel yang dipakai, kelas berisi siswa/materi/tugas, kategori yang punya berita,
  guru yang punya materi/tugas, siswa yang sudah mengerjakan tugas, tugas yang sudah dikerjakan, serta akun admin dan akun sendiri.
- **Halaman publik** tetap sama; berita kini dipaginasi dan gambar berita tidak lagi rusak bila nama kategori diganti.

## 5. Keamanan
- Hapus dan keluar kini `DELETE`/`POST` dengan token CSRF (sebelumnya link GET).
- Isi berita dan profil sekolah disaring `HTMLPurifier` sebelum disimpan (skrip, atribut `on*`, tautan `javascript:` dibuang) karena halaman publik menampilkannya apa adanya.
- Unggahan dibatasi tipe dan ukuran dengan nama file acak. Berkas materi hanya dapat diunduh siswa dari kelasnya.
- Login: pesan error jelas, sesi diregenerasi, dibatasi 10 percobaan per menit. Role yang salah diarahkan ke dashboardnya sendiri (JSON mendapat 403).

## 6. Arsitektur singkat
- **Layout:** `resources/views/layouts/admin.blade.php` (aplikasi) dan `guest.blade.php` (login). Menu di `config/admin-menu.php`. Komponen di `resources/views/components/admin/`.
- **Pola CRUD AJAX:** `Route::ajaxCrud('admin/mapel', MapelController::class, 'mapel')` (AppServiceProvider) + `App\Support\DataTableResponse` (server-side) +
  `resources/js/crud.js` (jQuery). Menu baru cukup menyalin `resources/views/mapel/index.blade.php` dan `MapelController`.
  Atribut yang tersedia: `data-crud`, `data-url/store/update/destroy`, `data-show`, `data-edit-link`, `<th data-col data-cell="image|file|link|avatar">`,
  `data-create-only`, `data-edit-only`, `data-lock-on-edit`, dan `<form data-ajax-form>` untuk form halaman penuh.
- **Pendukung:** `App\Support\Avatar` (foto guru/siswa), `App\Support\Html` (sanitasi HTML), `config/profil-sekolah.php` (8 halaman profil sekolah).
- **Lokasi berkas** tidak berubah: `public/storage/{guru|siswa}/{email}`, `gallery`, `pengaturan/{bagian}`; berkas baru memakai `materi/files` dan `berita/files`
  (berkas lama di folder lama tetap ditemukan).

## 7. Pengujian
`php artisan test` → 145 tes (smoke tiap halaman per role, CRUD tiap menu, unggah berkas, hak akses, alur pengerjaan tugas, seeder, login).
Test memakai sqlite in-memory dan folder `public` sementara; tidak menyentuh database atau berkas asli.

## 8. Detail upgrade framework
| Lama | Baru |
|---|---|
| laravel/framework 6.20 | 13.38 |
| cviebrock/eloquent-sluggable 6.0 | 13.0 |
| realrashid/sweet-alert 3.2 | 8.0 (hanya untuk konversi pesan flash) |
| renatomarinho/laravel-page-speed 2.0 | 4.4 |
| fideloper/proxy, facade/ignition | dihapus (bawaan framework) |
| phpunit 9 | 12 |
| (baru) | laravel-lang/common (terjemahan `id`), ezyang/htmlpurifier |

Struktur skeleton baru: `bootstrap/app.php` menggantikan `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler`, dan provider route/auth/event/broadcast.
Route memakai `[Controller::class, 'method']`, `$dates` di model menjadi `$casts`, `database/seeds` menjadi `database/seeders`.

## 9. Catatan dan keterbatasan
- Jawaban **essay** disimpan tetapi belum ada layar penilaian manual untuk guru (nilai hanya dari pilihan ganda).
- Laravel 13 tidak berjalan di PHP 8.2. Jika server tidak bisa PHP 8.3, turunkan target ke Laravel 12.
- Daftar publik galeri dan guru belum dipaginasi.

## 10. Redesign Tailwind (tahap 1-4)
- UI admin dan situs publik ditulis ulang dengan Tailwind v4 (Vite, `@tailwindcss/vite`), Alpine.js untuk interaksi, jQuery + DataTables untuk tabel server-side. Bootstrap hanya dipakai sebagai font ikon. Dark mode dibuang.
- Pengaturan situs disimpan di tabel `site_settings` (identitas, kontak, beranda, tahun ajaran, PPDB, KKM) dan menimpa `config('school.*')` lewat middleware `ApplySiteSettings`.
- Tabel baru: `jadwal`, `absensi` (unik siswa+tanggal), `nilai` (unik siswa+mapel+jenis+periode), `ppdb_pendaftar`, `program_unggulan`, `testimoni`. Migration aman dijalankan di database yang sudah berisi data.
- Seeder `AkademikSeeder` mengisi jadwal, kehadiran 2 pekan, nilai, dan 3 pendaftar PPDB untuk demo dashboard.
