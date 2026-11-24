{{--
    Halaman CRUD AJAX standar (lihat resources/js/crud.js).

    <x-admin.crud title="Mata Pelajaran" name="Mata Pelajaran" :url="route('admin/mapel.data')"
                  :store="route('admin/mapel.store')" :update="url('admin/mapel/:id')" :destroy="url('admin/mapel/:id')">
        <x-slot:columns> <th data-col="no">No</th> ... <th data-col="actions" class="no-sort">Aksi</th> </x-slot:columns>
        <x-slot:fields> <x-admin.field .../> </x-slot:fields>
    </x-admin.crud>

    Tanpa $store/$update/$destroy aksi itu tidak muncul. $addHref = tombol tambah berupa tautan ke halaman lain.
--}}
@props([
    'title', 'name', 'url', 'subtitle' => null,
    'store' => null, 'update' => null, 'destroy' => null,
    'show' => null, 'showLabel' => null, 'showIcon' => null, 'showKey' => null, 'showBlank' => false,
    'editLink' => null, 'addLabel' => null, 'addIcon' => 'plus-lg', 'addHref' => null,
    'size' => '', 'back' => null, 'bare' => false,
])
<div data-crud data-name="{{ $name }}" data-url="{{ $url }}"
     @if ($store) data-store="{{ $store }}" @endif
     @if ($update) data-update="{{ $update }}" @endif
     @if ($destroy) data-destroy="{{ $destroy }}" @endif
     @if ($show) data-show="{{ $show }}" @endif
     @if ($showLabel) data-show-label="{{ $showLabel }}" @endif
     @if ($showIcon) data-show-icon="{{ $showIcon }}" @endif
     @if ($showKey) data-show-key="{{ $showKey }}" @endif
     @if ($showBlank) data-show-blank @endif
     @if ($editLink) data-edit-link="{{ $editLink }}" @endif
     {{ $attributes }}>

    @if ($bare && ($store || $addHref || isset($actions)))
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display text-lg font-bold">{{ $title }}</h2>
            <div class="flex items-center gap-2">
                {{ $actions ?? '' }}
                @if ($addHref)
                    <a href="{{ $addHref }}" class="btn btn-primary btn-sm" data-crud-add><i class="bi bi-{{ $addIcon }}"></i> {{ $addLabel ?? 'Tambah '.$name }}</a>
                @else
                    <button type="button" class="btn btn-primary btn-sm" data-crud-add><i class="bi bi-{{ $addIcon }}"></i> {{ $addLabel ?? 'Tambah '.$name }}</button>
                @endif
            </div>
        </div>
    @endif

    @unless ($bare)
        <x-admin.page-header :title="$title" :subtitle="$subtitle" :back="$back">
            {{ $actions ?? '' }}
            @if ($store || $addHref)
                @if ($addHref)
                    <a href="{{ $addHref }}" class="btn btn-primary" data-crud-add><i class="bi bi-{{ $addIcon }}"></i> {{ $addLabel ?? 'Tambah '.$name }}</a>
                @else
                    <button type="button" class="btn btn-primary" data-crud-add><i class="bi bi-{{ $addIcon }}"></i> {{ $addLabel ?? 'Tambah '.$name }}</button>
                @endif
            @endif
        </x-admin.page-header>
    @endunless

    <div class="card">
        <div class="p-4 sm:p-5">
            <table class="table-clean table-stack w-full">
                <thead><tr>{{ $columns }}</tr></thead>
            </table>
        </div>
    </div>

    @if (isset($fields) && ($store || $update))
        <dialog class="modal {{ $size ? 'modal-'.$size : '' }}" aria-labelledby="modal-title-{{ \Illuminate\Support\Str::slug($name) }}">
            <form novalidate>
                <div class="flex items-center justify-between gap-3 px-6 pt-5">
                    <h2 class="font-display text-lg font-bold" id="modal-title-{{ \Illuminate\Support\Str::slug($name) }}" data-modal-title></h2>
                    <button type="button" class="btn btn-ghost btn-sm" data-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="grid max-h-[70vh] gap-4 overflow-y-auto px-6 py-5 sm:grid-cols-2">{{ $fields }}</div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" class="btn btn-outline" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
