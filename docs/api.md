# API Data Induk v1 (untuk aplikasi E-Learning)

API ini khusus **paket Premium**. Hanya membaca; tidak ada endpoint untuk mengubah data sekolah.
Setiap sekolah punya satu klien sendiri, dan klien itu hanya dapat melihat data sekolahnya.

Admin sekolah menyalakan sambungan di menu **Integrasi E-Learning** lalu memberikan **ID Klien** dan **Kunci Rahasia** kepada pengelola E-Learning.

## 1. Meminta token

```
POST {alamat-sekolah}/oauth/token
Content-Type: application/json

{ "grant_type": "client_credentials", "client_id": "<ID Klien>", "client_secret": "<Kunci Rahasia>" }
```

Balasan: `{"token_type":"Bearer","expires_in":...,"access_token":"..."}`. Pakai di setiap request: `Authorization: Bearer <access_token>`.
Kunjungan ke `/oauth/token` dibatasi per alamat IP. Token berumur terbatas; minta token baru bila kedaluwarsa (401).

## 2. Mengambil data

Alamat dasar: `{alamat-sekolah}/api/v1`. Semua balasan JSON.

| Endpoint | Isi | Filter tambahan |
|---|---|---|
| `GET /sekolah` | identitas sekolah | - |
| `GET /tahun-ajaran/aktif` | tahun ajaran dan semester yang sedang berjalan | - |
| `GET /tahun-ajaran` | semua tahun ajaran beserta semesternya | `status` |
| `GET /rombel` | rombongan belajar (kelas per tahun ajaran) | `tahun_ajaran_id`, `tingkat` |
| `GET /rombel-siswa` | keanggotaan siswa per rombel | `rombel_id`, `siswa_id`, `tahun_ajaran_id` |
| `GET /mapel` | mata pelajaran | - |
| `GET /guru` | guru | `mapel_id` |
| `GET /siswa` | siswa | `rombel_id`, `status` |
| `GET /jadwal` | jadwal pelajaran | `rombel_id`, `guru_id`, `mapel_id`, `semester_id` |

Parameter umum untuk daftar:

- `per_page` (1–100, bawaan 50) dan `page`.
- `updated_since` — tanggal/waktu ISO 8601; hanya data yang berubah sejak saat itu (untuk sinkronisasi berkala).

Bentuk balasan daftar:

```json
{ "data": [ { "id": 1, "nama": "...", "updated_at": "2026-11-12T10:54:39+00:00" } ],
  "meta": { "page": 1, "per_page": 50, "total": 120, "last_page": 3 },
  "links": { "next": "…?page=2", "prev": null } }
```

Data yang **dihapus** tidak muncul di daftar. Untuk mengetahui penghapusan, pakai webhook (di bawah) atau bandingkan daftar penuh secara berkala.

Kode status: `401` token tidak sah/kedaluwarsa/dicabut; `403` klien dimatikan, sekolah nonaktif, atau paket tidak mencakup integrasi; `422` parameter salah; `429` melebihi 120 request per menit per klien (lihat header `Retry-After`).

Data pribadi yang dikirim hanya yang dibutuhkan E-Learning (nama, NISN/NIP, tanggal lahir, username, email). Password tidak pernah dikirim.

## 3. Webhook (kabar perubahan)

Bila admin mengisi **alamat penerima kabar**, SIAKAD mengirim `POST` ke alamat itu setiap ada perubahan pada: siswa, guru, rombel, rombel-siswa, jadwal, mapel, dan tahun ajaran (termasuk semesternya).

Isi (JSON):

```json
{ "id": "uuid-pengiriman", "event": "siswa.updated", "waktu": "2026-11-12T10:54:39+00:00", "sekolah": "smpn1", "data": { "id": 1, "nama": "..." } }
```

`event` = `<sumber>.<created|updated|deleted>` dengan `<sumber>` seperti nama endpoint (`siswa`, `guru`, `rombel`, `rombel-siswa`, `jadwal`, `mapel`, `tahun-ajaran`). Untuk `deleted`, `data` hanya `{"id": …}`. Bentuk `data` sama dengan isi endpoint API.

Header:

- `X-Siakad-Event`, `X-Siakad-Delivery` (sama dengan `id`; gunakan untuk membuang kiriman ganda), `X-Siakad-Timestamp` (detik UNIX).
- `X-Siakad-Signature: sha256=<hex>` = `HMAC-SHA256(kunci_tanda_tangan, "<X-Siakad-Timestamp>.<isi mentah>")`.

**Wajib** diverifikasi di sisi E-Learning (bandingkan dengan `hash_equals`, dan tolak bila timestamp lebih dari 5 menit dari waktu sekarang). Contoh PHP:

```php
$ts = $_SERVER['HTTP_X_SIAKAD_TIMESTAMP'];
$isi = file_get_contents('php://input');
$harapan = 'sha256=' . hash_hmac('sha256', $ts . '.' . $isi, $kunciTandaTangan);
if (! hash_equals($harapan, $_SERVER['HTTP_X_SIAKAD_SIGNATURE']) || abs(time() - (int) $ts) > 300) { http_response_code(401); exit; }
```

Balas dengan status 2xx bila diterima. Selain itu pengiriman dicoba ulang bertahap: 1 menit, 5 menit, 30 menit, 2 jam, 12 jam (total 6 percobaan). Riwayat setiap percobaan terlihat di menu Integrasi E-Learning. Alamat harus `https://` dan tidak boleh menuju jaringan internal.

## 4. Menjalankan antrian (untuk pengelola server)

Webhook dikirim lewat antrian database. Jalankan pekerja antrian di server: `php artisan queue:work --tries=6`.
Kunci Passport dibuat dengan `php artisan passport:keys` (disimpan di `storage/oauth-*.key`, jangan masuk git) atau lewat variabel `PASSPORT_PRIVATE_KEY` dan `PASSPORT_PUBLIC_KEY`.

## 5. Login tunggal (SSO) untuk guru dan siswa

Admin sekolah mengisi **alamat E-Learning** dan **alamat kembali** (redirect URL) di menu Integrasi E-Learning, lalu menyalakan tombol "Buka E-Learning". Guru dan siswa yang sudah masuk ke SIAKAD menekan tombol itu dan diteruskan ke E-Learning. Alurnya OAuth2 *authorization code*; layar izin dilewati karena E-Learning milik sekolah itu sendiri.

1. E-Learning mengarahkan pengguna ke `GET {alamat-sekolah}/oauth/authorize?client_id=<ID Klien>&redirect_uri=<alamat kembali>&response_type=code&scope=&state=<acak>`. Bila pengguna belum masuk, ia diminta login SIAKAD lalu dikembalikan otomatis.
2. SIAKAD mengarahkan balik ke `redirect_uri?code=...&state=...`. Periksa `state`.
3. E-Learning menukar kode (satu kali pakai) lewat server: `POST {alamat-sekolah}/oauth/token` dengan `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code`. Balasan: `access_token` (berlaku **15 menit**) dan `refresh_token` (berlaku 14 hari).
4. Perpanjang dengan `grant_type=refresh_token` + `refresh_token`, `client_id`, `client_secret`.
5. Ambil identitas: `GET {alamat-sekolah}/api/v1/me` dengan `Authorization: Bearer <access_token>`:

```json
{ "data": { "user_id": 12, "sekolah_id": 3, "sekolah": "smpn1", "nama": "Andi", "username": "andi", "email": "...",
            "peran": ["siswa"], "siswa_id": 5, "guru_id": null,
            "rombel": [ { "id": 2, "nama": "VII A", "tingkat": 7, "tahun_ajaran_id": 1 } ] } }
```

Untuk guru, `rombel` berisi rombel yang diajar pada semester aktif dan rombel yang diwalikan. Hanya akun **guru, wali kelas, dan siswa** yang boleh masuk lewat SSO. Token pengguna **tidak** bisa membaca API data induk (bagian 2), dan sebaliknya.

SSO ditolak (403) bila: SSO dimatikan, paket sekolah tidak mencakup integrasi, klien dipakai lewat alamat sekolah lain, atau pengguna bukan guru/siswa. Token pengguna berhenti berlaku bila SSO/sambungan dimatikan atau akun dinonaktifkan.
