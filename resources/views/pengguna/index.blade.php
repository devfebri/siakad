@extends('layouts.admin')

@section('title', 'Pengguna')

@section('content')
    <x-admin.crud title="Pengguna" subtitle="Kelola akun login. Akun guru dan siswa dibuat dari menu Data Guru / Data Siswa." name="Admin" add-label="Tambah Admin" add-icon="person-plus"
        :url="route('admin/pengguna.data')" :store="route('admin/pengguna.store')" :update="url('admin/pengguna/:id')" :destroy="url('admin/pengguna/:id')">
        <x-slot:actions>
            <select data-crud-filter="role" class="select !w-auto" aria-label="Filter peran">
                <option value="">Semua peran</option>
                @foreach (\App\Peran::where('kode', '!=', 'super_admin')->orderBy('id')->get() as $p)<option value="{{ $p->kode }}">{{ $p->nama }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="name">Nama</th>
            <th data-col="username">Username</th>
            <th data-col="email">Email</th>
            <th data-col="role" data-cell="badge" class="no-sort">Peran</th>
            <th data-col="bergabung" class="no-sort">Bergabung</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="name" id="pg_nama" label="Nama" class="sm:col-span-2" maxlength="100" required />
            <x-admin.field name="username" id="pg_username" label="Username (untuk login)" class="sm:col-span-2" minlength="3" maxlength="50" autocomplete="off" required hint="Huruf kecil, angka, titik, garis bawah, strip." />
            <x-admin.field name="email" id="pg_email" label="Email" type="email" class="sm:col-span-2" maxlength="191" required />
            <x-admin.field name="password" id="pg_password" label="Password" type="password" class="sm:col-span-2" minlength="6" autocomplete="new-password" data-create-only required hint="Minimal 6 karakter." />
        </x-slot:fields>
    </x-admin.crud>

    {{-- Atur ulang password --}}
    <dialog id="dialog-reset" class="modal" x-data="{ id: null, nama: '', role: '', pesan: '', galat: '' }"
            @reset-password.window="id = $event.detail.id; nama = $event.detail.name; role = $event.detail.role; pesan = ''; galat = ''; $el.showModal(); $nextTick(() => $refs.pw.focus())">
        <form @submit.prevent="
            galat = '';
            const res = await fetch(@js(url('admin/pengguna')) + '/' + id + '/password', { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ password: $refs.pw.value || null }) });
            const j = await res.json().catch(() => ({}));
            if (res.ok) { $el.closest('dialog').close(); $refs.pw.value = ''; window.toast(j.message); }
            else { galat = j.errors?.password?.[0] || j.message || 'Gagal.'; }">
            <div class="flex items-center justify-between px-6 pt-5">
                <h2 class="font-display text-lg font-bold">Atur Ulang Password</h2>
                <button type="button" class="btn btn-ghost btn-sm" @click="$el.closest('dialog').close()" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="space-y-3 px-6 py-5">
                <p class="text-sm text-slate-600">Akun: <strong x-text="nama"></strong> <span class="badge badge-muted capitalize" x-text="role"></span></p>
                <div>
                    <label for="pw-baru" class="label">Password baru</label>
                    <input id="pw-baru" x-ref="pw" type="password" minlength="6" autocomplete="new-password" class="input" :class="galat ? 'is-invalid' : ''">
                    <p class="field-hint" x-show="role !== 'admin'">Kosongkan untuk mengembalikan ke password awal.</p>
                    <p class="field-error" x-show="galat" x-text="galat"></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" class="btn btn-outline" @click="$el.closest('dialog').close()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-key"></i> Simpan</button>
            </div>
        </form>
    </dialog>
    {{-- Ubah peran --}}
    <dialog id="dialog-peran" class="modal" x-data="{ id: null, nama: '', dipilih: [], galat: '' }"
            @ubah-peran.window="id = $event.detail.id; nama = $event.detail.name; dipilih = [...$event.detail.peran]; galat = ''; $el.showModal()">
        <form @submit.prevent="
            galat = '';
            const res = await fetch(@js(url('admin/pengguna')) + '/' + id + '/peran', { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ peran: dipilih }) });
            const j = await res.json().catch(() => ({}));
            if (res.ok) { $el.closest('dialog').close(); window.toast(j.message); $('[data-crud] table').DataTable().ajax.reload(null, false); }
            else { galat = j.errors?.peran?.[0] || j.message || 'Gagal.'; }">
            <div class="flex items-center justify-between px-6 pt-5">
                <h2 class="font-display text-lg font-bold">Ubah Peran</h2>
                <button type="button" class="btn btn-ghost btn-sm" @click="$el.closest('dialog').close()" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="space-y-3 px-6 py-5">
                <p class="text-sm text-slate-600">Akun: <strong x-text="nama"></strong>. Satu akun boleh punya beberapa peran.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Peran::where('kode', '!=', 'super_admin')->orderBy('id')->get() as $p)
                        <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" class="check" value="{{ $p->kode }}" x-model="dipilih"> {{ $p->nama }}</label>
                    @endforeach
                </div>
                <p class="field-error" x-show="galat" x-text="galat"></p>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" class="btn btn-outline" @click="$el.closest('dialog').close()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Simpan</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    // Tombol "Reset" ditambahkan ke setiap baris tabel setelah digambar.
    document.addEventListener('DOMContentLoaded', () => {
        $(document).on('draw.dt', 'table', function () {
            const tabel = $(this).DataTable();
            $(this).find('tbody tr').each(function () {
                const data = tabel.row(this).data();
                if (!data || $(this).find('[data-reset-pw]').length) return;
                $('<button type="button" class="btn btn-soft btn-sm" data-ubah-peran title="Ubah peran"><i class="bi bi-person-gear"></i><span class="hidden xl:inline">Peran</span></button>')
                    .prependTo($(this).find('td:last > div'))
                    .on('click', () => window.dispatchEvent(new CustomEvent('ubah-peran', { detail: { id: data.id, name: data.name, peran: data.peran } })));
                $('<button type="button" class="btn btn-soft btn-sm" data-reset-pw title="Atur ulang password"><i class="bi bi-key"></i><span class="hidden xl:inline">Reset</span></button>')
                    .prependTo($(this).find('td:last > div'))
                    .on('click', () => window.dispatchEvent(new CustomEvent('reset-password', { detail: { id: data.id, name: data.name, role: data.peran.includes('admin') ? 'admin' : data.role.split('|')[0].toLowerCase() } })));
            });
        });
    });
</script>
@endpush
