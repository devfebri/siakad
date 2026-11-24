@extends('layouts.admin')

@section('title', 'Pengaturan Konsultasi Online')

@section('content')
    <x-admin.page-header title="Konsultasi Online" subtitle="Atur jam layanan, hari libur, dan asisten otomatis (chatbot FAQ)." />

    <x-admin.subnav :items="[['Percakapan Langsung', route('admin/chat'), ['admin/chat']], ['Riwayat', route('admin/chat.riwayat'), ['admin/chat/riwayat*']], ['Statistik', route('admin/chat.statistik'), ['admin/chat/statistik']], ['Pengaturan', route('admin/chat.pengaturan'), ['admin/chat/pengaturan']]]" />

    <form data-ajax-form data-method="PUT" novalidate action="{{ route('admin/chat.pengaturan.simpan') }}" class="card mb-6">
        <div class="card-header">Jam Layanan & Asisten Otomatis
            <span @class(['badge', 'badge-success' => $statusSekarang, 'badge-muted' => ! $statusSekarang])>{{ $statusSekarang ? 'Sedang buka' : 'Sedang tutup' }} · {{ $labelJam }}</span>
        </div>
        <div class="grid gap-5 p-5 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="jam_buka" class="label">Jam buka</label>
                        <input id="jam_buka" name="jam_buka" type="time" value="{{ $jam['buka'] }}" class="input" required>
                    </div>
                    <div>
                        <label for="jam_tutup" class="label">Jam tutup</label>
                        <input id="jam_tutup" name="jam_tutup" type="time" value="{{ $jam['tutup'] }}" class="input" required>
                    </div>
                </div>
                <div>
                    <label for="zona" class="label">Zona waktu</label>
                    <select id="zona" name="zona" class="select">
                        @foreach ($zonaPilihan as $kode => $nama)<option value="{{ $kode }}" @selected($zonaDipilih === $kode)>{{ $nama }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="maks_chat" class="label">Maks. chat / petugas</label>
                        <input id="maks_chat" name="maks_chat" type="number" min="1" max="20" value="{{ $batas['maks_chat'] }}" class="input" required>
                    </div>
                    <div>
                        <label for="tutup_otomatis_jam" class="label">Tutup otomatis (jam)</label>
                        <input id="tutup_otomatis_jam" name="tutup_otomatis_jam" type="number" min="0" max="720" value="{{ $batas['tutup_otomatis_jam'] }}" class="input" required>
                    </div>
                    <div>
                        <label for="retensi_hari" class="label">Simpan riwayat (hari)</label>
                        <input id="retensi_hari" name="retensi_hari" type="number" min="0" max="3650" value="{{ $batas['retensi_hari'] }}" class="input" required>
                    </div>
                </div>
                <p class="field-hint -mt-2">Chat tanpa aktivitas ditutup otomatis setelah sekian jam; riwayat lebih lama dari masa simpan dihapus tiap malam. Isi 0 untuk menonaktifkan.</p>
                <fieldset>
                    <legend class="label">Hari layanan</legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($namaHari as $no => $nama)
                            <label class="flex cursor-pointer items-center gap-1.5 text-sm"><input type="checkbox" name="hari[]" value="{{ $no }}" class="check" @checked(in_array($no, $jam['hari'], true))> {{ $nama }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="hari_libur" class="label">Hari libur <span class="font-normal text-slate-400">(satu per baris)</span></label>
                    <textarea id="hari_libur" name="hari_libur" rows="4" class="textarea" placeholder="2026-12-25 Hari Natal&#10;2027-01-01 Tahun Baru">{{ $liburTeks }}</textarea>
                    <p class="field-hint">Format: TAHUN-BULAN-TANGGAL lalu keterangan. Pada hari libur layanan dianggap tutup.</p>
                </div>
            </div>

            <div class="space-y-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold"><input type="checkbox" name="bot_aktif" value="1" class="check" @checked($bot['aktif'])> Aktifkan asisten otomatis (chatbot FAQ) di luar jam layanan atau saat tidak ada petugas online</label>
                <div>
                    <label for="bot_sapaan" class="label">Sapaan pembuka</label>
                    <input id="bot_sapaan" name="bot_sapaan" value="{{ $bot['sapaan'] }}" maxlength="60" class="input" placeholder="Halo">
                </div>
                <div>
                    <label for="bot_kata_alihkan" class="label">Kata kunci pertanyaan yang tidak dijawab bot</label>
                    <textarea id="bot_kata_alihkan" name="bot_kata_alihkan" rows="2" maxlength="600" class="textarea">{{ $bot['kata_alihkan'] }}</textarea>
                    <p class="field-hint">Pisahkan dengan koma. Pertanyaan yang mengandung kata ini tidak dijawab otomatis (mis. pertanyaan hukum agama/fatwa).</p>
                </div>
                <div>
                    <label for="bot_pesan_alihkan" class="label">Pesan pengalihan</label>
                    <textarea id="bot_pesan_alihkan" name="bot_pesan_alihkan" rows="3" maxlength="500" class="textarea">{{ $bot['pesan_alihkan'] }}</textarea>
                </div>
            </div>
        </div>
        <div class="flex justify-end border-t border-slate-100 px-5 py-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Simpan Pengaturan</button>
        </div>
    </form>

    <x-admin.crud title="Template Balasan Cepat" name="Template" subtitle="Tombol jawaban singkat di ruang chat petugas." class="mb-6"
        :url="route('admin/chat/balasan.data')" :store="route('admin/chat/balasan.store')" :update="url('admin/chat/balasan/:id')" :destroy="url('admin/chat/balasan/:id')">
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="urutan" class="w-16">Urutan</th>
            <th data-col="judul">Judul</th>
            <th data-col="ringkas" class="no-sort">Isi</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="judul" id="br_judul" label="Judul tombol" class="sm:col-span-2" maxlength="60" required />
            <x-admin.field name="isi" id="br_isi" label="Isi balasan" as="textarea" class="sm:col-span-2" rows="4" maxlength="1000" required />
            <x-admin.field name="urutan" id="br_urutan" label="Urutan" type="number" min="0" value="0" />
            <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="aktif" value="1" class="check" checked> Aktif</label>
        </x-slot:fields>
    </x-admin.crud>

    <x-admin.crud title="FAQ Chatbot" name="FAQ" subtitle="Jawaban otomatis berdasarkan kata kunci. Lima FAQ teratas (urutan terkecil) tampil sebagai tombol pilihan cepat."
        :url="route('admin/chat/faq.data')" :store="route('admin/chat/faq.store')" :update="url('admin/chat/faq/:id')" :destroy="url('admin/chat/faq/:id')">
        <x-slot:columns>
            <th data-col="no" class="w-12">No</th>
            <th data-col="urutan" class="w-16">Urutan</th>
            <th data-col="pertanyaan">Pertanyaan / Tombol</th>
            <th data-col="kata_kunci" class="no-sort">Kata kunci</th>
            <th data-col="status" data-cell="badge" class="no-sort">Status</th>
            <th data-col="actions" class="no-sort text-right">Aksi</th>
        </x-slot:columns>
        <x-slot:fields>
            <x-admin.field name="pertanyaan" id="faq_tanya" label="Pertanyaan (teks tombol)" class="sm:col-span-2" maxlength="200" required />
            <x-admin.field name="kata_kunci" id="faq_kunci" label="Kata kunci" class="sm:col-span-2" maxlength="300" required hint="Pisahkan dengan koma, mis. ppdb, daftar, pendaftaran. Semakin banyak kata kunci cocok, semakin tepat jawabannya." />
            <x-admin.field name="jawaban" id="faq_jawab" label="Jawaban" as="textarea" class="sm:col-span-2" rows="5" maxlength="2000" required hint="Boleh memakai {nama_sekolah} {alamat} {telepon} {email} {whatsapp} {jam_layanan} {ppdb_url} {agenda_url} {kontak_url}." />
            <x-admin.field name="urutan" id="faq_urutan" label="Urutan" type="number" min="0" value="0" />
            <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="aktif" value="1" class="check" checked> Aktif</label>
        </x-slot:fields>
    </x-admin.crud>
@endsection
