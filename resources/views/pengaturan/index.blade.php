@extends('layouts.admin')

@section('title', 'Pengaturan')

@php
    $tabAwal = request('tab', 'identitas');
    $tabs = collect($groups)->map(fn ($g) => ['label' => $g['label'], 'icon' => $g['icon']])->all()
        + [
            'program' => ['label' => 'Program Unggulan', 'icon' => 'stars'],
            'testimoni' => ['label' => 'Testimoni', 'icon' => 'chat-quote'],
            'profil' => ['label' => 'Halaman Profil', 'icon' => 'file-earmark-richtext'],
        ];
    if (! array_key_exists($tabAwal, $tabs)) { $tabAwal = 'identitas'; }
@endphp

@section('content')
    <x-admin.page-header title="Pengaturan" subtitle="Atur identitas sekolah dan konten halaman depan.">
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-outline"><i class="bi bi-box-arrow-up-right"></i> Lihat Situs</a>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-4" x-data="{ tab: @js($tabAwal), profil: {{ array_key_first($sections) }} }">
        <nav class="card h-fit p-2" role="tablist" aria-label="Bagian pengaturan">
            @foreach ($tabs as $key => $tab)
                <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="pane-{{ $key }}" :aria-selected="tab === '{{ $key }}'"
                        @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-xl border-0 px-3.5 py-2.5 text-left text-sm font-semibold transition"
                        :class="tab === '{{ $key }}' ? 'bg-primary text-white shadow-sm' : 'bg-transparent text-slate-600 hover:bg-primary-soft hover:text-primary'">
                    <i class="bi bi-{{ $tab['icon'] }} w-5 text-center"></i> {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>

        <div class="lg:col-span-3">
            {{-- Grup pengaturan situs (dibentuk dari config/site-settings.php) --}}
            @foreach ($groups as $gkey => $group)
                <div x-cloak x-show="tab === '{{ $gkey }}'" role="tabpanel" id="pane-{{ $gkey }}" aria-labelledby="tab-{{ $gkey }}">
                    <form data-ajax-form data-method="PUT" enctype="multipart/form-data" novalidate action="{{ route('admin/pengaturan.situs', $gkey) }}" class="card">
                        @csrf
                        <div class="card-header"><span class="flex items-center gap-2"><i class="bi bi-{{ $group['icon'] }} text-primary"></i> {{ $group['label'] }}</span></div>
                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            @foreach ($group['fields'] as $key => $field)
                                @php
                                    $nilai = $values[$key] ?? config($field['config'] ?? "school.$key");
                                    $wide = in_array($field['type'], ['textarea', 'image']) || str_contains($key, 'judul') || $key === 'peta_embed_url' || $key === 'alamat';
                                    $id = $gkey.'-'.$key;
                                @endphp
                                <div data-field @class(['sm:col-span-2' => $wide])>
                                    @if ($field['type'] === 'checkbox')
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold">
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input type="checkbox" name="{{ $key }}" value="1" class="check" @checked((bool) $nilai)> {{ $field['label'] }}
                                        </label>
                                    @else
                                        <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
                                        @if ($field['type'] === 'textarea')
                                            <textarea id="{{ $id }}" name="{{ $key }}" rows="3" class="textarea">{{ $nilai }}</textarea>
                                        @elseif ($field['type'] === 'select')
                                            <select id="{{ $id }}" name="{{ $key }}" class="select">
                                                @foreach ($field['options'] as $val => $lab)<option value="{{ $val }}" @selected((string) $nilai === (string) $val)>{{ $lab }}</option>@endforeach
                                            </select>
                                        @elseif ($field['type'] === 'image')
                                            @if ($nilai)<img data-preview src="{{ asset($nilai) }}" alt="" class="mb-2 block max-h-32 rounded-xl border border-slate-100 bg-white p-1">@endif
                                            <input type="file" id="{{ $id }}" name="{{ $key }}" class="input" accept="image/png,image/jpeg,image/webp">
                                        @elseif ($field['type'] === 'color')
                                            <div class="flex items-center gap-3">
                                                <input type="color" id="{{ $id }}" name="{{ $key }}" value="{{ $nilai ?: '#1e3a8a' }}" class="h-11 w-16 cursor-pointer rounded-xl border border-slate-200 bg-white p-1">
                                                <span class="text-sm text-slate-500">{{ $nilai ?: 'Bawaan (#1e3a8a)' }}</span>
                                            </div>
                                        @elseif ($field['type'] === 'datetime')
                                            <input type="datetime-local" id="{{ $id }}" name="{{ $key }}" value="{{ $nilai ? \Illuminate\Support\Carbon::parse($nilai)->format('Y-m-d\TH:i') : '' }}" class="input">
                                        @else
                                            <input type="{{ $field['type'] }}" id="{{ $id }}" name="{{ $key }}" value="{{ $nilai }}" class="input"
                                                   @if ($field['type'] === 'number') min="0" @endif>
                                        @endif
                                        @if (! empty($field['hint']))<p class="field-hint">{{ $field['hint'] }}</p>@endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="card-footer text-right"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan</button></div>
                    </form>
                </div>
            @endforeach

            {{-- Program unggulan --}}
            <div x-cloak x-show="tab === 'program'" role="tabpanel" id="pane-program" aria-labelledby="tab-program">
                <x-admin.crud title="Program Unggulan" name="Program" subtitle="Ditampilkan sebagai kartu di beranda (maksimal 4 teratas)."
                    :url="route('admin/program.data')" :store="route('admin/program.store')"
                    :update="url('admin/program/:id')" :destroy="url('admin/program/:id')" bare>
                    <x-slot:columns>
                        <th data-col="no" class="w-12">No</th>
                        <th data-col="judul">Judul</th>
                        <th data-col="ikon_label" class="no-sort">Ikon</th>
                        <th data-col="urutan">Urutan</th>
                        <th data-col="status" data-cell="badge" class="no-sort">Status</th>
                        <th data-col="actions" class="no-sort text-right">Aksi</th>
                    </x-slot:columns>
                    <x-slot:fields>
                        <x-admin.field name="judul" id="pr_judul" label="Judul" class="sm:col-span-2" maxlength="100" required />
                        <x-admin.field name="deskripsi" id="pr_deskripsi" label="Deskripsi Singkat" as="textarea" class="sm:col-span-2" rows="2" maxlength="300" optional />
                        <x-admin.field name="ikon" id="pr_ikon" label="Ikon" as="select" required>
                            @foreach ($ikon as $nama => $label)<option value="{{ $nama }}">{{ $label }}</option>@endforeach
                        </x-admin.field>
                        <x-admin.field name="urutan" id="pr_urutan" label="Urutan" type="number" min="0" value="0" hint="Angka kecil tampil lebih dulu." />
                        <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="aktif" value="1" class="check" checked> Tampilkan di beranda</label>
                    </x-slot:fields>
                </x-admin.crud>
                <div class="mt-3"></div>
            </div>

            {{-- Testimoni --}}
            <div x-cloak x-show="tab === 'testimoni'" role="tabpanel" id="pane-testimoni" aria-labelledby="tab-testimoni">
                <x-admin.crud title="Testimoni" name="Testimoni" :url="route('admin/testimoni.data')" :store="route('admin/testimoni.store')"
                    :update="url('admin/testimoni/:id')" :destroy="url('admin/testimoni/:id')" bare>
                    <x-slot:columns>
                        <th data-col="no" class="w-12">No</th>
                        <th data-col="foto" data-cell="image" class="no-sort w-16">Foto</th>
                        <th data-col="nama">Nama</th>
                        <th data-col="peran" class="no-sort">Peran</th>
                        <th data-col="ringkas" class="no-sort">Isi</th>
                        <th data-col="status" data-cell="badge" class="no-sort">Status</th>
                        <th data-col="actions" class="no-sort text-right">Aksi</th>
                    </x-slot:columns>
                    <x-slot:fields>
                        <x-admin.field name="nama" id="ts_nama" label="Nama" maxlength="100" required />
                        <x-admin.field name="peran" id="ts_peran" label="Peran" maxlength="120" placeholder="mis. Alumni 2022 – Mahasiswa UI" optional />
                        <x-admin.field name="foto" id="ts_foto" label="Foto" type="file" class="sm:col-span-2" accept="image/jpeg,image/png,image/webp" hint="Opsional, maks. 1 MB. Kosongkan untuk memakai inisial nama (saat mengubah: kosongkan agar foto lama tetap)." />
                        <x-admin.field name="isi" id="ts_isi" label="Isi Testimoni" as="textarea" class="sm:col-span-2" rows="4" maxlength="600" required />
                        <x-admin.field name="urutan" id="ts_urutan" label="Urutan" type="number" min="0" value="0" />
                        <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="aktif" value="1" class="check" checked> Tampilkan di beranda</label>
                    </x-slot:fields>
                </x-admin.crud>
            </div>

            {{-- Halaman profil sekolah (8 bagian) --}}
            <div x-cloak x-show="tab === 'profil'" role="tabpanel" id="pane-profil" aria-labelledby="tab-profil">
                <div class="mb-4 flex flex-wrap gap-2">
                    @foreach ($sections as $id => $section)
                        <button type="button" @click="profil = {{ $id }}" class="btn btn-sm" :class="profil === {{ $id }} ? 'btn-primary' : 'btn-outline'">
                            <i class="bi bi-{{ $section['icon'] }}"></i> {{ $section['label'] }}
                        </button>
                    @endforeach
                </div>
                @foreach ($sections as $id => $section)
                    @php $row = $rows[$id]; @endphp
                    <div x-cloak x-show="profil === {{ $id }}" id="pane-{{ $id }}">
                        <form data-ajax-form data-method="PUT" enctype="multipart/form-data" novalidate action="{{ route('admin/pengaturan.update', $row->id) }}" class="card">
                            @csrf
                            <div class="card-header"><span class="flex items-center gap-2"><i class="bi bi-{{ $section['icon'] }} text-primary"></i> {{ $section['label'] }}</span></div>
                            <div class="space-y-5 p-5">
                                <x-admin.field name="judul" id="judul-{{ $id }}" label="Judul" maxlength="200" value="{{ $row->judul }}" required />
                                <div data-field>
                                    <label class="label" id="isi-label-{{ $id }}">Isi Halaman</label>
                                    <input type="hidden" id="deskripsi-{{ $id }}" name="deskripsi" value="{{ $row->deskripsi }}">
                                    <div data-editor="deskripsi-{{ $id }}" aria-labelledby="isi-label-{{ $id }}"></div>
                                </div>
                                @if ($section['dir'])
                                    <div data-field>
                                        <label for="gambar-{{ $id }}" class="label">Gambar</label>
                                        @if ($row->gambar)
                                            <img data-preview src="{{ asset('storage/pengaturan/'.$section['dir'].'/'.$row->gambar) }}" alt="" class="mb-2 block max-h-52 rounded-xl">
                                        @endif
                                        <input type="file" id="gambar-{{ $id }}" name="gambar" class="input" accept="image/jpeg,image/png,image/webp">
                                        <p class="field-hint">JPG, PNG, atau WEBP, maksimal 2 MB. Kosongkan jika tidak diganti.</p>
                                    </div>
                                @endif
                            </div>
                            <div class="card-footer text-right"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan</button></div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
