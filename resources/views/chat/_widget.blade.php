{{-- Widget Konsultasi Online (pojok kanan bawah). Logika: resources/js/chat-widget.js (jQuery + AJAX). --}}
<div id="chat-widget" class="print:hidden"
     data-start="{{ route('chat.start') }}" data-send="{{ route('chat.send') }}" data-poll="{{ route('chat.poll') }}"
     data-typing="{{ route('chat.typing') }}" data-end="{{ route('chat.end') }}" data-upload="{{ route('chat.upload') }}" data-rate="{{ route('chat.rate') }}" data-faq="{{ route('chat.faq.pilih') }}"
     data-status="{{ route('chat.status') }}" data-leave="{{ route('chat.leave') }}">

    <button type="button" id="chat-fab" aria-label="Buka konsultasi online"
            class="fixed right-5 bottom-5 z-50 grid size-14 place-items-center rounded-full bg-primary text-2xl text-white shadow-xl shadow-primary/30 transition hover:scale-105 hover:bg-primary-deep">
        <i class="bi bi-chat-dots-fill"></i>
        <span id="chat-badge" class="absolute -top-1 -right-1 hidden min-w-5 rounded-full bg-red-600 px-1.5 text-center text-xs leading-5 font-bold text-white ring-2 ring-white">0</span>
    </button>

    <section id="chat-panel" role="dialog" aria-label="Konsultasi Online" style="display:none"
             class="fixed z-50 flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-slate-200 max-sm:inset-0 sm:right-5 sm:bottom-5 sm:h-[38rem] sm:max-h-[calc(100vh-2.5rem)] sm:w-[380px] sm:rounded-3xl">
        {{-- Header --}}
        <header class="flex items-center gap-3 bg-primary px-4 py-3 text-white">
            <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-accent text-xl text-ink">
                @if (config('school.logo'))
                    <img src="{{ asset(config('school.logo')) }}" alt="" class="size-full bg-white object-contain">
                @else
                    <i class="bi bi-mortarboard-fill"></i>
                @endif
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="truncate font-display font-bold">Konsultasi Online</p>
                <p class="flex items-center gap-1.5 text-xs text-white/75"><span id="chat-dot" class="size-2 rounded-full bg-slate-400"></span><span id="chat-status">Memeriksa petugas...</span></p>
            </div>
            <a href="#" id="chat-download" class="grid size-8 place-items-center rounded-full text-lg text-white no-underline hover:bg-white/15" aria-label="Unduh percakapan (PDF)" title="Unduh percakapan (PDF)" style="display:none"><i class="bi bi-download"></i></a>
            <button type="button" id="chat-minimize" class="grid size-8 place-items-center rounded-full text-lg hover:bg-white/15" aria-label="Minimize"><i class="bi bi-dash-lg"></i></button>
            <button type="button" id="chat-close" class="grid size-8 place-items-center rounded-full text-lg hover:bg-white/15" aria-label="Tutup dan akhiri"><i class="bi bi-x-lg"></i></button>
        </header>

        {{-- Form data diri --}}
        <div id="chat-start" class="min-h-0 flex-1 flex-col overflow-y-auto bg-surface p-5" style="display:flex">
            <p class="font-display text-lg font-bold text-ink">Halo! Ada yang bisa kami bantu?</p>
            <p class="mt-1 mb-4 text-sm text-slate-600">Isi data singkat berikut untuk memulai percakapan dengan petugas {{ config('school.short_name') }}.</p>
            <form id="chat-form" class="space-y-3" novalidate>
                <div>
                    <label for="chat-nama" class="label">Nama</label>
                    <input id="chat-nama" name="nama" class="input" maxlength="100" autocomplete="name" required>
                    <p class="field-error" data-error="nama"></p>
                </div>
                <div>
                    <label for="chat-email" class="label">Email</label>
                    <input id="chat-email" name="email" type="email" class="input" maxlength="150" autocomplete="email" required>
                    <p class="field-error" data-error="email"></p>
                </div>
                <div>
                    <label for="chat-wa" class="label">No. WhatsApp <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="chat-wa" name="whatsapp" type="tel" class="input" maxlength="25" autocomplete="tel">
                    <p class="field-error" data-error="whatsapp"></p>
                </div>
                <div>
                    <label for="chat-topik" class="label">Topik</label>
                    <select id="chat-topik" name="topik" class="select" required>
                        @foreach (\App\ChatSession::TOPIK as $topik)
                            <option value="{{ $topik }}">{{ $topik }}</option>
                        @endforeach
                    </select>
                    <p class="field-error" data-error="topik"></p>
                </div>
                <label class="flex cursor-pointer items-start gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="persetujuan" value="1" class="check mt-0.5"> <span>Saya setuju data dan isi percakapan ini disimpan untuk keperluan layanan.</span>
                </label>
                <p class="field-error" data-error="persetujuan"></p>
                <p class="field-error" data-error="umum"></p>
                <button type="submit" class="btn btn-primary w-full"><i class="bi bi-chat-dots"></i> Mulai Percakapan</button>
            </form>
        </div>

        {{-- Ruang chat --}}
        <div id="chat-room" class="min-h-0 flex-1 flex-col" style="display:none">
            <p id="chat-conn" class="bg-amber-100 px-3 py-1.5 text-center text-xs font-semibold text-amber-800" style="display:none"></p>
            <div id="chat-queue" class="border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" style="display:none">
                <p class="font-semibold"><i class="bi bi-hourglass-split"></i> <span id="chat-queue-text"></span></p>
                <div id="chat-offer" class="mt-2 text-xs" style="display:none">
                    <p>Antrian cukup panjang. Mau tetap menunggu, atau tinggalkan pesan dan dibalas lewat email?</p>
                    <div class="mt-1.5 flex gap-2">
                        <button type="button" id="chat-wait" class="btn btn-outline btn-sm">Tetap menunggu</button>
                        <button type="button" id="chat-leave-email" class="btn btn-primary btn-sm">Balas lewat email</button>
                    </div>
                </div>
            </div>
            <div id="chat-messages" class="flex-1 space-y-3 overflow-y-auto bg-surface p-4" aria-live="polite"></div>
            <div id="chat-progress" class="h-1 bg-slate-200" style="display:none"><div class="h-full bg-accent transition-all" style="width:0"></div></div>
            <p id="chat-typing" class="bg-surface px-4 pb-1 text-xs text-slate-500 italic" style="display:none">Petugas sedang mengetik...</p>

            <form id="chat-compose" class="relative flex items-end gap-2 border-t border-slate-200 bg-white p-3">
                <div id="chat-emoji-pop" class="absolute bottom-full left-3 mb-1 grid grid-cols-6 gap-0.5 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" style="display:none"></div>
                <input type="file" id="chat-file" accept="image/jpeg,image/png,application/pdf" class="hidden" tabindex="-1">
                <button type="button" id="chat-attach" class="grid size-10 shrink-0 place-items-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Kirim gambar atau PDF"><i class="bi bi-paperclip"></i></button>
                <button type="button" id="chat-emoji" class="grid size-10 shrink-0 place-items-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Emoji"><i class="bi bi-emoji-smile"></i></button>
                <textarea id="chat-input" rows="1" maxlength="1000" placeholder="Tulis pesan..." aria-label="Pesan"
                          class="max-h-24 min-h-10 flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm outline-none focus:border-primary focus:bg-white"></textarea>
                <button type="submit" id="chat-send" class="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-white hover:bg-primary-deep disabled:opacity-50" aria-label="Kirim"><i class="bi bi-send-fill"></i></button>
            </form>

            <div id="chat-ended" class="flex-col items-center gap-2 border-t border-slate-200 bg-white p-4 text-center" style="display:none">
                <p class="text-sm font-semibold text-slate-700">Percakapan ini telah selesai.</p>
                <div id="chat-rating" class="w-full">
                    <p class="text-xs text-slate-500">Bagaimana layanan kami?</p>
                    <div class="my-1 flex justify-center gap-1 text-2xl">
                        @foreach (range(1, 5) as $n)<button type="button" class="chat-star leading-none text-slate-300 hover:scale-110" data-n="{{ $n }}" aria-label="{{ $n }} bintang">★</button>@endforeach
                    </div>
                    <textarea id="chat-rating-komentar" rows="2" maxlength="300" placeholder="Komentar singkat (opsional)" class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-primary"></textarea>
                    <p id="chat-rating-galat" class="field-error"></p>
                    <button type="button" id="chat-rating-kirim" class="btn btn-accent btn-sm mt-1">Kirim Penilaian</button>
                </div>
                <p id="chat-rating-terima" class="text-sm text-emerald-700" style="display:none">Terima kasih atas penilaian Anda!</p>
                <a href="#" id="chat-download2" class="text-xs font-semibold text-primary"><i class="bi bi-file-earmark-pdf"></i> Unduh percakapan (PDF)</a>
                <button type="button" id="chat-restart" class="btn btn-primary btn-sm">Mulai Percakapan Baru</button>
            </div>
        </div>
    </section>
</div>

@vite('resources/js/chat-widget.js')
