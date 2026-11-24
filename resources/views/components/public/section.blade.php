{{-- Judul bagian halaman depan: label kecil beraksen oranye, judul, dan keterangan. --}}
@props(['eyebrow' => null, 'title', 'subtitle' => null, 'align' => 'center'])
<div @class(['mb-10 max-w-2xl', 'mx-auto text-center' => $align === 'center', 'text-left' => $align !== 'center'])>
    @if ($eyebrow)
        <p class="mb-2 inline-flex items-center gap-2 text-xs font-bold tracking-widest text-accent-dark uppercase"><span class="h-0.5 w-6 rounded-full bg-accent"></span>{{ $eyebrow }}</p>
    @endif
    <h2 class="font-display text-3xl font-extrabold text-ink sm:text-4xl">{{ $title }}</h2>
    @if ($subtitle)<p class="mt-3 text-slate-600">{{ $subtitle }}</p>@endif
</div>
