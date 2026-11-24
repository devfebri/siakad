@props(['label', 'value', 'icon', 'color' => 'primary', 'href' => null, 'hint' => null])
@php
    $tones = [
        'primary' => 'bg-primary-soft text-primary',
        'accent' => 'bg-accent-soft text-accent-dark',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'violet' => 'bg-violet-50 text-violet-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card card-lift group flex items-center gap-4 p-5 no-underline', 'cursor-pointer' => $href]) }}>
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl text-2xl {{ $tones[$color] ?? $tones['primary'] }}">
        <i class="bi bi-{{ $icon }}"></i>
    </span>
    <span class="min-w-0">
        <span class="block font-display text-2xl leading-none font-extrabold text-ink">{{ is_numeric($value) ? number_format($value, 0, ',', '.') : $value }}</span>
        <span class="mt-1 block truncate text-sm font-medium text-slate-500">{{ $label }}</span>
        @if ($hint)<span class="block text-xs text-slate-400">{{ $hint }}</span>@endif
    </span>
</{{ $tag }}>
