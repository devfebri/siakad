@php
    $sosmed = array_filter([
        'facebook' => config('school.facebook'),
        'instagram' => config('school.instagram'),
        'youtube' => config('school.youtube'),
        'tiktok' => config('school.tiktok'),
    ]);
    $wa = config('school.whatsapp') ? 'https://wa.me/'.preg_replace('/\D/', '', config('school.whatsapp')) : null;
@endphp
<footer class="mt-16 bg-gradient-to-b from-primary to-primary-deep text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-1">
            <a href="{{ route('beranda') }}" class="flex items-center gap-3 text-white no-underline">
                <span class="grid size-11 place-items-center overflow-hidden rounded-xl bg-accent text-2xl text-ink">
                    @if (config('school.logo'))<img src="{{ asset(config('school.logo')) }}" alt="" class="size-full bg-white object-contain">@else<i class="bi bi-mortarboard-fill"></i>@endif
                </span>
                <span class="font-display text-base leading-tight font-extrabold">{{ config('school.name') }}</span>
            </a>
            <p class="mt-4 text-sm leading-relaxed text-white/70">{{ config('school.hero_deskripsi') }}</p>
            @if ($sosmed)
                <div class="mt-5 flex gap-2">
                    @foreach ($sosmed as $nama => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($nama) }}" class="grid size-9 place-items-center rounded-lg bg-white/10 text-white no-underline transition hover:bg-accent hover:text-ink"><i class="bi bi-{{ $nama }}"></i></a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h3 class="mb-4 font-display text-sm font-bold tracking-wide text-accent uppercase">Tautan Cepat</h3>
            <ul class="space-y-2 text-sm">
                @foreach (array_filter([['Profil Sekolah', route('profil'), null], ['Akademik', route('akademik'), null], ['Berita', route('berita'), 'konten_web'], ['Pengumuman', route('pengumuman'), 'konten_web'], ['Agenda', route('agenda'), 'konten_web'], ['Galeri', route('galeri'), 'konten_web'], ['Unduhan', route('unduhan'), 'unduhan'], ['PPDB', route('ppdb'), 'ppdb']], fn ($t) => $t[2] === null || \App\Support\AksesSekolah::modul($t[2])) as [$l, $u])
                    <li><a href="{{ $u }}" class="text-white/75 no-underline transition hover:text-accent">{{ $l }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="mb-4 font-display text-sm font-bold tracking-wide text-accent uppercase">Hubungi Kami</h3>
            <ul class="space-y-3 text-sm text-white/75">
                @if (config('school.alamat'))<li class="flex gap-3"><i class="bi bi-geo-alt-fill mt-0.5 text-accent"></i><span>{{ config('school.alamat') }}</span></li>@endif
                @if (config('school.telepon'))<li class="flex gap-3"><i class="bi bi-telephone-fill text-accent"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', config('school.telepon')) }}" class="text-white/75 no-underline hover:text-accent">{{ config('school.telepon') }}</a></li>@endif
                @if (config('school.email'))<li class="flex gap-3"><i class="bi bi-envelope-fill text-accent"></i><a href="mailto:{{ config('school.email') }}" class="text-white/75 no-underline hover:text-accent">{{ config('school.email') }}</a></li>@endif
                @if ($wa)<li class="flex gap-3"><i class="bi bi-whatsapp text-accent"></i><a href="{{ $wa }}" target="_blank" rel="noopener" class="text-white/75 no-underline hover:text-accent">WhatsApp</a></li>@endif
                @if (config('school.jam_layanan'))<li class="flex gap-3"><i class="bi bi-clock-fill text-accent"></i><span>{{ config('school.jam_layanan') }}</span></li>@endif
            </ul>
        </div>

        <div>
            <h3 class="mb-4 font-display text-sm font-bold tracking-wide text-accent uppercase">Lokasi</h3>
            @if (config('school.peta_embed_url'))
                <iframe src="{{ config('school.peta_embed_url') }}" class="h-44 w-full rounded-xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi sekolah" allowfullscreen></iframe>
            @else
                <div class="grid h-44 place-items-center rounded-xl bg-white/10 text-center text-sm text-white/60"><span><i class="bi bi-map mb-1 block text-2xl"></i>Peta belum diatur</span></div>
            @endif
        </div>
    </div>
    <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-white/55">&copy; {{ date('Y') }} {{ config('school.name') }}. Hak cipta dilindungi.</div>
</footer>
