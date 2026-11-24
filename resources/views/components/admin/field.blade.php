{{--
    Satu kolom form: label + input/select/textarea + petunjuk.
    <x-admin.field name="judul" label="Judul" required maxlength="100" />
    <x-admin.field name="kelas_id" label="Kelas" as="select" required><option ...></x-admin.field>
    Atribut class, hidden, data-create-only, dan data-edit-only diterapkan ke pembungkus (mis. class="sm:col-span-2"),
    atribut lainnya ke input.
--}}
@props(['name', 'label', 'type' => 'text', 'as' => 'input', 'hint' => null, 'id' => null, 'optional' => false])
@php
    $id = $id ?? $name;
    $wrapper = ['class', 'hidden', 'data-create-only', 'data-edit-only'];
@endphp
<div data-field {{ $attributes->only($wrapper) }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if ($optional) <span class="font-normal text-slate-400">(opsional)</span>@endif</label>
    @if ($as === 'select')
        <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except($wrapper)->class(['select']) }}>{{ $slot }}</select>
    @elseif ($as === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" {{ $attributes->except($wrapper)->class(['textarea']) }}>{{ $slot }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" {{ $attributes->except($wrapper)->class(['input']) }}>
    @endif
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
</div>
