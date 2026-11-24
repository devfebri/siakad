{{-- Spanduk judul halaman dalam: latar navy, judul, deskripsi singkat, dan jejak halaman. --}}
@props(['title', 'subtitle' => null, 'crumb' => null])
<section class="relative overflow-hidden bg-gradient-to-br from-primary to-primary-deep text-white">
    <div class="bg-grid pointer-events-none absolute inset-0"></div>
    <i class="bi bi-book-half pointer-events-none absolute -right-6 -bottom-12 text-[12rem] leading-none text-white/[0.06]"></i>
    <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <nav aria-label="Jejak halaman" class="mb-3 flex items-center gap-2 text-sm text-white/70">
            <a href="{{ route('beranda') }}" class="text-white/70 no-underline hover:text-accent">Beranda</a>
            <i class="bi bi-chevron-right text-xs"></i>
            <span class="text-accent">{{ $crumb ?? $title }}</span>
        </nav>
        <h1 class="font-display text-3xl font-extrabold sm:text-4xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-2 max-w-2xl text-white/80">{{ $subtitle }}</p>@endif
    </div>
</section>
