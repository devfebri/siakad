<?php

/*
|--------------------------------------------------------------------------
| Isi menu Panduan
|--------------------------------------------------------------------------
| Satu artikel = satu keperluan ("Cara ..."). Ubah teks di sini tanpa menyentuh tampilan.
|
|   slug      : alamat artikel (unik, huruf kecil dan strip)
|   panel     : panel yang melihat artikel ini: admin, guru, siswa, ortu, bendahara, kepsek, platform, atau 'semua'
|   modul     : (opsional) kode modul paket; artikel hanya tampil bila sekolah punya modul itu. Array = cukup salah satu.
|   mulai     : (opsional) true = tampil di kotak "Mulai dari sini"
|   langkah   : daftar langkah; **tebal** untuk menyorot nama tombol/menu
|   tautan    : [nama rute, teks tombol] untuk membuka halaman yang dibahas ("{panel}/profile" dipakai untuk profil panel yang sedang aktif)
|
| Bahasa sengaja sederhana karena penggunanya banyak yang belum terbiasa dengan aplikasi.
*/
return [
    'kategori' => [
        'Mulai Cepat' => 'rocket-takeoff',
        'Data Sekolah' => 'database',
        'Akademik' => 'mortarboard',
        'Website & PPDB' => 'globe2',
        'Keuangan' => 'cash-stack',
        'Pengaturan Sistem' => 'gear',
        'Mengajar' => 'easel',
        'Belajar' => 'journal-bookmark',
        'Anak Saya' => 'person-hearts',
        'Memantau' => 'binoculars',
        'Kelola Platform' => 'buildings',
        'Bantuan' => 'life-preserver',
    ],

    'artikel' => [
        // ================================================================== ADMIN
        [
            'slug' => 'pengaturan-awal', 'judul' => 'Menyiapkan sekolah untuk pertama kali', 'kategori' => 'Mulai Cepat', 'ikon' => 'rocket-takeoff', 'panel' => ['admin'], 'mulai' => true,
            'ringkas' => 'Lima langkah singkat agar aplikasi siap dipakai guru, siswa, dan orang tua.',
            'langkah' => [
                'Buka menu **Pengaturan Awal**. Halaman ini menunjukkan langkah mana yang sudah selesai dan mana yang belum.',
                '**1. Identitas sekolah:** isi nama, slogan, dan logo sekolah.',
                '**2. Tahun ajaran & semester:** tentukan tahun ajaran yang sedang berjalan dan semester aktifnya.',
                '**3. Mata pelajaran & rombel:** ketik nama mata pelajaran dan nama kelas (rombel), satu per baris, lalu tekan **Tambahkan**.',
                '**4. Data guru** dan **5. Data siswa:** unduh template Excel, isi, lalu impor. Caranya ada di panduan "Memasukkan data dari Excel".',
                'Langkah boleh dilewati atau diulang kapan saja. Tidak ada yang salah urutan.',
            ],
            'tips' => ['Mulai dari langkah yang paling mudah. Data bisa dilengkapi sedikit demi sedikit.'],
            'tautan' => ['admin/setup', 'Buka Pengaturan Awal'],
        ],
        [
            'slug' => 'impor-excel', 'judul' => 'Memasukkan data guru, siswa, dan orang tua dari Excel', 'kategori' => 'Mulai Cepat', 'ikon' => 'file-earmark-excel', 'panel' => ['admin'], 'mulai' => true,
            'ringkas' => 'Unduh format, isi di Excel, unggah. Ratusan data masuk sekaligus.',
            'langkah' => [
                'Buka **Impor Data** (atau tombol **Impor Excel** di halaman Data Guru, Data Siswa, dan Orang Tua).',
                'Pilih jenisnya lalu tekan **Unduh Template**. File Excel akan terunduh.',
                'Buka file itu. Isi lembar **Data** mulai **baris ke-3**. Baris ke-2 yang berwarna abu-abu hanya contoh dan otomatis dilewati.',
                'Judul kolom berwarna **oranye** wajib diisi. Judul biru boleh dikosongkan. Aturan tiap kolom ada di lembar **Petunjuk**.',
                'Simpan file, lalu unggah di kotak **Berkas** pada halaman Impor Data.',
                'Sistem memeriksa dulu: berapa baris siap, dan baris mana bermasalah beserta alasannya. Belum ada data yang tersimpan pada tahap ini.',
                'Tekan **Impor**. Baris yang bermasalah dilewati, sisanya tersimpan. Perbaiki baris yang dilewati lalu impor lagi.',
            ],
            'tips' => [
                'Urutan yang disarankan: **siswa dulu, baru orang tua**, karena orang tua dihubungkan ke anaknya lewat NISN. Template orang tua sudah berisi lembar **Daftar Siswa** untuk menyalin NISN.',
                'Password awal akun hasil impor: guru **guru123**, siswa **siswa123**, orang tua **ortu123**. Minta mereka menggantinya.',
                'Maksimal 1.000 baris dan 2 MB per file. Lebih dari itu, pecah jadi beberapa file.',
            ],
            'tautan' => ['admin/impor', 'Buka Impor Data'],
        ],
        [
            'slug' => 'menambah-guru-siswa', 'judul' => 'Menambah atau mengubah satu guru atau siswa', 'kategori' => 'Data Sekolah', 'ikon' => 'person-plus', 'panel' => ['admin'],
            'ringkas' => 'Untuk data yang jumlahnya sedikit, tambah langsung lewat formulir.',
            'langkah' => [
                'Buka **Data Guru** atau **Data Siswa**.',
                'Tekan tombol tambah di kanan atas, isi formulir, lalu **Simpan**. Akun login dibuat otomatis (username = NIP/NISN, atau isi sendiri).',
                'Untuk mengubah, tekan tombol **Ubah** di baris orangnya. Untuk menghapus, tekan **Hapus** dan konfirmasi.',
                'Kolom pencarian di atas tabel membantu menemukan nama atau NISN dengan cepat.',
            ],
            'tips' => ['Password awal guru **guru123** dan siswa **siswa123**. Di formulir ubah, mengisi kolom password berarti mengganti password orang itu.'],
            'tautan' => ['admin/siswa', 'Buka Data Siswa'],
        ],
        [
            'slug' => 'orang-tua-akun', 'judul' => 'Membuat akun orang tua dan menghubungkannya ke anak', 'kategori' => 'Data Sekolah', 'ikon' => 'person-hearts', 'panel' => ['admin'], 'modul' => ['portal_orang_tua', 'keuangan'],
            'ringkas' => 'Orang tua bisa melihat data anak dan membayar tagihan setelah akunnya dihubungkan.',
            'langkah' => [
                'Buka menu **Orang Tua**.',
                'Tekan **Tambah Orang Tua**, isi nama, username, nomor HP, lalu pilih anaknya. Atau pakai **Impor Excel** untuk banyak orang tua sekaligus.',
                'Untuk menambah anak lain, tekan **Kelola anak** di baris orang tua tersebut.',
                'Beri tahu orang tua username dan password awalnya (**ortu123**).',
            ],
            'tips' => ['Isi **nomor HP** dengan benar supaya orang tua juga menerima pesan WhatsApp dari sekolah.'],
            'tautan' => ['admin/orang-tua', 'Buka Orang Tua'],
        ],
        [
            'slug' => 'pengguna-reset-password', 'judul' => 'Mengatur akun pengguna dan mengatur ulang password', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'people', 'panel' => ['admin'],
            'ringkas' => 'Jika ada yang lupa password, admin bisa mengembalikannya.',
            'langkah' => [
                'Buka menu **Pengguna**.',
                'Cari nama orangnya, lalu tekan tombol **Atur Ulang Password** di kolom Aksi.',
                'Untuk guru, siswa, dan orang tua, kosongkan isian baru agar password kembali ke password awal. Akun admin harus memilih password baru.',
                'Untuk memberi peran tambahan (misalnya seorang guru juga bendahara), ubah peran lewat tombol di kolom **Aksi** pada barisnya dan centang peran yang diperlukan.',
            ],
            'tips' => ['Satu orang boleh punya beberapa peran. Ia memilih panel kerjanya lewat menu profil di kanan atas.'],
            'tautan' => ['admin/pengguna', 'Buka Pengguna'],
        ],
        [
            'slug' => 'kelas-jadwal', 'judul' => 'Mengatur rombel, mata pelajaran, dan jadwal', 'kategori' => 'Akademik', 'ikon' => 'calendar3', 'panel' => ['admin'],
            'ringkas' => 'Kelas (rombel), mata pelajaran, dan jadwal pelajaran ada dalam satu menu.',
            'langkah' => [
                'Buka **Kelas & Jadwal**. Ada tiga tab: **Rombel**, **Mata Pelajaran**, dan **Jadwal Pelajaran**.',
                '**Rombel:** tambah kelas beserta tingkat dan wali kelasnya. Siswa ditempatkan di rombel lewat Data Siswa atau Impor.',
                '**Mata Pelajaran:** daftar pelajaran yang diajarkan. Guru dikaitkan ke mata pelajaran di Data Guru.',
                '**Jadwal Pelajaran:** pilih kelas, lalu tambah jam pelajaran (hari, mata pelajaran, guru, jam mulai dan selesai, ruang). Tampilan mingguan muncul di bawahnya.',
            ],
            'tips' => ['Jadwal mengikuti semester yang sedang aktif. Saat tahun ajaran baru dibuka, jadwal lama bisa disalin sebagai awal.'],
            'tautan' => ['admin/kelas', 'Buka Kelas & Jadwal'],
        ],
        [
            'slug' => 'tahun-ajaran-semester', 'judul' => 'Mengatur tahun ajaran, semester, dan KKM', 'kategori' => 'Akademik', 'ikon' => 'calendar-range', 'panel' => ['admin'],
            'ringkas' => 'Semua jadwal, absensi, dan nilai mengikuti semester yang sedang aktif.',
            'langkah' => [
                'Buka **Tahun Ajaran**.',
                'Pastikan ada tahun ajaran dengan semester **Ganjil** dan **Genap** beserta tanggalnya.',
                'Tandai semester yang sedang berjalan sebagai **aktif**. Hanya satu semester yang aktif pada satu waktu.',
                'Atur **KKM** dan **bobot nilai** (tugas, ulangan harian, UTS, UAS) di halaman yang sama.',
            ],
            'tips' => ['Ganti semester aktif hanya saat benar-benar pindah semester, karena jadwal, absensi, dan nilai ikut berpindah.'],
            'tautan' => ['admin/tahun-ajaran', 'Buka Tahun Ajaran'],
        ],
        [
            'slug' => 'proses-tahunan', 'judul' => 'Kenaikan kelas dan kelulusan di akhir tahun', 'kategori' => 'Akademik', 'ikon' => 'signpost-2', 'panel' => ['admin'],
            'ringkas' => 'Buka tahun ajaran baru, proses naik dan lulus, lalu aktifkan. Selama masih draf, semuanya bisa dibatalkan.',
            'langkah' => [
                'Buka **Proses Tahunan**.',
                'Tekan **Buka Tahun Ajaran Baru**. Rombel tahun lalu disalin (tanpa anggota) dan tahun ajaran baru berstatus **Draf**.',
                'Wali kelas mengusulkan siswa naik atau tinggal lewat menu **Usulan Kenaikan** mereka. Admin bisa mengubah hasilnya.',
                'Untuk tiap rombel, pilih **Tujuan naik** dan **Tujuan tinggal**, lalu tekan **Proses rombel**. Siswa tingkat akhir otomatis diluluskan dan masuk **Data Alumni**.',
                'Setelah semua rombel selesai, **aktifkan** tahun ajaran baru.',
            ],
            'tips' => ['Tahap ini bisa dibatalkan selama masih draf lewat **Batalkan semua proses**. Setelah diaktifkan, perubahan tidak bisa dibatalkan.'],
            'tautan' => ['admin/proses-tahunan', 'Buka Proses Tahunan'],
        ],
        [
            'slug' => 'absensi-admin', 'judul' => 'Melihat dan mencatat absensi', 'kategori' => 'Akademik', 'ikon' => 'clipboard2-check', 'panel' => ['admin'], 'modul' => 'absensi',
            'ringkas' => 'Catat kehadiran siswa per kelas dan lihat rekap bulanannya.',
            'langkah' => [
                'Buka **Absensi**, tab **Catat Absensi**.',
                'Pilih **Kelas** dan **Tanggal**. Daftar siswa muncul.',
                'Tandai tiap siswa: **H** Hadir, **I** Izin, **S** Sakit, atau **A** Alpa. Tombol **Semua hadir** mempercepat, lalu ubah yang tidak hadir saja.',
                'Simpan. Untuk laporan, buka tab **Rekap Bulanan**.',
            ],
            'tips' => ['Bila WhatsApp sekolah menyala, orang tua otomatis diberi tahu saat anaknya dicatat **Alpa**.'],
            'tautan' => ['admin/absensi', 'Buka Absensi'],
        ],
        [
            'slug' => 'nilai-rapor-admin', 'judul' => 'Memasukkan nilai dan mencetak rapor', 'kategori' => 'Akademik', 'ikon' => 'journal-check', 'panel' => ['admin'], 'modul' => 'nilai_rapor',
            'ringkas' => 'Nilai dimasukkan per kelas, mata pelajaran, dan jenis penilaian. Rapor dicetak per siswa.',
            'langkah' => [
                'Buka **Nilai & Rapor**, tab **Input Nilai**.',
                'Pilih **Kelas**, **Mata Pelajaran**, dan **Jenis Penilaian**. Daftar siswa muncul.',
                'Isi kolom **Nilai (0–100)** tiap siswa, lalu simpan.',
                'Untuk rapor, buka tab **Rapor Siswa**, pilih kelas dan siswa, lalu tekan **Buka Rapor**. Dari sana bisa dicetak.',
            ],
            'tips' => ['Nilai akhir dihitung dari bobot tiap jenis penilaian yang diatur di menu **Tahun Ajaran**.'],
            'tautan' => ['admin/nilai', 'Buka Nilai & Rapor'],
        ],
        [
            'slug' => 'berita-pengumuman', 'judul' => 'Menulis berita, pengumuman, agenda, dan galeri', 'kategori' => 'Website & PPDB', 'ikon' => 'newspaper', 'panel' => ['admin'], 'modul' => 'konten_web',
            'ringkas' => 'Isi website sekolah diperbarui dari menu Konten Web.',
            'langkah' => [
                'Buka **Berita**, **Pengumuman**, **Agenda**, atau **Galeri** sesuai kebutuhan.',
                'Tekan tombol tambah, isi judul dan isinya. Untuk berita, pilih kategori dan unggah gambar.',
                'Simpan. Tulisan langsung tampil di website sekolah.',
                '**Unduhan** dipakai untuk berkas yang boleh diunduh pengunjung, misalnya formulir atau brosur.',
            ],
            'tips' => ['Gambar berita sebaiknya berukuran lebar, supaya tampil rapi di halaman depan.'],
            'tautan' => ['admin/berita', 'Buka Berita'],
        ],
        [
            'slug' => 'ppdb-penerimaan', 'judul' => 'Mengelola PPDB dan menjadikan pendaftar sebagai siswa', 'kategori' => 'Website & PPDB', 'ikon' => 'person-plus', 'panel' => ['admin'], 'modul' => 'ppdb', 'mulai' => true,
            'ringkas' => 'Dari pendaftaran online sampai pendaftar yang diterima menjadi siswa dan orang tua di aplikasi.',
            'langkah' => [
                'Buka **Pengaturan > PPDB** untuk mengatur tahun ajaran, tanggal buka dan tutup, lalu nyalakan pendaftaran. Calon siswa mendaftar di halaman **/ppdb** website sekolah.',
                'Di menu **PPDB**, tekan **Detail** pada pendaftar. Periksa datanya, ubah **Status** menjadi **Diterima** atau **Ditolak**, lalu **Simpan Status**.',
                'Untuk pendaftar yang **Diterima**, tekan **Jadikan Siswa**. Lengkapi **agama**, **NISN** (boleh kosong), dan **rombel**.',
                'Lihat kotak **Yang akan dibuat** di sebelah kanan: akun siswa, dan akun orang tua (baru, atau dihubungkan ke orang tua yang sudah ada). Tekan **Jadikan Siswa** untuk menyimpan.',
                'Untuk banyak pendaftar sekaligus, pakai tombol **Jadikan Siswa Semua yang Diterima** di halaman PPDB. Yang datanya belum lengkap dilewati dan dilaporkan.',
            ],
            'tips' => [
                'Bila NISN kosong, dipakai nomor pendaftaran sebagai pengganti sementara. Ganti di **Data Siswa** setelah NISN resmi keluar.',
                'Kakak-adik dengan nama dan nomor HP orang tua yang sama dihubungkan ke **satu** akun orang tua.',
                'Pendaftar yang sudah dijadikan siswa tidak bisa dikonversi dua kali.',
            ],
            'tautan' => ['admin/ppdb', 'Buka PPDB'],
        ],
        [
            'slug' => 'pengaturan-situs-tema', 'judul' => 'Mengubah identitas, tampilan, dan warna aplikasi', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'palette', 'panel' => ['admin'],
            'ringkas' => 'Nama sekolah, logo, kontak, sambutan kepala sekolah, dan tema warna diatur di satu tempat.',
            'langkah' => [
                'Buka **Pengaturan**.',
                'Tab **Identitas Sekolah**: nama, slogan, logo, dan **Tema Warna** (pilih satu, seluruh aplikasi berganti warna).',
                'Tab **Kontak & Media Sosial**: alamat, telepon, WhatsApp, dan peta.',
                'Tab **Beranda & Sambutan**: pilih **Tema Tampilan Website** (Klasik, Portal, atau Modern), judul utama, foto, dan sambutan kepala sekolah.',
                'Tekan **Simpan** di tiap tab.',
            ],
            'tips' => ['Tema website bisa diganti kapan saja tanpa kehilangan data.'],
            'tautan' => ['admin/pengaturan', 'Buka Pengaturan'],
        ],
        [
            'slug' => 'konsultasi-online', 'judul' => 'Melayani konsultasi online (chat)', 'kategori' => 'Website & PPDB', 'ikon' => 'chat-dots', 'panel' => ['admin'], 'modul' => 'konsultasi_online',
            'ringkas' => 'Pengunjung website bisa bertanya lewat chat. Petugas menjawab dari menu ini.',
            'langkah' => [
                'Buka **Konsultasi Online**. Tekan **Status saya: Online** agar chat baru dibagikan kepada Anda.',
                'Chat yang menunggu muncul di daftar. Tekan untuk mengambil, lalu balas.',
                'Atur jam layanan, pertanyaan yang sering diajukan (FAQ), dan balasan cepat di **Pengaturan** menu yang sama.',
            ],
            'tips' => ['Di luar jam layanan, chatbot menjawab pertanyaan umum dan pengunjung bisa meninggalkan pesan.'],
            'tautan' => ['admin/chat', 'Buka Konsultasi Online'],
        ],
        [
            'slug' => 'keuangan-tagihan', 'judul' => 'Membuat tagihan SPP dan mencatat pembayaran', 'kategori' => 'Keuangan', 'ikon' => 'cash-stack', 'panel' => ['admin'], 'modul' => 'keuangan', 'mulai' => true,
            'ringkas' => 'Atur jenis biaya sekali, buat tagihan untuk satu kelas atau semua siswa, lalu catat yang membayar.',
            'langkah' => [
                'Buka **Keuangan & SPP**, tab **Jenis Biaya**. Tambah biaya (misalnya **SPP** Rp 150.000, bulanan) dan biaya sekali bayar (uang gedung, seragam).',
                'Buka tab **Tagihan**, tekan **Buat Tagihan**. Pilih jenis biaya, kelas (atau semua), bulan mulai, dan berapa bulan.',
                'Tagihan dibuat untuk **siswa aktif yang sudah punya kelas**. Tagihan yang sudah ada tidak dibuat dua kali, jadi aman diulang.',
                'Saat orang tua membayar tunai atau transfer, tekan **Bayar** di baris tagihannya. Isi jumlah (boleh dicicil), cara bayar, lalu simpan.',
                'Setelah tersimpan, tekan **Unduh kuitansi** untuk mencetak bukti pembayaran.',
                'Salah catat? Buka tab **Pembayaran**, tekan tombol batal di barisnya, isi alasan. Tagihan kembali seperti semula dan riwayatnya tetap tersimpan.',
            ],
            'tips' => ['Tab **Ringkasan** menampilkan total yang belum dibayar, siswa yang menunggak, dan pembayaran terbaru.', 'Bendahara memakai halaman yang sama di panel Bendahara.'],
            'tautan' => ['admin/keuangan', 'Buka Keuangan'],
        ],
        [
            'slug' => 'bayar-online-spp', 'judul' => 'Mengaktifkan pembayaran SPP online untuk orang tua', 'kategori' => 'Keuangan', 'ikon' => 'credit-card', 'panel' => ['admin'], 'modul' => 'keuangan',
            'ringkas' => 'Orang tua membayar dari ponsel (virtual account, QRIS, e-wallet). Uang masuk langsung ke akun gerbang milik sekolah.',
            'langkah' => [
                'Daftar dulu di **Midtrans** atau **Xendit** atas nama sekolah, lalu ambil kunci API dari dashboard mereka.',
                'Buka **Keuangan & SPP**, tab **Pembayaran Online**.',
                'Pilih **Gerbang**, isi kunci, lalu salin **alamat notifikasi** yang tampil di halaman itu ke dashboard Midtrans/Xendit.',
                'Pilih siapa yang menanggung **biaya admin** gerbang: sekolah atau orang tua, lengkap dengan besarnya.',
                'Tekan **Simpan Pengaturan**. Mulai saat itu tombol **Bayar Sekarang** muncul di halaman Tagihan orang tua.',
            ],
            'tips' => ['Kunci tersimpan terenkripsi dan tidak pernah ditampilkan utuh lagi. Mengosongkan kolom kunci berarti kunci lama tidak diubah.', 'Bendahara menerima email setiap ada pembayaran online masuk.'],
            'tautan' => ['admin/keuangan/online', 'Buka Pembayaran Online'],
        ],
        [
            'slug' => 'notifikasi-whatsapp', 'judul' => 'Mengirim pemberitahuan lewat WhatsApp', 'kategori' => 'Keuangan', 'ikon' => 'whatsapp', 'panel' => ['admin'], 'modul' => 'notifikasi_wa',
            'ringkas' => 'Orang tua dikabari otomatis: siswa alpa, tagihan baru, pengingat jatuh tempo, dan pembayaran diterima.',
            'langkah' => [
                'Daftar di layanan pengirim WhatsApp (**Fonnte**, **Wablas**, atau **WhatsApp Cloud API**) dan ambil tokennya.',
                'Buka **Notifikasi WhatsApp**. Pilih layanan, isi kunci, lalu centang **Nyalakan notifikasi WhatsApp**.',
                'Di bagian **Pesan yang dikirim**, hidupkan kejadian yang diinginkan. Kalimat boleh diubah; kata seperti `{nama_siswa}` otomatis terisi.',
                'Gunakan **Coba kirim ke nomor Anda** untuk memastikan semuanya tersambung.',
                'Pantau hasilnya di **Riwayat pesan** (terkirim, antri, atau gagal beserta alasannya).',
            ],
            'tips' => ['Pastikan **nomor HP orang tua** terisi di data orang tua.', '**Batas kirim per menit** melindungi nomor dari pemblokiran. Pesan berlebih menunggu giliran, tidak hilang.', 'Pesan yang sama tidak pernah dikirim dua kali.'],
            'tautan' => ['admin/wa', 'Buka Notifikasi WhatsApp'],
        ],
        [
            'slug' => 'langganan-saya', 'judul' => 'Melihat dan memperpanjang langganan aplikasi', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'credit-card-2-front', 'panel' => ['admin'],
            'ringkas' => 'Cek masa aktif, ajukan perpanjangan, bayar invoice, dan unduh kuitansi.',
            'langkah' => [
                'Buka **Langganan Saya**. Bagian atas menunjukkan paket dan tanggal berakhir.',
                'Untuk memperpanjang atau pindah paket, di **Ajukan Perpanjangan / Ganti Paket** pilih durasi lalu tekan **Ajukan**. Invoice dibuat.',
                'Di daftar **Invoice**, tekan **Bayar Online** (bila tersedia) atau bayar lewat transfer sesuai petunjuk lalu hubungi pengelola.',
                'Setelah lunas, tekan **Kuitansi** untuk mengunduh bukti.',
            ],
            'tips' => ['Setelah masa aktif habis ada **masa tenggang 7 hari**. Sesudahnya situs menjadi **baca-saja**: data tetap aman dan bisa dilihat, tetapi tidak bisa diubah sampai diperpanjang.'],
            'tautan' => ['admin/langganan', 'Buka Langganan Saya'],
        ],
        [
            'slug' => 'domain-sendiri', 'judul' => 'Memakai alamat website sendiri (domain)', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'globe2', 'panel' => ['admin'],
            'ringkas' => 'Ganti alamat bawaan dengan domain milik sekolah, misalnya www.sekolahku.sch.id.',
            'langkah' => [
                'Beli domain di penyedia domain (misalnya PANDI untuk .sch.id).',
                'Buka **Domain Website**, tulis nama domain, lalu **Simpan Domain**.',
                'Di panel DNS penyedia domain, pasang dua rekaman yang tampil di halaman itu: **TXT** (bukti kepemilikan) dan **CNAME** atau **A** (arah ke server). Tombol **Salin** membantu.',
                'Tunggu beberapa menit sampai beberapa jam, lalu tekan **Cek Sekarang**. Sistem juga mengecek sendiri tiap jam.',
                'Setelah **Terverifikasi**, website bisa dibuka lewat domain tersebut.',
            ],
            'tips' => ['Jika bingung, kirim halaman ini kepada orang yang mengurus domain sekolah.', 'Alamat bawaan tetap bisa dipakai sebagai cadangan.'],
            'tautan' => ['admin/domain', 'Buka Domain Website'],
        ],
        [
            'slug' => 'backup-data', 'judul' => 'Menyimpan salinan (backup) data sekolah', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'archive', 'panel' => ['admin'],
            'ringkas' => 'Unduh satu file zip berisi seluruh data dan berkas sekolah untuk berjaga-jaga.',
            'langkah' => [
                'Buka **Backup Data**, tekan **Buat Backup**.',
                'Tunggu beberapa menit (tergantung banyaknya foto dan dokumen), lalu muat ulang halaman.',
                'Saat statusnya **Selesai**, tekan **Unduh** dan simpan file zip di tempat aman.',
            ],
            'tips' => ['File ini berisi **data pribadi** siswa dan guru. Jangan dibagikan. Kata sandi dan kunci pembayaran tidak ikut dimasukkan.', 'Batas pembuatan manual 3 kali per hari. Sistem juga membuat backup otomatis tiap minggu.'],
            'tautan' => ['admin/backup', 'Buka Backup Data'],
        ],
        [
            'slug' => 'integrasi-elearning', 'judul' => 'Menghubungkan aplikasi E-Learning', 'kategori' => 'Pengaturan Sistem', 'ikon' => 'plug', 'panel' => ['admin'], 'modul' => 'integrasi_elearning',
            'ringkas' => 'Guru dan siswa bisa masuk ke E-Learning dengan satu tombol, tanpa mengetik password lagi.',
            'langkah' => [
                'Buka **Integrasi E-Learning**, tekan **Nyalakan Sambungan**.',
                'Salin **ID Klien**, **Kunci Rahasia**, dan **Kunci Tanda Tangan Kabar**. Ketiganya hanya tampil sekali, jadi simpan baik-baik.',
                'Berikan kunci dan alamat API yang tampil kepada pengelola aplikasi E-Learning.',
                'Di **Langkah 4**, isi **alamat aplikasi E-Learning** dan **alamat kembali setelah masuk** (dari pengelola E-Learning), lalu centang **Tampilkan tombol "Buka E-Learning"**.',
                'Tekan **Cek Sekarang** untuk memastikan semuanya tersambung.',
            ],
            'tips' => ['Aplikasi E-Learning harus sudah tersedia terpisah. Menu ini hanya menyambungkan data dan login.', 'Kehilangan kunci? Tekan **Buat Ulang Kunci**. Kunci lama langsung berhenti berlaku.'],
            'tautan' => ['admin/integrasi', 'Buka Integrasi E-Learning'],
        ],

        // ================================================================== GURU
        [
            'slug' => 'guru-absensi', 'judul' => 'Mencatat absensi siswa', 'kategori' => 'Mengajar', 'ikon' => 'clipboard2-check', 'panel' => ['guru'], 'modul' => 'absensi', 'mulai' => true,
            'ringkas' => 'Pilih kelas dan tanggal, tandai kehadiran, selesai.',
            'langkah' => [
                'Buka menu **Absensi**.',
                'Pilih **Kelas** dan **Tanggal**. Daftar siswa muncul.',
                'Tandai tiap siswa: **H** Hadir, **I** Izin, **S** Sakit, **A** Alpa. Tombol **Semua hadir** mempercepat; ubah yang tidak hadir saja.',
                'Simpan. Untuk laporan per bulan, buka tab **Rekap Bulanan**.',
            ],
            'tips' => ['Bila sekolah memakai WhatsApp, orang tua otomatis diberi tahu saat anaknya dicatat **Alpa**.'],
            'tautan' => ['guru/absensi', 'Buka Absensi'],
        ],
        [
            'slug' => 'guru-nilai', 'judul' => 'Memasukkan nilai dan membuka rapor', 'kategori' => 'Mengajar', 'ikon' => 'journal-check', 'panel' => ['guru'], 'modul' => 'nilai_rapor', 'mulai' => true,
            'ringkas' => 'Nilai dimasukkan per kelas, mata pelajaran, dan jenis penilaian.',
            'langkah' => [
                'Buka **Nilai & Rapor**, tab **Input Nilai**.',
                'Pilih **Kelas**, **Mata Pelajaran**, dan **Jenis Penilaian** (tugas, ulangan harian, UTS, atau UAS).',
                'Isi kolom **Nilai (0–100)** tiap siswa lalu simpan.',
                'Untuk melihat rapor, buka tab **Rapor Siswa**, pilih kelas dan siswa, lalu **Buka Rapor**.',
            ],
            'tips' => ['Nilai di luar 0–100 ditolak. Nilai yang sudah tersimpan bisa diperbaiki kapan saja selama semesternya masih aktif.'],
            'tautan' => ['guru/nilai', 'Buka Nilai & Rapor'],
        ],
        [
            'slug' => 'guru-jadwal-kelas', 'judul' => 'Melihat jadwal mengajar dan daftar kelas', 'kategori' => 'Mengajar', 'ikon' => 'calendar3', 'panel' => ['guru'],
            'ringkas' => 'Jadwal mingguan Anda dan kelas yang Anda ajar.',
            'langkah' => [
                'Buka **Jadwal Mengajar** untuk melihat hari, jam, kelas, dan ruang.',
                'Buka **Kelas** untuk melihat daftar rombel, dan **Mata Pelajaran** untuk daftar pelajaran sekolah.',
                'Perubahan jadwal dilakukan oleh admin sekolah.',
            ],
            'tautan' => ['guru/jadwal', 'Buka Jadwal Mengajar'],
        ],
        [
            'slug' => 'guru-wali-kelas', 'judul' => 'Wali kelas: kelas wali dan usulan kenaikan', 'kategori' => 'Mengajar', 'ikon' => 'person-hearts', 'panel' => ['guru'],
            'ringkas' => 'Khusus guru yang menjadi wali kelas.',
            'langkah' => [
                'Buka **Kelas Wali** untuk melihat siswa di rombel yang Anda ampu.',
                'Di akhir tahun, buka **Usulan Kenaikan**. Untuk tiap siswa, pilih **naik** atau **tinggal** dan tulis catatan bila perlu.',
                'Admin yang memproses hasilnya di menu **Proses Tahunan**.',
            ],
            'tips' => ['Menu ini hanya muncul bila admin sudah menetapkan Anda sebagai wali kelas di menu **Kelas & Jadwal**.'],
            'tautan' => ['guru/wali', 'Buka Kelas Wali'],
        ],
        [
            'slug' => 'guru-elearning', 'judul' => 'Membuka E-Learning', 'kategori' => 'Mengajar', 'ikon' => 'box-arrow-up-right', 'panel' => ['guru'], 'modul' => 'integrasi_elearning',
            'ringkas' => 'Masuk ke aplikasi E-Learning tanpa mengetik password lagi.',
            'langkah' => [
                'Buka menu **Buka E-Learning** di sisi kiri.',
                'Anda otomatis masuk ke E-Learning dengan akun yang sama.',
            ],
            'tips' => ['Menu ini hanya muncul jika admin sekolah sudah mengaktifkannya. Belum muncul? Hubungi admin.'],
            'tautan' => ['elearning.buka', 'Buka E-Learning'],
        ],

        // ================================================================== SISWA
        [
            'slug' => 'siswa-jadwal-kelas', 'judul' => 'Melihat jadwal pelajaran dan kelas saya', 'kategori' => 'Belajar', 'ikon' => 'calendar3', 'panel' => ['siswa'], 'mulai' => true,
            'ringkas' => 'Jadwal minggu ini dan teman sekelas.',
            'langkah' => [
                'Buka **Jadwal Pelajaran** untuk melihat pelajaran tiap hari beserta jam dan gurunya.',
                'Buka **Kelas Saya** untuk melihat teman sekelas dan wali kelas.',
            ],
            'tautan' => ['siswa/jadwal', 'Buka Jadwal Pelajaran'],
        ],
        [
            'slug' => 'siswa-nilai-kehadiran', 'judul' => 'Melihat nilai dan kehadiran saya', 'kategori' => 'Belajar', 'ikon' => 'journal-check', 'panel' => ['siswa'], 'mulai' => true,
            'ringkas' => 'Pantau nilai dan rekap kehadiran per bulan.',
            'langkah' => [
                'Buka **Nilai Saya** untuk melihat nilai tiap mata pelajaran.',
                'Buka **Kehadiran Saya** untuk melihat rekap hadir, izin, sakit, dan alpa per bulan.',
                'Ada yang tidak sesuai? Sampaikan kepada wali kelas atau guru mata pelajaran.',
            ],
            'tips' => ['Menu Nilai dan Kehadiran hanya muncul bila sekolah mengaktifkannya.'],
            'tautan' => ['siswa/dashboard', 'Buka Dashboard'],
        ],
        [
            'slug' => 'siswa-elearning', 'judul' => 'Membuka E-Learning', 'kategori' => 'Belajar', 'ikon' => 'box-arrow-up-right', 'panel' => ['siswa'], 'modul' => 'integrasi_elearning',
            'ringkas' => 'Masuk ke aplikasi E-Learning tanpa mengetik password lagi.',
            'langkah' => ['Buka menu **Buka E-Learning** di sisi kiri.', 'Anda otomatis masuk ke E-Learning dengan akun yang sama.'],
            'tips' => ['Menu ini hanya muncul jika sekolah sudah mengaktifkannya.'],
            'tautan' => ['elearning.buka', 'Buka E-Learning'],
        ],

        // ================================================================== ORANG TUA
        [
            'slug' => 'ortu-anak-dashboard', 'judul' => 'Memilih anak dan membaca dashboard', 'kategori' => 'Anak Saya', 'ikon' => 'person-hearts', 'panel' => ['ortu'], 'mulai' => true,
            'ringkas' => 'Satu akun bisa memantau lebih dari satu anak.',
            'langkah' => [
                'Masuk memakai **username** dan **password** dari sekolah.',
                'Jika punya lebih dari satu anak, tekan nama anak di bagian atas halaman untuk berpindah.',
                'Dashboard menampilkan kelas, wali kelas, kehadiran bulan ini, jadwal hari ini, dan pengumuman sekolah.',
            ],
            'tips' => ['Belum ada anak yang tampil? Minta admin sekolah menghubungkan akun Anda dengan data anak.'],
            'tautan' => ['ortu/dashboard', 'Buka Dashboard'],
        ],
        [
            'slug' => 'ortu-kehadiran-nilai', 'judul' => 'Melihat kehadiran dan nilai anak', 'kategori' => 'Anak Saya', 'ikon' => 'clipboard2-check', 'panel' => ['ortu'], 'modul' => 'portal_orang_tua',
            'ringkas' => 'Pantau perkembangan anak dari rumah.',
            'langkah' => [
                'Buka **Kehadiran** untuk rekap hadir, izin, sakit, dan alpa per bulan.',
                'Buka **Nilai & Rapor** untuk nilai dan rapor anak.',
            ],
            'tautan' => ['ortu/kehadiran', 'Buka Kehadiran'],
        ],
        [
            'slug' => 'ortu-bayar-tagihan', 'judul' => 'Melihat dan membayar tagihan sekolah', 'kategori' => 'Anak Saya', 'ikon' => 'wallet2', 'panel' => ['ortu'], 'modul' => 'keuangan', 'mulai' => true,
            'ringkas' => 'Lihat tagihan, bayar dari ponsel, dan unduh kuitansi.',
            'langkah' => [
                'Buka **Tagihan & Pembayaran**. Bagian atas menunjukkan jumlah yang harus dibayar dan yang sudah dibayar.',
                'Jika tombol bayar online tersedia, **centang** tagihan yang ingin dibayar (boleh beberapa sekaligus, termasuk tagihan kakak dan adik).',
                'Periksa rincian di kotak bawah (total, dan biaya admin bila ada), lalu tekan **Bayar Sekarang**.',
                'Pilih cara bayar di halaman pembayaran (virtual account, QRIS, atau e-wallet) dan selesaikan pembayaran.',
                'Setelah itu Anda kembali ke halaman ini. Status tagihan menjadi **Lunas** otomatis dalam beberapa saat. Unduh bukti di **Riwayat Pembayaran > Unduh**.',
            ],
            'tips' => ['Belum selesai membayar? Tekan **Lanjutkan Pembayaran** di bagian atas halaman.', 'Jika tombol bayar tidak ada, pembayaran dilakukan langsung di sekolah (tunai atau transfer).'],
            'tautan' => ['ortu/tagihan', 'Buka Tagihan & Pembayaran'],
        ],

        // ================================================================== BENDAHARA
        [
            'slug' => 'bendahara-jenis-biaya', 'judul' => 'Mengatur jenis biaya', 'kategori' => 'Keuangan', 'ikon' => 'tags', 'panel' => ['bendahara'], 'mulai' => true,
            'ringkas' => 'Daftar biaya seperti SPP, uang gedung, dan seragam diatur sekali di sini.',
            'langkah' => [
                'Buka **Jenis Biaya**, tekan **Tambah Jenis Biaya**.',
                'Isi **nama biaya**, **nominal** (tanpa titik), dan **jenis**: bulanan atau sekali bayar.',
                'Simpan. Biaya yang sudah dipakai di tagihan tidak bisa dihapus, hanya dinonaktifkan.',
            ],
            'tautan' => ['bendahara/keuangan/biaya', 'Buka Jenis Biaya'],
        ],
        [
            'slug' => 'bendahara-tagihan', 'judul' => 'Membuat tagihan dan mencatat pembayaran', 'kategori' => 'Keuangan', 'ikon' => 'receipt', 'panel' => ['bendahara'], 'mulai' => true,
            'ringkas' => 'Tagihan dibuat massal; pembayaran dicatat satu per satu.',
            'langkah' => [
                'Buka **Tagihan**, tekan **Buat Tagihan**. Pilih jenis biaya, kelas (atau semua), bulan mulai, dan jumlah bulan.',
                'Tagihan hanya dibuat untuk **siswa aktif yang sudah punya kelas**, dan tidak dibuat dua kali untuk bulan yang sama.',
                'Saat ada yang membayar, tekan **Bayar** di baris tagihannya. Isi jumlah (boleh dicicil), cara bayar, catatan, lalu **Simpan**.',
                'Tekan **Unduh kuitansi** untuk mencetak bukti.',
                'Gunakan saringan **Lewat jatuh tempo** untuk melihat tunggakan.',
            ],
            'tips' => ['Jumlah bayar tidak bisa melebihi sisa tagihan.'],
            'tautan' => ['bendahara/keuangan/tagihan', 'Buka Tagihan'],
        ],
        [
            'slug' => 'bendahara-pembayaran-kuitansi', 'judul' => 'Riwayat pembayaran, kuitansi, dan koreksi', 'kategori' => 'Keuangan', 'ikon' => 'cash-coin', 'panel' => ['bendahara'],
            'ringkas' => 'Semua pembayaran tercatat. Salah catat dibatalkan, bukan dihapus.',
            'langkah' => [
                'Buka **Pembayaran** untuk daftar semua pembayaran (tunai, transfer, dan online).',
                'Tekan **Kuitansi** untuk mengunduh bukti pembayaran dalam PDF.',
                'Salah mencatat? Tekan tombol batal, isi **alasan**, lalu konfirmasi. Tagihan kembali ke kondisi sebelum dibayar.',
                'Pembayaran online dari orang tua masuk otomatis dan Anda diberi tahu lewat email.',
            ],
            'tips' => ['Riwayat yang dibatalkan tetap tersimpan sebagai catatan, jadi tidak ada uang yang "hilang" dari laporan.'],
            'tautan' => ['bendahara/keuangan/pembayaran', 'Buka Pembayaran'],
        ],
        [
            'slug' => 'bendahara-ringkasan', 'judul' => 'Membaca ringkasan keuangan', 'kategori' => 'Keuangan', 'ikon' => 'speedometer2', 'panel' => ['bendahara'],
            'ringkas' => 'Gambaran cepat kondisi tagihan sekolah.',
            'langkah' => [
                'Buka **Ringkasan**.',
                'Empat kotak atas: yang belum dibayar, jumlah siswa menunggak, pemasukan hari ini, dan pemasukan bulan ini.',
                'Di bawahnya ada pembayaran terbaru dan daftar tagihan yang lewat jatuh tempo.',
            ],
            'tautan' => ['bendahara/dashboard', 'Buka Ringkasan'],
        ],

        // ================================================================== KEPALA SEKOLAH
        [
            'slug' => 'kepsek-dashboard', 'judul' => 'Membaca dashboard kepala sekolah', 'kategori' => 'Memantau', 'ikon' => 'speedometer2', 'panel' => ['kepsek'], 'mulai' => true,
            'ringkas' => 'Pantau jumlah siswa, guru, dan kehadiran sekolah.',
            'langkah' => [
                'Buka **Dashboard** untuk ringkasan siswa, guru, dan grafik.',
                'Buka **Kehadiran** untuk rekap kehadiran per kelas.',
                'Buka **Rombel** untuk melihat kelas dan wali kelasnya, dan **Data Alumni** untuk lulusan.',
            ],
            'tips' => ['Panel ini hanya untuk memantau. Perubahan data dilakukan oleh admin sekolah.'],
            'tautan' => ['kepsek/dashboard', 'Buka Dashboard'],
        ],

        // ================================================================== SUPER ADMIN
        [
            'slug' => 'platform-sekolah-baru', 'judul' => 'Menambah sekolah baru', 'kategori' => 'Kelola Platform', 'ikon' => 'buildings', 'panel' => ['platform'], 'mulai' => true,
            'ringkas' => 'Dua cara: sekolah mendaftar sendiri, atau Anda yang membuatkan.',
            'langkah' => [
                '**Sekolah mendaftar sendiri:** arahkan ke halaman **/daftar**. Setelah mengisi data dan memverifikasi email, sekolah aktif dengan masa percobaan (trial) bila Anda menyalakannya di **Pengaturan Platform**.',
                '**Anda yang membuatkan:** buka **Sekolah**, tekan **Tambah Sekolah**, isi data sekolah dan akun admin pertamanya.',
                'Buat **invoice** di halaman detail sekolah, lalu konfirmasi pembayaran (atau biarkan sekolah membayar online) untuk mengaktifkan langganannya.',
                'Tombol **Masuk sebagai Admin** di halaman detail memudahkan membantu sekolah. Tindakan ini tercatat di log.',
            ],
            'tautan' => ['platform/sekolah', 'Buka Sekolah'],
        ],
        [
            'slug' => 'platform-paket-invoice', 'judul' => 'Mengatur paket, harga, dan invoice', 'kategori' => 'Kelola Platform', 'ikon' => 'box-seam', 'panel' => ['platform'],
            'ringkas' => 'Paket menentukan modul yang boleh dipakai sekolah.',
            'langkah' => [
                'Buka **Paket & Modul** untuk mengatur harga 1, 6, dan 12 bulan serta modul tiap paket.',
                'Buka **Invoice** untuk melihat semua tagihan sewa. Tekan **Lunas** untuk mengonfirmasi pembayaran manual, yang langsung membuat atau memperpanjang langganan.',
                'Unduh **Invoice** dan **Kuitansi** PDF dari tombol di tiap baris.',
            ],
            'tips' => ['Paket berubah langsung berlaku untuk menu sekolah yang bersangkutan.'],
            'tautan' => ['platform/paket', 'Buka Paket & Modul'],
        ],
        [
            'slug' => 'platform-pembayaran-sewa', 'judul' => 'Mengaktifkan pembayaran sewa online dan trial', 'kategori' => 'Kelola Platform', 'ikon' => 'credit-card', 'panel' => ['platform'],
            'ringkas' => 'Sekolah bisa membayar invoice lewat Midtrans atau Xendit.',
            'langkah' => [
                'Buka **Pengaturan Platform**.',
                'Di **Pembayaran online (sewa)**, pilih gerbang dan isi kuncinya. Salin **alamat notifikasi** yang tampil ke dashboard gerbang.',
                'Di **Percobaan gratis (trial)**, nyalakan dan atur lama hari bagi sekolah yang mendaftar sendiri.',
                'Di **Backup sekolah**, atur backup mingguan dan berapa banyak yang disimpan.',
            ],
            'tips' => ['Konfirmasi manual oleh Super Admin tetap tersedia. Kunci tersimpan terenkripsi dan tidak tampil utuh lagi.'],
            'tautan' => ['platform/pengaturan', 'Buka Pengaturan Platform'],
        ],
        [
            'slug' => 'platform-pantau-backup-log', 'judul' => 'Memantau sekolah, backup, dan log aktivitas', 'kategori' => 'Kelola Platform', 'ikon' => 'activity', 'panel' => ['platform'],
            'ringkas' => 'Lihat penggunaan tiap sekolah, simpan salinan data, dan telusuri siapa melakukan apa.',
            'langkah' => [
                '**Pemantauan:** jumlah siswa, guru, ukuran penyimpanan, login terakhir, akses API, dan status langganan tiap sekolah.',
                '**Backup:** buat backup manual per sekolah, unduh, atau hapus. Backup mingguan dan arsip sekolah nonaktif 90 hari berjalan otomatis.',
                '**Log Aktivitas:** saring menurut sekolah, pengguna, jenis tindakan, dan tanggal. Tombol **Unduh CSV** menyimpan hasilnya.',
            ],
            'tips' => ['Backup berisi data pribadi. Salin juga ke penyimpanan di luar server.'],
            'tautan' => ['platform/pemantauan', 'Buka Pemantauan'],
        ],
        [
            'slug' => 'platform-domain-sekolah', 'judul' => 'Membantu sekolah memasang domain sendiri', 'kategori' => 'Kelola Platform', 'ikon' => 'globe2', 'panel' => ['platform'],
            'ringkas' => 'Domain baru dipakai setelah terbukti milik sekolah.',
            'langkah' => [
                'Buka halaman detail sekolah, bagian **Domain kustom**.',
                'Berikan nilai **TXT** dan tujuan **CNAME/A** yang tampil kepada sekolah. Tujuannya diatur lewat `SEKOLAH_CNAME_TARGET` dan `SEKOLAH_SERVER_IP` di `.env`.',
                'Tekan **Cek DNS**. Bila DNS belum bisa dicek tetapi Anda yakin domain itu milik sekolah, tekan **Tandai terverifikasi**.',
            ],
            'tips' => ['Sertifikat SSL diurus di server (lihat README), bukan oleh aplikasi.'],
            'tautan' => ['platform/sekolah', 'Buka Sekolah'],
        ],

        // ================================================================== SEMUA PERAN
        [
            'slug' => 'ganti-password', 'judul' => 'Mengganti password sendiri', 'kategori' => 'Bantuan', 'ikon' => 'key', 'panel' => ['admin', 'guru', 'siswa', 'ortu'], 'mulai' => true,
            'ringkas' => 'Ganti password awal dengan yang hanya Anda yang tahu.',
            'langkah' => [
                'Tekan nama atau foto Anda di **kanan atas**, lalu pilih **Profil Saya**.',
                'Cari bagian **Ubah Password**.',
                'Isi password lama, lalu password baru (minimal 6 karakter) dua kali, dan simpan.',
            ],
            'tips' => ['Gunakan campuran huruf dan angka, dan jangan memberi tahu password kepada orang lain.'],
            'tautan' => ['{panel}/profile', 'Buka Profil Saya'],
        ],
        [
            'slug' => 'tidak-bisa-masuk', 'judul' => 'Tidak bisa masuk atau lupa password', 'kategori' => 'Bantuan', 'ikon' => 'question-circle', 'panel' => ['semua'],
            'ringkas' => 'Penyebab paling umum dan cara mengatasinya.',
            'langkah' => [
                'Masuk memakai **username**, bukan email. Huruf besar atau kecil pada username tidak dibedakan.',
                'Periksa password: huruf besar dan kecil dibedakan. Password awal diberikan oleh sekolah (guru **guru123**, siswa **siswa123**, orang tua **ortu123**).',
                'Lupa password? Hubungi **admin sekolah**; admin bisa mengembalikannya lewat menu **Pengguna**.',
                'Muncul pesan "belum memiliki peran" atau "dinonaktifkan"? Akun Anda belum diatur oleh admin. Hubungi admin sekolah.',
                'Admin sekolah yang lupa password: hubungi pengelola platform.',
            ],
            'tautan' => null,
        ],
        [
            'slug' => 'menu-tidak-muncul', 'judul' => 'Menu yang saya cari tidak ada', 'kategori' => 'Bantuan', 'ikon' => 'eye-slash', 'panel' => ['admin'],
            'ringkas' => 'Sebagian menu hanya tampil bila paket langganan sekolah mencakupnya.',
            'langkah' => [
                'Buka **Langganan Saya** dan lihat daftar **Modul**. Modul bergembok berarti belum termasuk paket sekolah.',
                'Menu seperti **Keuangan & SPP**, **PPDB**, **Notifikasi WhatsApp**, dan **Integrasi E-Learning** mengikuti modul itu.',
                'Untuk menambah modul, ajukan perpanjangan dengan paket yang lebih tinggi di **Langganan Saya**.',
                'Menu **Buka E-Learning** untuk guru dan siswa baru muncul setelah admin menyalakannya di **Integrasi E-Learning**.',
            ],
            'tautan' => ['admin/langganan', 'Buka Langganan Saya'],
        ],
    ],
];
