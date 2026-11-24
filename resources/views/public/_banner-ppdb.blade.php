{{-- Ajakan PPDB dengan hitung mundur penutupan pendaftaran. Variabel: $ppdb (App\Support\Ppdb::status()) --}}
<section class="px-4 pb-4 sm:px-6 lg:px-8">
    <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gradient-to-r from-primary to-primary-deep p-8 text-white shadow-lift sm:p-12">
        <div class="bg-grid pointer-events-none absolute inset-0"></div>
        <div class="pointer-events-none absolute -right-16 -bottom-16 size-72 rounded-full bg-accent/25 blur-2xl"></div>
        <i class="bi bi-mortarboard-fill pointer-events-none absolute top-6 right-8 hidden text-8xl text-white/10 sm:block"></i>

        <div class="relative grid items-center gap-8 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-bold tracking-widest text-accent uppercase">Penerimaan Peserta Didik Baru {{ $ppdb['tahun_ajaran'] }}</p>
                <h2 class="font-display text-3xl font-extrabold sm:text-4xl">
                    @switch($ppdb['state'])
                        @case('buka') Daftarkan dirimu sebelum pendaftaran ditutup! @break
                        @case('belum') Pendaftaran segera dibuka @break
                        @case('tutup') Pendaftaran telah ditutup @break
                        @default Informasi PPDB segera diumumkan
                    @endswitch
                </h2>
                <p class="mt-3 max-w-lg text-white/80">
                    @if ($ppdb['state'] === 'buka') Isi formulir secara online, pantau status pendaftaran kapan saja, dan jadilah bagian dari keluarga besar {{ config('school.short_name') }}.
                    @elseif ($ppdb['state'] === 'belum' && $ppdb['buka']) Dibuka pada {{ $ppdb['buka']->translatedFormat('d F Y') }}. Siapkan berkas dan pantau terus informasinya.
                    @elseif ($ppdb['state'] === 'tutup') Terima kasih atas antusiasme Anda. Pantau pengumuman untuk informasi gelombang berikutnya.
                    @else Pantau halaman ini atau hubungi panitia untuk informasi lebih lanjut. @endif
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('ppdb') }}" class="btn btn-accent btn-lg">{{ $ppdb['bisa_daftar'] ? 'Daftar Sekarang' : 'Lihat Informasi' }} <i class="bi bi-arrow-right"></i></a>
                    <a href="{{ route('ppdb.cek') }}" class="btn btn-lg border border-white/30 bg-white/5 text-white hover:bg-white/15">Cek Status</a>
                </div>
            </div>

            @if ($ppdb['state'] === 'buka' && $ppdb['tutup'] && $ppdb['tutup']->isFuture())
                <div data-countdown="{{ $ppdb['tutup']->format('Y-m-d\TH:i:s') }}" class="grid grid-cols-4 gap-3 text-center" role="timer" aria-label="Hitung mundur penutupan pendaftaran">
                    @foreach (['days' => 'Hari', 'hours' => 'Jam', 'minutes' => 'Menit', 'seconds' => 'Detik'] as $unit => $label)
                        <div class="rounded-2xl bg-white/10 px-2 py-4 ring-1 ring-white/20 backdrop-blur">
                            <span data-unit="{{ $unit }}" class="block font-display text-3xl font-extrabold text-accent sm:text-5xl">00</span>
                            <span class="text-xs text-white/70">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @elseif ($ppdb['tutup'])
                <div class="rounded-2xl bg-white/10 p-6 ring-1 ring-white/20">
                    <p class="text-sm text-white/70">Penutupan pendaftaran</p>
                    <p class="font-display text-2xl font-extrabold text-accent">{{ $ppdb['tutup']->translatedFormat('d F Y, H:i') }} WIB</p>
                </div>
            @endif
        </div>
    </div>
</section>
