@extends('layouts.admin')

@section('title', 'Unduhan')

@section('content')
    <x-admin.crud title="Unduhan" subtitle="Berkas yang bisa diunduh pengunjung di menu Unduhan situs publik (formulir, panduan, brosur, dll)." name="Berkas" add-label="Tambah Berkas" add-icon="cloud-arrow-up"
        :url="route('admin/unduhan.data')" :store="route('admin/unduhan.store')" :update="url('admin/unduhan/:id')" :destroy="url('admin/unduhan/:id')">
        <x-slot:actions>
            <a href="{{ route('unduhan') }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i class="bi bi-box-arrow-up-right"></i> Lihat di Situs</a>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="judul">Judul</th>
            <th data-col="kategori">Kategori</th>
            <th data-col="ukuran" class="no-sort">Ukuran</th>
            <th data-col="jumlah_unduh">Diunduh</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="file_url" data-cell="file" class="no-sort">Berkas</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="judul" id="ud_judul" label="Judul" class="sm:col-span-2" maxlength="150" required />
            <x-admin.field name="kategori" id="ud_kategori" label="Kategori" list="ud-kategori" maxlength="60" value="Umum" required hint="Pilih atau ketik kategori baru." />
            <x-admin.field name="urutan" id="ud_urutan" label="Urutan" type="number" min="0" value="0" hint="Angka kecil tampil lebih dulu." />
            <x-admin.field name="deskripsi" id="ud_deskripsi" label="Deskripsi" as="textarea" rows="2" class="sm:col-span-2" maxlength="500" optional />
            <x-admin.field name="berkas" id="ud_berkas" label="Berkas" type="file" class="sm:col-span-2" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png,.txt" hint="PDF, Word, Excel, PowerPoint, ZIP, gambar. Maks. 10 MB. Saat mengubah, kosongkan jika berkas tidak diganti." />
            <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="aktif" value="1" class="check" checked> Tampilkan di situs</label>
            <datalist id="ud-kategori">@foreach ($kategori as $k)<option value="{{ $k }}">@endforeach</datalist>
        </x-slot:fields>
    </x-admin.crud>
@endsection
