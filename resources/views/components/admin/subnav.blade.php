{{-- Tab navigasi antar halaman satu kelompok. $items = [[label, url, [pola request()->is]], ...] --}}
@props(['items'])
<nav class="mb-6 flex flex-wrap gap-1 rounded-2xl border border-slate-100 bg-white p-1.5 shadow-soft" aria-label="Sub menu">
    @foreach ($items as [$label, $url, $match])
        <a href="{{ $url }}" @class([
            'rounded-xl px-4 py-2 text-sm font-semibold no-underline transition',
            'bg-primary text-white shadow-sm' => request()->is(...$match),
            'text-slate-600 hover:bg-primary-soft hover:text-primary' => ! request()->is(...$match),
        ])>{{ $label }}</a>
    @endforeach
</nav>
