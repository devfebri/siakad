<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

use Illuminate\Http\Request;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ChatAdminController;
use App\Http\Controllers\ElearningController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainIzinController;
use App\Http\Controllers\BackupSayaController;
use App\Http\Controllers\IntegrasiController;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\PengaturanWaController;
use App\Http\Controllers\JenisBiayaController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KeuanganOnlineController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\PembayaranSimulasiController;
use App\Http\Controllers\SewaPdfController;
use App\Http\Controllers\Platform\PengaturanController as PlatformPengaturanController;
use App\Http\Controllers\Platform\PemantauanController as PlatformPemantauanController;
use App\Http\Controllers\Platform\BackupController as PlatformBackupController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatPengaturanController;
use App\Http\Controllers\ChatRiwayatController;
use App\Http\Controllers\ChatStatistikController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeritaController;
use App\Http\Controllers\CariController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MapelController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PpdbAdminController;
use App\Http\Controllers\PpdbPublikController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramUnggulanController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\ImpersonasiController;
use App\Http\Controllers\LanggananSayaController;
use App\Http\Controllers\Platform\AuthController as PlatformAuthController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\InvoiceController as PlatformInvoiceController;
use App\Http\Controllers\Platform\LanggananController as PlatformLanggananController;
use App\Http\Controllers\Platform\LogController as PlatformLogController;
use App\Http\Controllers\Platform\PaketController as PlatformPaketController;
use App\Http\Controllers\Platform\SekolahController as PlatformSekolahController;
use App\Http\Controllers\AlumniController;
use App\Http\Controllers\ProsesTahunanController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UsulanKenaikanController;
use App\Http\Controllers\SitusController;
use App\Http\Controllers\ImporController;
use App\Http\Controllers\KepsekController;
use App\Http\Controllers\OrangTuaController;
use App\Http\Controllers\OrtuController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\WaliKelasController;
use App\Http\Controllers\UnduhanController;
use App\Http\Controllers\UnduhanPublikController;
use App\Http\Controllers\TestimoniController;

// ---------------------------------------------------------------- situs publik
Route::get('/', [SitusController::class, 'beranda'])->name('beranda');
Route::get('/profil', [SitusController::class, 'profil'])->name('profil');
Route::get('/akademik', [SitusController::class, 'akademik'])->name('akademik');
Route::get('/berita', [SitusController::class, 'berita'])->name('berita');
Route::get('/berita/{slug}', [SitusController::class, 'beritaShow'])->name('berita.show');
Route::get('/pengumuman', [SitusController::class, 'pengumuman'])->name('pengumuman');
Route::get('/agenda', [SitusController::class, 'agenda'])->name('agenda');
Route::get('/galeri', [SitusController::class, 'galeri'])->name('galeri');
Route::get('/kontak', [SitusController::class, 'kontak'])->name('kontak');
Route::get('/unduhan', [UnduhanPublikController::class, 'index'])->name('unduhan');
Route::get('/unduhan/{unduhan}/unduh', [UnduhanPublikController::class, 'unduh'])->middleware('throttle:60,1')->name('unduhan.unduh');

Route::get('/ppdb', [PpdbPublikController::class, 'index'])->name('ppdb');
Route::post('/ppdb', [PpdbPublikController::class, 'store'])->middleware('throttle:10,1')->name('ppdb.store');
Route::get('/ppdb/sukses', [PpdbPublikController::class, 'sukses'])->name('ppdb.sukses');
Route::match(['get', 'post'], '/ppdb/cek', [PpdbPublikController::class, 'cek'])->middleware('throttle:20,1')->name('ppdb.cek');

// Konsultasi online (widget chat, jQuery AJAX + polling). Pengunjung dikenali dari token sesi.
Route::prefix('chat')->name('chat.')->group(function () {
    Route::post('/start', [ChatController::class, 'start'])->middleware('throttle:6,1')->name('start');
    Route::post('/send', [ChatController::class, 'send'])->middleware('throttle:40,1')->name('send');
    Route::get('/poll', [ChatController::class, 'poll'])->middleware('throttle:180,1')->name('poll');
    Route::get('/status', [ChatController::class, 'status'])->middleware('throttle:60,1')->name('status');
    Route::get('/faq', [ChatController::class, 'faq'])->middleware('throttle:60,1')->name('faq');
    Route::post('/faq', [ChatController::class, 'pilihFaq'])->middleware('throttle:40,1')->name('faq.pilih');
    Route::post('/leave', [ChatController::class, 'leave'])->middleware('throttle:10,1')->name('leave');
    Route::post('/upload', [ChatController::class, 'upload'])->middleware('throttle:15,1')->name('upload');
    Route::get('/file/{attachment}', [ChatController::class, 'file'])->middleware('signed')->name('file');
    Route::get('/riwayat/{token}', [ChatRiwayatController::class, 'show'])->middleware('throttle:60,1')->name('riwayat');
    Route::get('/export/{token}', [ChatRiwayatController::class, 'export'])->middleware('throttle:20,1')->name('export');
    Route::post('/rate', [ChatRiwayatController::class, 'rate'])->middleware('throttle:10,1')->name('rate');
    Route::post('/typing', [ChatController::class, 'typing'])->middleware('throttle:60,1')->name('typing');
    Route::post('/end', [ChatController::class, 'end'])->middleware('throttle:10,1')->name('end');
});

// Alamat lama (sebelum redesign) dialihkan ke alamat baru agar tautan lama tidak mati.
Route::permanentRedirect('/artikel', '/berita');
Route::get('/artikel/kategori/{kategori}', fn (string $kategori) => redirect()->route('berita', ['kategori' => $kategori], 301));
Route::get('/artikel/{slug}', fn (string $slug) => redirect()->route('berita.show', $slug, 301))->name('site.single.post');
foreach (['tentangkami' => 'tentangkami', 'visimisi' => 'visimisi', 'sejarah' => 'sejarah', 'sosialmedia' => 'sosialmedia', 'administrasikantorsekolah' => 'administrasi', 'perpustakaan' => 'perpustakaan', 'labkomputer' => 'labkom', 'strukturorganisasi' => 'struktur', 'guru' => 'guru'] as $lama => $jangkar) {
    Route::get("/$lama", fn () => redirect(route('profil').'#'.$jangkar, 301));
}
Route::permanentRedirect('/hubungikami', '/kontak');
Route::permanentRedirect('/gallery', '/galeri');

// Route::post('/register', [AuthController::class, 'postRegister']);
// Route::get('/register', [AuthController::class, 'getRegister'])->name('register');
// Halaman produk dan pendaftaran sekolah mandiri (tidak terikat sekolah). Di domain utama, "/" menampilkan halaman produk.
Route::get('/produk', [ProdukController::class, 'landing'])->name('produk');
if ($domainUtama = config('sekolah.domain_utama')) {
    Route::domain($domainUtama)->get('/', [ProdukController::class, 'landing']);
    Route::domain('www.'.$domainUtama)->get('/', [ProdukController::class, 'landing']);
}
Route::get('/daftar', [ProdukController::class, 'formDaftar'])->name('daftar');
Route::get('/daftar/cek-subdomain', [ProdukController::class, 'cekSubdomain'])->middleware('throttle:60,1')->name('daftar.cek-subdomain');
Route::post('/daftar', [ProdukController::class, 'simpan'])->middleware('throttle:5,60')->name('daftar.simpan');
Route::get('/daftar/cek-email', [ProdukController::class, 'cekEmail'])->name('daftar.cek-email');
Route::get('/daftar/verifikasi/{token}', [ProdukController::class, 'verifikasi'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:20,1')->name('daftar.verifikasi');

// Endpoint "ask" Caddy on-demand TLS: hanya domain terdaftar dan terverifikasi yang boleh mendapat sertifikat SSL.
Route::get('/_domain/izin', DomainIzinController::class)->middleware('throttle:120,1')->name('domain.izin');

// Halaman bayar tiruan (gerbang "Simulasi", hanya lokal/uji).
Route::get('/pembayaran/simulasi/{orderId}', [PembayaranSimulasiController::class, 'tampil'])->name('pembayaran.simulasi');
Route::post('/pembayaran/simulasi/{orderId}', [PembayaranSimulasiController::class, 'kirim'])->name('pembayaran.simulasi.kirim');
Route::get('/pembayaran/simulasi-spp/{orderId}', [PembayaranSimulasiController::class, 'tampilSpp'])->name('pembayaran.simulasi-spp');
Route::post('/pembayaran/simulasi-spp/{orderId}', [PembayaranSimulasiController::class, 'kirimSpp'])->name('pembayaran.simulasi-spp.kirim');

// Panduan pemakaian untuk semua peran (isi disaring menurut panel dan paket).
Route::get('/panduan', [PanduanController::class, 'index'])->middleware('auth')->name('panduan');
Route::get('/panduan/{slug}', [PanduanController::class, 'show'])->where('slug', '[a-z0-9-]+')->middleware('auth')->name('panduan.show');

Route::get('/elearning', [ElearningController::class, 'buka'])->middleware('auth')->name('elearning.buka');
Route::post('/ganti-panel/{panel}', [AuthController::class, 'gantiPanel'])->middleware('auth')->name('panel.ganti');
Route::post('/login', [AuthController::class, 'postLogin'])->middleware('throttle:10,1');
Route::get('/login', [AuthController::class, 'getLogin'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout.post');


//route admin
Route::prefix('admin')->middleware('auth', 'checkRole:admin')->group(function () {
    Route::get('/langganan', [LanggananSayaController::class, 'index'])->name('admin/langganan');
    Route::post('/langganan/ajukan', [LanggananSayaController::class, 'ajukan'])->name('admin/langganan.ajukan');
    Route::post('/langganan/invoice/{invoice}/bayar', [LanggananSayaController::class, 'bayar'])->whereNumber('invoice')->name('admin/langganan.bayar');
    Route::get('/langganan/invoice/{invoice}/pdf', [SewaPdfController::class, 'invoice'])->whereNumber('invoice')->name('admin/langganan.invoice-pdf');
    Route::get('/langganan/invoice/{invoice}/kuitansi', [SewaPdfController::class, 'kuitansi'])->whereNumber('invoice')->name('admin/langganan.kuitansi-pdf');
    Route::get('/backup', [BackupSayaController::class, 'index'])->name('admin/backup');
    Route::post('/backup', [BackupSayaController::class, 'buat'])->name('admin/backup.buat');
    Route::get('/backup/{backup}/unduh', [BackupSayaController::class, 'unduh'])->whereNumber('backup')->name('admin/backup.unduh');
    Route::get('/domain', [DomainController::class, 'index'])->name('admin/domain');
    Route::post('/domain', [DomainController::class, 'simpan'])->name('admin/domain.simpan');
    Route::post('/domain/periksa', [DomainController::class, 'periksa'])->name('admin/domain.periksa');
    Route::delete('/domain', [DomainController::class, 'hapus'])->name('admin/domain.hapus');
    Route::get('/wa', [PengaturanWaController::class, 'index'])->name('admin/wa');
    Route::put('/wa', [PengaturanWaController::class, 'simpan'])->name('admin/wa.simpan');
    Route::post('/wa/uji', [PengaturanWaController::class, 'uji'])->name('admin/wa.uji');
    Route::get('/integrasi', [IntegrasiController::class, 'index'])->name('admin/integrasi');
    Route::post('/integrasi/aktifkan', [IntegrasiController::class, 'aktifkan'])->name('admin/integrasi.aktifkan');
    Route::post('/integrasi/nonaktifkan', [IntegrasiController::class, 'nonaktifkan'])->name('admin/integrasi.nonaktifkan');
    Route::put('/integrasi/webhook', [IntegrasiController::class, 'simpanWebhook'])->name('admin/integrasi.webhook');
    Route::put('/integrasi/sso', [IntegrasiController::class, 'simpanSso'])->name('admin/integrasi.sso');
    Route::post('/integrasi/uji', [IntegrasiController::class, 'uji'])->name('admin/integrasi.uji');
    Route::get('/chat', [ChatAdminController::class, 'index'])->name('admin/chat');
    Route::get('/chat/pengaturan', [ChatPengaturanController::class, 'index'])->name('admin/chat.pengaturan');
    Route::put('/chat/pengaturan', [ChatPengaturanController::class, 'simpan'])->name('admin/chat.pengaturan.simpan');
    Route::get('/chat/statistik', [ChatStatistikController::class, 'index'])->name('admin/chat.statistik');
    Route::get('/chat/balasan/data', [ChatPengaturanController::class, 'balasanData'])->name('admin/chat/balasan.data');
    Route::post('/chat/balasan', [ChatPengaturanController::class, 'balasanStore'])->name('admin/chat/balasan.store');
    Route::put('/chat/balasan/{balasan}', [ChatPengaturanController::class, 'balasanUpdate'])->name('admin/chat/balasan.update');
    Route::delete('/chat/balasan/{balasan}', [ChatPengaturanController::class, 'balasanDestroy'])->name('admin/chat/balasan.destroy');
    Route::get('/chat/faq/data', [ChatPengaturanController::class, 'faqData'])->name('admin/chat/faq.data');
    Route::post('/chat/faq', [ChatPengaturanController::class, 'faqStore'])->name('admin/chat/faq.store');
    Route::put('/chat/faq/{faq}', [ChatPengaturanController::class, 'faqUpdate'])->name('admin/chat/faq.update');
    Route::delete('/chat/faq/{faq}', [ChatPengaturanController::class, 'faqDestroy'])->name('admin/chat/faq.destroy');
    Route::get('/chat/riwayat', [ChatAdminController::class, 'riwayat'])->name('admin/chat.riwayat');
    Route::get('/chat/riwayat/export', [ChatAdminController::class, 'riwayatExport'])->name('admin/chat.riwayat.export');
    Route::get('/chat/riwayat/{session}', [ChatAdminController::class, 'riwayatShow'])->name('admin/chat.riwayat.show');
    Route::get('/chat/{session}/pdf', [ChatAdminController::class, 'pdf'])->name('admin/chat.pdf');
    Route::get('/chat/queue', [ChatAdminController::class, 'queue'])->name('admin/chat.queue');
    Route::post('/chat/status', [ChatAdminController::class, 'status'])->name('admin/chat.status');
    Route::post('/chat/{session}/take', [ChatAdminController::class, 'take'])->name('admin/chat.take');
    Route::post('/chat/{session}/transfer', [ChatAdminController::class, 'transfer'])->name('admin/chat.transfer');
    Route::get('/chat/{session}/poll', [ChatAdminController::class, 'poll'])->name('admin/chat.poll');
    Route::post('/chat/{session}/reply', [ChatAdminController::class, 'reply'])->name('admin/chat.reply');
    Route::post('/chat/{session}/upload', [ChatAdminController::class, 'upload'])->name('admin/chat.upload');
    Route::get('/chat/file/{attachment}', [ChatAdminController::class, 'file'])->name('admin/chat.file');
    Route::post('/chat/{session}/typing', [ChatAdminController::class, 'typing'])->name('admin/chat.typing');
    Route::post('/chat/{session}/close', [ChatAdminController::class, 'close'])->name('admin/chat.close');
    Route::get('/absensi', [AbsensiController::class, 'index'])->name('admin/absensi');
    Route::get('/absensi/siswa', [AbsensiController::class, 'siswa'])->name('admin/absensi.siswa');
    Route::post('/absensi', [AbsensiController::class, 'simpan'])->name('admin/absensi.simpan');
    Route::get('/absensi/rekap', [AbsensiController::class, 'rekap'])->name('admin/absensi.rekap');
    Route::get('/nilai', [NilaiController::class, 'index'])->name('admin/nilai');
    Route::get('/nilai/siswa', [NilaiController::class, 'siswa'])->name('admin/nilai.siswa');
    Route::post('/nilai', [NilaiController::class, 'simpan'])->name('admin/nilai.simpan');
    Route::get('/nilai/daftar-siswa', [NilaiController::class, 'daftarSiswa'])->name('admin/nilai.daftar');
    Route::get('/rapor/{siswa}', [NilaiController::class, 'rapor'])->name('admin/rapor');
    Route::get('/proses-tahunan', [ProsesTahunanController::class, 'index'])->name('admin/proses-tahunan');
    Route::post('/proses-tahunan/buka', [ProsesTahunanController::class, 'buka'])->name('admin/proses-tahunan.buka');
    Route::post('/proses-tahunan/rombel/{kelas}', [ProsesTahunanController::class, 'prosesRombel'])->name('admin/proses-tahunan.rombel');
    Route::post('/proses-tahunan/siswa/{siswa}', [ProsesTahunanController::class, 'prosesSiswa'])->name('admin/proses-tahunan.siswa');
    Route::delete('/proses-tahunan/siswa/{siswa}', [ProsesTahunanController::class, 'batalSiswa'])->name('admin/proses-tahunan.siswa.batal');
    Route::delete('/proses-tahunan/semua', [ProsesTahunanController::class, 'batalSemua'])->name('admin/proses-tahunan.batal-semua');
    Route::delete('/proses-tahunan/tahun', [ProsesTahunanController::class, 'batalTahun'])->name('admin/proses-tahunan.batal-tahun');
    Route::post('/proses-tahunan/aktifkan', [ProsesTahunanController::class, 'aktifkan'])->name('admin/proses-tahunan.aktifkan');
    Route::get('/alumni/export', [AlumniController::class, 'export'])->name('admin/alumni.export');
    Route::ajaxCrud('admin/alumni', AlumniController::class, 'alumni', true);
    Route::get('/setup', [SetupController::class, 'index'])->name('admin/setup');
    Route::put('/setup/identitas', [SetupController::class, 'identitas'])->name('admin/setup.identitas');
    Route::put('/setup/akademik', [SetupController::class, 'akademik'])->name('admin/setup.akademik');
    Route::post('/setup/struktur', [SetupController::class, 'struktur'])->name('admin/setup.struktur');
    Route::post('/setup/selesai', [SetupController::class, 'tandaiSelesai'])->name('admin/setup.selesai');
    Route::get('/impor', [ImporController::class, 'index'])->name('admin/impor');
    Route::get('/impor/{jenis}/template', [ImporController::class, 'template'])->name('admin/impor.template');
    Route::post('/impor/{jenis}/periksa', [ImporController::class, 'periksa'])->name('admin/impor.periksa');
    Route::post('/impor/{jenis}/simpan', [ImporController::class, 'simpan'])->name('admin/impor.simpan');
    Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index'])->name('admin/tahun-ajaran');
    Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store'])->name('admin/tahun-ajaran.store');
    Route::put('/tahun-ajaran/bobot', [TahunAjaranController::class, 'updateBobot'])->name('admin/tahun-ajaran.bobot');
    Route::put('/tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('admin/tahun-ajaran.update');
    Route::delete('/tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'destroy'])->name('admin/tahun-ajaran.destroy');
    Route::put('/semester/{semester}', [TahunAjaranController::class, 'updateSemester'])->name('admin/semester.update');
    Route::post('/semester/{semester}/aktifkan', [TahunAjaranController::class, 'aktifkan'])->name('admin/semester.aktifkan');
    Route::get('/cari', CariController::class)->name('admin/cari');
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('admin/pengaturan');
    Route::put('/pengaturan/situs/{group}', [PengaturanController::class, 'updateSitus'])->name('admin/pengaturan.situs');
    Route::put('/pengaturan/{pengaturan}', [PengaturanController::class, 'update'])->name('admin/pengaturan.update');
    Route::ajaxCrud('admin/berita', BeritaController::class, 'berita', false, [], true);
    Route::ajaxCrud('admin/guru', GuruController::class, 'guru');
    Route::ajaxCrud('admin/siswa', SiswaController::class, 'siswa');
    Route::ajaxCrud('admin/gallery', GalleryController::class, 'gallery', false, ['update']);
    Route::ajaxCrud('admin/unduhan', UnduhanController::class, 'unduhan');
    Route::ajaxCrud('admin/pengumuman', PengumumanController::class, 'pengumuman');
    Route::ajaxCrud('admin/agenda', AgendaController::class, 'agenda');
    Route::ajaxCrud('admin/kategori', KategoriController::class, 'kategori');
    Route::post('/kelas/salin', [KelasController::class, 'salin'])->name('admin/kelas.salin');
    Route::post('/kelas/{kelas}/anggota', [KelasController::class, 'tambahAnggota'])->name('admin/kelas.anggota.tambah');
    Route::delete('/kelas/{kelas}/anggota/{siswa}', [KelasController::class, 'keluarkanAnggota'])->name('admin/kelas.anggota.keluar');
    Route::ajaxCrud('admin/kelas', KelasController::class, 'kelas');
    Route::ajaxCrud('admin/orang-tua', OrangTuaController::class, 'orangTua');
    Route::get('/orang-tua/{orangTua}', [OrangTuaController::class, 'show'])->whereNumber('orangTua')->name('admin/orang-tua.show');
    Route::post('/orang-tua/{orangTua}/anak', [OrangTuaController::class, 'tambahAnak'])->name('admin/orang-tua.anak.tambah');
    Route::delete('/orang-tua/{orangTua}/anak/{siswa}', [OrangTuaController::class, 'lepasAnak'])->name('admin/orang-tua.anak.lepas');
    Route::ajaxCrud('admin/mapel', MapelController::class, 'mapel');
    Route::ajaxCrud('admin/program', ProgramUnggulanController::class, 'program');
    Route::ajaxCrud('admin/testimoni', TestimoniController::class, 'testimoni');
    Route::get('/jadwal/grid', [JadwalController::class, 'grid'])->name('admin/jadwal.grid');
    Route::ajaxCrud('admin/jadwal', JadwalController::class, 'jadwal');
    Route::ajaxCrud('admin/pengguna', PenggunaController::class, 'pengguna');
    Route::put('/pengguna/{pengguna}/peran', [PenggunaController::class, 'updatePeran'])->name('admin/pengguna.peran');
    Route::put('/pengguna/{pengguna}/password', [PenggunaController::class, 'resetPassword'])->name('admin/pengguna.password');
    Route::get('/ppdb', [PpdbAdminController::class, 'index'])->name('admin/ppdb');
    Route::get('/ppdb/data', [PpdbAdminController::class, 'data'])->name('admin/ppdb.data');
    Route::get('/ppdb/export', [PpdbAdminController::class, 'export'])->name('admin/ppdb.export');
    Route::get('/ppdb/create', [PpdbAdminController::class, 'create'])->name('admin/ppdb.create');
    Route::post('/ppdb', [PpdbAdminController::class, 'store'])->name('admin/ppdb.store');
    Route::post('/ppdb/jadikan-siswa-massal', [PpdbAdminController::class, 'jadikanSiswaMassal'])->name('admin/ppdb.jadikan-massal');
    Route::get('/ppdb/{ppdb}', [PpdbAdminController::class, 'show'])->name('admin/ppdb.show');
    Route::get('/ppdb/{ppdb}/jadikan-siswa', [PpdbAdminController::class, 'jadikanSiswa'])->name('admin/ppdb.jadikan');
    Route::post('/ppdb/{ppdb}/jadikan-siswa', [PpdbAdminController::class, 'simpanSiswa'])->name('admin/ppdb.jadikan.simpan');
    Route::put('/ppdb/{ppdb}/status', [PpdbAdminController::class, 'updateStatus'])->name('admin/ppdb.status');
    Route::delete('/ppdb/{ppdb}', [PpdbAdminController::class, 'destroy'])->name('admin/ppdb.destroy');
    //data guru admin
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin/dashboard');
    Route::get('/profile', [ProfileController::class, 'index'])->name('admin/profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('admin/profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('admin/profile.password');




    // Route::post('postberita/tambah', [BeritaController::class, 'tambah']);



    Route::get('/kelas/open/{id}', [KelasController::class, 'open']);



    Route::post('/fitur/tambah', function (Request $req) {
        $data = new App\Timeline;
        $data->author = auth()->user()->name;
        $data->message = $req->message;
        $data->save();
        return redirect()->back();
    });



});



//rote guru
Route::prefix('guru')->middleware('auth', 'checkRole:guru,wali_kelas')->group(function () {
    Route::get('/absensi', [AbsensiController::class, 'index'])->name('guru/absensi');
    Route::get('/absensi/siswa', [AbsensiController::class, 'siswa'])->name('guru/absensi.siswa');
    Route::post('/absensi', [AbsensiController::class, 'simpan'])->name('guru/absensi.simpan');
    Route::get('/absensi/rekap', [AbsensiController::class, 'rekap'])->name('guru/absensi.rekap');
    Route::get('/nilai', [NilaiController::class, 'index'])->name('guru/nilai');
    Route::get('/nilai/siswa', [NilaiController::class, 'siswa'])->name('guru/nilai.siswa');
    Route::post('/nilai', [NilaiController::class, 'simpan'])->name('guru/nilai.simpan');
    Route::get('/nilai/daftar-siswa', [NilaiController::class, 'daftarSiswa'])->name('guru/nilai.daftar');
    Route::get('/rapor/{siswa}', [NilaiController::class, 'rapor'])->name('guru/rapor');
    Route::get('/wali', [WaliKelasController::class, 'index'])->middleware('checkRole:wali_kelas')->name('guru/wali');
    Route::get('/usulan', [UsulanKenaikanController::class, 'index'])->middleware('checkRole:wali_kelas')->name('guru/usulan');
    Route::put('/usulan/{kelas}', [UsulanKenaikanController::class, 'simpan'])->middleware('checkRole:wali_kelas')->name('guru/usulan.simpan');
    Route::get('/jadwal', [JadwalController::class, 'saya'])->name('guru/jadwal');
    Route::get('/jadwal/grid', [JadwalController::class, 'grid'])->name('guru/jadwal.grid');
    Route::ajaxCrud('guru/berita', BeritaController::class, 'berita', false, [], true);
    Route::ajaxCrud('guru/kategori', KategoriController::class, 'kategori');
    Route::ajaxCrud('guru/kelas', KelasController::class, 'kelas', true);
    Route::ajaxCrud('guru/mapel', MapelController::class, 'mapel', true);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('guru/dashboard');
    Route::get('/profile', [ProfileController::class, 'index'])->name('guru/profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('guru/profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('guru/profile.password');

    Route::get('/kelas/open/{id}', [KelasController::class, 'open']);






    // Route::get('listberita', [BeritaController::class, 'index'])->name('guru/listberita');
    // Route::get('postberita', [BeritaController::class, 'postberita'])->name('guru/postberita');
    // Route::post('postberita/tambah', [
    //     'uses' => [BeritaController::class, 'tambah'],
    //     'as' => 'beritaguru.tambah'
    // ]);

    // Route::get('/berita/{id}/hapus', [BeritaController::class, 'hapus']);
    // Route::get('/berita/edit/{id}', [BeritaController::class, 'edit']);
    // Route::post('/berita/update/{id}', [BeritaController::class, 'update']);
});

// Keuangan sekolah (modul Keuangan): halaman yang sama dipakai di panel admin (/admin/keuangan) dan panel bendahara (/bendahara).
foreach (['admin' => 'admin', 'bendahara' => 'bendahara'] as $panel => $peran) {
    Route::prefix($panel)->middleware('auth', "checkRole:$peran")->group(function () use ($panel) {
        if ($panel === 'bendahara') {
            Route::get('/dashboard', [KeuanganController::class, 'ringkasan'])->name('bendahara/dashboard');
        } else {
            Route::get('/keuangan', [KeuanganController::class, 'ringkasan'])->name('admin/keuangan');
        }

        Route::get('/keuangan/tagihan', [KeuanganController::class, 'tagihan'])->name("$panel/keuangan/tagihan");
        Route::get('/keuangan/tagihan/data', [KeuanganController::class, 'tagihanData'])->name("$panel/keuangan/tagihan.data");
        Route::post('/keuangan/tagihan', [KeuanganController::class, 'tagihanMassal'])->name("$panel/keuangan/tagihan.massal");
        Route::post('/keuangan/tagihan/{tagihan}/bayar', [KeuanganController::class, 'bayar'])->whereNumber('tagihan')->name("$panel/keuangan/tagihan.bayar");
        Route::post('/keuangan/tagihan/{tagihan}/batal', [KeuanganController::class, 'batalTagihan'])->whereNumber('tagihan')->name("$panel/keuangan/tagihan.batal");

        Route::get('/keuangan/pembayaran', [KeuanganController::class, 'pembayaran'])->name("$panel/keuangan/pembayaran");
        Route::get('/keuangan/pembayaran/data', [KeuanganController::class, 'pembayaranData'])->name("$panel/keuangan/pembayaran.data");
        Route::get('/keuangan/pembayaran/{pembayaran}/kuitansi', [KeuanganController::class, 'kuitansi'])->whereNumber('pembayaran')->name("$panel/keuangan.kuitansi");
        Route::post('/keuangan/pembayaran/{pembayaran}/batal', [KeuanganController::class, 'batalBayar'])->whereNumber('pembayaran')->name("$panel/keuangan/pembayaran.batal");

        Route::ajaxCrud("$panel/keuangan/biaya", JenisBiayaController::class, 'biaya');

        // kunci gerbang milik sekolah: khusus admin
        if ($panel === 'admin') {
            Route::get('/keuangan/online', [KeuanganOnlineController::class, 'index'])->name('admin/keuangan/online');
            Route::put('/keuangan/online', [KeuanganOnlineController::class, 'simpan'])->name('admin/keuangan/online.simpan');
        }
    });
}

// Login-sebagai admin sekolah oleh Super Admin (tautan bertanda tangan dari panel platform)
Route::get('/impersonasi', [ImpersonasiController::class, 'masuk'])->name('impersonasi.masuk');
Route::post('/impersonasi/keluar', [ImpersonasiController::class, 'keluar'])->middleware('auth')->name('impersonasi.keluar');

// Panel Super Admin (tidak terikat sekolah): sekolah penyewa, paket, langganan, invoice, log aktivitas
Route::prefix('platform')->group(function () {
    Route::get('/login', [PlatformAuthController::class, 'form'])->name('platform.login');
    Route::post('/login', [PlatformAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth', 'checkRole:super_admin')->group(function () {
        Route::post('/logout', [PlatformAuthController::class, 'logout'])->name('platform.logout');
        Route::redirect('/', '/platform/dashboard');
        Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('platform/dashboard');

        Route::get('/sekolah', [PlatformSekolahController::class, 'index'])->name('platform/sekolah');
        Route::get('/sekolah/data', [PlatformSekolahController::class, 'data'])->name('platform/sekolah.data');
        Route::post('/sekolah', [PlatformSekolahController::class, 'store'])->name('platform/sekolah.store');
        Route::get('/sekolah/{sekolah}', [PlatformSekolahController::class, 'show'])->whereNumber('sekolah')->name('platform/sekolah.show');
        Route::put('/sekolah/{sekolah}', [PlatformSekolahController::class, 'update'])->whereNumber('sekolah')->name('platform/sekolah.update');
        Route::post('/sekolah/{sekolah}/domain/periksa', [PlatformSekolahController::class, 'domainPeriksa'])->name('platform/sekolah.domain.periksa');
        Route::post('/sekolah/{sekolah}/domain/manual', [PlatformSekolahController::class, 'domainManual'])->name('platform/sekolah.domain.manual');
        Route::delete('/sekolah/{sekolah}/domain', [PlatformSekolahController::class, 'domainHapus'])->name('platform/sekolah.domain.hapus');
        Route::post('/sekolah/{sekolah}/status', [PlatformSekolahController::class, 'status'])->name('platform/sekolah.status');
        Route::post('/sekolah/{sekolah}/invoice', [PlatformSekolahController::class, 'buatInvoice'])->name('platform/sekolah.invoice');
        Route::post('/sekolah/{sekolah}/masuk', [PlatformSekolahController::class, 'masuk'])->name('platform/sekolah.masuk');

        Route::get('/pengaturan', [PlatformPengaturanController::class, 'index'])->name('platform/pengaturan');
        Route::put('/pengaturan', [PlatformPengaturanController::class, 'simpan'])->name('platform/pengaturan.simpan');

        Route::get('/paket', [PlatformPaketController::class, 'index'])->name('platform/paket');
        Route::post('/paket', [PlatformPaketController::class, 'store'])->name('platform/paket.store');
        Route::put('/paket/{paket}', [PlatformPaketController::class, 'update'])->name('platform/paket.update');

        Route::get('/langganan', [PlatformLanggananController::class, 'index'])->name('platform/langganan');
        Route::get('/langganan/data', [PlatformLanggananController::class, 'data'])->name('platform/langganan.data');
        Route::put('/langganan/{langganan}', [PlatformLanggananController::class, 'update'])->name('platform/langganan.update');

        Route::get('/invoice', [PlatformInvoiceController::class, 'index'])->name('platform/invoice');
        Route::get('/invoice/data', [PlatformInvoiceController::class, 'data'])->name('platform/invoice.data');
        Route::post('/invoice', [PlatformInvoiceController::class, 'store'])->name('platform/invoice.store');
        Route::get('/invoice/{invoice}/pdf', [SewaPdfController::class, 'invoice'])->whereNumber('invoice')->name('platform/invoice.pdf');
        Route::get('/invoice/{invoice}/kuitansi', [SewaPdfController::class, 'kuitansi'])->whereNumber('invoice')->name('platform/invoice.kuitansi');
        Route::post('/invoice/{invoice}/lunas', [PlatformInvoiceController::class, 'lunas'])->name('platform/invoice.lunas');
        Route::post('/invoice/{invoice}/batal', [PlatformInvoiceController::class, 'batal'])->name('platform/invoice.batal');

        Route::get('/log', [PlatformLogController::class, 'index'])->name('platform/log');
        Route::get('/log/data', [PlatformLogController::class, 'data'])->name('platform/log.data');
        Route::get('/log/ekspor', [PlatformLogController::class, 'ekspor'])->name('platform/log.ekspor');

        Route::get('/pemantauan', [PlatformPemantauanController::class, 'index'])->name('platform/pemantauan');
        Route::post('/pemantauan/segarkan', [PlatformPemantauanController::class, 'segarkan'])->name('platform/pemantauan.segarkan');

        Route::get('/backup', [PlatformBackupController::class, 'index'])->name('platform/backup');
        Route::post('/backup', [PlatformBackupController::class, 'buat'])->name('platform/backup.buat');
        Route::get('/backup/{backup}/unduh', [PlatformBackupController::class, 'unduh'])->whereNumber('backup')->name('platform/backup.unduh');
        Route::delete('/backup/{backup}', [PlatformBackupController::class, 'hapus'])->whereNumber('backup')->name('platform/backup.hapus');
    });
});

// Kepala sekolah: pantauan hanya-baca
Route::prefix('kepsek')->middleware('auth', 'checkRole:kepsek')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('kepsek/dashboard');
    Route::ajaxCrud('kepsek/kelas', KelasController::class, 'kelas', true);
    Route::get('/kelas/open/{id}', [KelasController::class, 'open']);
    Route::get('/kehadiran', [KepsekController::class, 'kehadiran'])->name('kepsek/kehadiran');
    Route::get('/alumni/export', [AlumniController::class, 'export'])->name('kepsek/alumni.export');
    Route::ajaxCrud('kepsek/alumni', AlumniController::class, 'alumni', true);
    Route::get('/rapor/{siswa}', [NilaiController::class, 'rapor'])->name('kepsek/rapor');
});

// Portal orang tua
Route::prefix('ortu')->middleware('auth', 'checkRole:orang_tua')->group(function () {
    Route::get('/dashboard', [OrtuController::class, 'dashboard'])->name('ortu/dashboard');
    Route::post('/anak/{siswa}', [OrtuController::class, 'pilihAnak'])->name('ortu/anak.pilih');
    Route::get('/kehadiran', [OrtuController::class, 'kehadiran'])->name('ortu/kehadiran');
    Route::get('/kehadiran/data', [OrtuController::class, 'kehadiranData'])->name('ortu/kehadiran.data');
    Route::get('/nilai', [OrtuController::class, 'nilai'])->name('ortu/nilai');
    Route::get('/tagihan', [OrtuController::class, 'tagihan'])->name('ortu/tagihan');
    Route::post('/tagihan/bayar', [OrtuController::class, 'bayar'])->name('ortu/tagihan.bayar');
    Route::get('/kuitansi/{pembayaran}', [OrtuController::class, 'kuitansi'])->whereNumber('pembayaran')->name('ortu/kuitansi');
    Route::get('/profile', [OrtuController::class, 'profil'])->name('ortu/profile');
    Route::put('/profile', [OrtuController::class, 'updateProfil'])->name('ortu/profile.update');
    Route::put('/profile/password', [OrtuController::class, 'ubahPassword'])->name('ortu/profile.password');
});

//route siswa
Route::prefix('siswa')->middleware('auth', 'checkRole:siswa')->group(function () {
    Route::get('/kehadiran', [AbsensiController::class, 'saya'])->name('siswa/kehadiran');
    Route::get('/kehadiran/data', [AbsensiController::class, 'sayaData'])->name('siswa/kehadiran.data');
    Route::get('/nilai', [NilaiController::class, 'raporSaya'])->name('siswa/nilai');
    Route::get('/jadwal', [JadwalController::class, 'saya'])->name('siswa/jadwal');
    Route::get('/jadwal/grid', [JadwalController::class, 'grid'])->name('siswa/jadwal.grid');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('siswa/dashboard');
    Route::get('/profile', [ProfileController::class, 'index'])->name('siswa/profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('siswa/profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('siswa/profile.password');


    Route::get('/kelas', [KelasController::class, 'opensiswa'])->name('siswa/kelas');
});


