@extends('layouts.admin')

@section('title', 'Konsultasi Online')

@section('content')
    <x-admin.page-header title="Konsultasi Online" subtitle="Layani pengunjung secara langsung. Layani beberapa chat sekaligus lewat tab.">
        <button type="button" id="ac-notif" class="btn btn-outline btn-sm" style="display:none"><i class="bi bi-bell"></i> Aktifkan notifikasi</button>
        <div class="flex items-center gap-2" id="ac-status-box">
            <span class="text-sm font-medium text-slate-500">Status saya</span>
            <select id="ac-status" class="select !w-auto" aria-label="Status petugas">
                <option value="online">🟢 Online</option>
                <option value="istirahat">🟡 Istirahat</option>
                <option value="offline">⚪ Offline</option>
            </select>
        </div>
    </x-admin.page-header>

    <x-admin.subnav :items="[['Percakapan Langsung', route('admin/chat'), ['admin/chat']], ['Riwayat', route('admin/chat.riwayat'), ['admin/chat/riwayat*']], ['Statistik', route('admin/chat.statistik'), ['admin/chat/statistik']], ['Pengaturan', route('admin/chat.pengaturan'), ['admin/chat/pengaturan']]]" />

    <div id="admin-chat" class="card grid min-h-[34rem] overflow-hidden md:grid-cols-[19rem_1fr]"
         data-queue="{{ route('admin/chat.queue') }}" data-base="{{ url('admin/chat') }}" data-status-url="{{ route('admin/chat.status') }}"
         data-quick='@json($balasanCepat)'>
        {{-- Antrian & chat saya --}}
        <aside class="max-h-[34rem] overflow-y-auto border-b border-slate-100 md:border-r md:border-b-0" aria-label="Antrian dan chat saya">
            <p id="ac-conn" class="bg-amber-100 px-4 py-2 text-xs font-semibold text-amber-800" style="display:none">Koneksi terputus, mencoba lagi...</p>
            <h2 class="px-4 pt-3 pb-1 text-xs font-bold tracking-wide text-slate-400 uppercase">Antrian <span id="ac-n-wait" class="ml-1 rounded-full bg-amber-100 px-1.5 text-amber-800">0</span></h2>
            <div id="ac-wait" class="divide-y divide-slate-100"></div>
            <h2 class="px-4 pt-4 pb-1 text-xs font-bold tracking-wide text-slate-400 uppercase">Chat saya <span id="ac-n-mine" class="ml-1 rounded-full bg-emerald-100 px-1.5 text-emerald-800">0</span></h2>
            <div id="ac-mine" class="divide-y divide-slate-100"></div>
            <h2 class="px-4 pt-4 pb-1 text-xs font-bold tracking-wide text-slate-400 uppercase">Petugas</h2>
            <ul id="ac-agents" class="px-4 pb-4 text-sm"></ul>
        </aside>

        {{-- Ruang percakapan --}}
        <section class="flex min-h-[30rem] min-w-0 flex-col">
            <div id="ac-tabs" class="flex gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50 px-2 pt-2" role="tablist" style="display:none"></div>

            <div id="ac-empty" class="grid flex-1 place-items-center p-8 text-center text-slate-500">
                <div><i class="bi bi-chat-square-text text-4xl text-slate-300"></i><p class="mt-2 text-sm">Pilih percakapan dari antrian atau "Chat saya".</p></div>
            </div>

            <div id="ac-room" class="min-h-0 flex-1 flex-col" style="display:none">
                <header class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p id="ac-nama" class="truncate font-display font-bold"></p>
                        <p id="ac-info" class="truncate text-xs text-slate-500"></p>
                    </div>
                    <button type="button" id="ac-take" class="btn btn-accent btn-sm" style="display:none"><i class="bi bi-hand-index-thumb"></i> Ambil chat</button>
                    <select id="ac-transfer" class="select !w-auto !py-1.5 text-sm" aria-label="Alihkan ke petugas lain"><option value="">Alihkan ke...</option></select>
                    <button type="button" id="ac-close" class="btn btn-outline btn-sm"><i class="bi bi-check2-circle"></i> Tutup sesi</button>
                </header>
                <p id="ac-email-note" class="bg-amber-50 px-5 py-2 text-xs font-semibold text-amber-800" style="display:none"><i class="bi bi-envelope"></i> Pengunjung memilih dibalas lewat email.</p>
                <div id="ac-messages" class="flex-1 space-y-3 overflow-y-auto bg-surface p-4" style="max-height:24rem"></div>
                <p id="ac-typing" class="bg-surface px-4 pb-1 text-xs text-slate-500 italic" style="display:none">Pengunjung sedang mengetik...</p>
                <div id="ac-quick" class="flex gap-1.5 overflow-x-auto border-t border-slate-100 px-3 pt-2"></div>
                <div id="ac-progress" class="h-1 bg-slate-200" style="display:none"><div class="h-full bg-accent transition-all" style="width:0"></div></div>
                <form id="ac-form" class="flex items-end gap-2 p-3">
                    <input type="file" id="ac-file" accept="image/jpeg,image/png,application/pdf" class="hidden" tabindex="-1">
                    <button type="button" id="ac-attach" class="btn btn-outline" aria-label="Kirim gambar atau PDF"><i class="bi bi-paperclip"></i></button>
                    <textarea id="ac-input" rows="2" maxlength="1000" placeholder="Tulis balasan..." aria-label="Balasan" class="textarea flex-1"></textarea>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i> Kirim</button>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/admin-chat.js')
@endpush
