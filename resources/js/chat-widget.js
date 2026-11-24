// Widget Konsultasi Online (pengunjung): jQuery + AJAX + polling. Markup: resources/views/chat/_widget.blade.php
//
// Alur: form data diri -> POST /chat/start -> token sesi disimpan di localStorage -> polling GET /chat/poll
// (3 dtk saat panel terbuka, 15 dtk saat diminimize, diperlambat saat tab tidak aktif) -> POST /chat/send.
// Semua teks pesan dimasukkan dengan .text() sehingga aman dari XSS.
import $ from 'jquery';
import { minta, beritahu, kedip, berhentiKedip } from './chat-notify';

const KEY = 'chat.token';
const INTERVAL_BUKA = 3000;
const INTERVAL_TUTUP = 15000;
const EMOJI = ['😊', '🙏', '👍', '😀', '😅', '😢', '❤️', '🎉', '📚', '✅', '❓', '🙂'];

const $root = $('#chat-widget');
if ($root.length) {
    initChat();
}

function initChat() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const urls = { start: $root.data('start'), send: $root.data('send'), poll: $root.data('poll'), typing: $root.data('typing'), end: $root.data('end'), upload: $root.data('upload'), faq: $root.data('faq'), status: $root.data('status'), leave: $root.data('leave') };
    const $panel = $('#chat-panel');
    const $fab = $('#chat-fab');
    const $badge = $('#chat-badge');
    const $list = $('#chat-messages');
    const $input = $('#chat-input');
    const $conn = $('#chat-conn');

    const state = { token: lsGet(KEY), lastId: 0, open: false, closed: false, inflight: false, timer: null, rendered: new Set(), typingSent: 0, gagal: 0, unread: 0, tolakTawaran: false, siap: false, statusLama: null, kedipN: 0 };

    // ------------------------------------------------------------ util
    function lsGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
    function lsSet(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch { /* mode privat */ } }

    function tampilkan(layar) {
        $('#chat-start, #chat-room').hide();
        $(layar).css('display', 'flex');
    }

    // ------------------------------------------------------------ panel
    function bukaPanel() {
        state.open = true;
        state.kedipN = 0;
        berhentiKedip();
        $panel.stop(true, true).css({ display: 'flex', opacity: 0, transform: 'translateY(12px)' }).animate({ opacity: 1 }, 180).css('transform', 'none');
        $fab.addClass('hidden');
        setBadge(0);
        if (state.token) {
            tampilkan('#chat-room');
            poll(true);
            scrollBawah();
            $input.trigger('focus');
        } else {
            tampilkan('#chat-start');
            cekPetugas();
        }
        jadwalkan();
    }

    /** Status petugas untuk header sebelum chat dimulai. */
    function cekPetugas() {
        $.getJSON(urls.status).done((r) => setPetugas(r.agents_online, r.jam_buka));
    }

    /** Status di header: di luar jam layanan / petugas online / belum tersedia. */
    function setPetugas(n, buka = true) {
        const online = buka && n > 0;
        $('#chat-dot').toggleClass('bg-emerald-400', online).toggleClass('bg-slate-400', !online);
        setStatus(!buka ? 'Di luar jam layanan' : n > 0 ? 'Petugas online' : 'Petugas belum tersedia');
    }

    function tutupPanel() {
        state.open = false;
        $panel.fadeOut(150);
        $fab.removeClass('hidden');
        jadwalkan();
    }

    $fab.on('click', bukaPanel);
    $(document).on('click', '[data-chat-open]', (e) => { e.preventDefault(); bukaPanel(); });
    $('#chat-minimize').on('click', tutupPanel);
    $(document).on('keydown', (e) => { if (e.key === 'Escape' && state.open) tutupPanel(); });

    $('#chat-close').on('click', () => {
        if (!state.token || state.closed) return tutupPanel();
        const lanjut = () => $.post(urls.end, { token: state.token }).always(() => poll(true)); // tampilkan layar selesai + penilaian
        if (window.Swal) {
            window.Swal.fire({ title: 'Akhiri percakapan?', text: 'Percakapan akan ditutup.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, akhiri', cancelButtonText: 'Batal', reverseButtons: true })
                .then((r) => r.isConfirmed && lanjut());
        } else if (window.confirm('Akhiri percakapan ini?')) {
            lanjut();
        }
    });

    function akhiriLokal() {
        lsSet(KEY, null);
        state.token = null; state.lastId = 0; state.closed = false; state.rendered.clear();
        $list.empty();
        $('#chat-form')[0].reset();
        $('#chat-ended').hide();
        $('#chat-compose').show();
        $('#chat-queue').hide();
        $('#chat-download').hide();
        nilaiRating = 0; warnaiBintang(0); $('#chat-rating-komentar').val(''); $('#chat-rating-galat').text(''); tampilRated(false);
        state.tolakTawaran = false;
        state.siap = false;
        state.statusLama = null;
        cekPetugas();
    }

    // ------------------------------------------------------------ mulai sesi
    $('#chat-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        $form.find('.field-error').text('');
        const $btn = $form.find('button[type=submit]').prop('disabled', true);

        $.ajax({ url: urls.start, method: 'POST', data: $form.serialize(), dataType: 'json' })
            .done((res) => {
                state.token = res.token;
                lsSet(KEY, res.token);
                state.lastId = 0; state.closed = false; state.rendered.clear(); $list.empty();
                terapkan(res);
                tampilkan('#chat-room');
                $input.trigger('focus');
                jadwalkan(); // mulai polling untuk sesi baru
                minta(); // izin notifikasi diminta setelah chat dimulai, bukan saat halaman dibuka
            })
            .fail((xhr) => {
                const errors = xhr.responseJSON?.errors || {};
                Object.keys(errors).forEach((k) => $form.find(`[data-error="${k}"]`).text(errors[k][0]));
                if (!Object.keys(errors).length) $form.find('[data-error="umum"]').text(xhr.status === 429 ? 'Terlalu banyak percobaan. Coba lagi sebentar.' : 'Gagal memulai. Periksa koneksi Anda.');
            })
            .always(() => $btn.prop('disabled', false));
    });

    // ------------------------------------------------------------ kirim pesan
    function kirim() {
        const teks = $input.val().trim();
        if (!teks || !state.token || state.closed) return;
        $input.val('').trigger('input');
        const $send = $('#chat-send').prop('disabled', true);

        $.ajax({ url: urls.send, method: 'POST', data: { token: state.token, body: teks }, dataType: 'json' })
            .done((res) => { renderPesan(res.message); (res.extra || []).forEach(renderPesan); scrollBawah(true); if (res.extra?.length) poll(true); }) // segarkan banner/status setelah bot menjawab
            .fail((xhr) => {
                $input.val(teks); // kembalikan agar tidak hilang
                tampilConn(xhr.status === 409 ? 'Sesi ini sudah selesai.' : xhr.status === 429 ? 'Terlalu cepat, tunggu sebentar.' : 'Pesan belum terkirim. Coba lagi.');
                if (xhr.status === 404) akhiriLokal();
            })
            .always(() => $send.prop('disabled', false));
    }

    $('#chat-compose').on('submit', (e) => { e.preventDefault(); kirim(); });
    $input.on('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); kirim(); } });
    $input.on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 96) + 'px';
        const sekarang = Date.now();
        if (state.token && !state.closed && $input.val() && sekarang - state.typingSent > 2500) {
            state.typingSent = sekarang;
            $.post(urls.typing, { token: state.token });
        }
    });

    // ------------------------------------------------------------ kirim gambar/PDF (FormData + progress)
    const $file = $('#chat-file');
    const $prog = $('#chat-progress');
    $('#chat-attach').on('click', () => $file.trigger('click'));
    $file.on('change', function () {
        const f = this.files[0];
        this.value = '';
        if (!f || !state.token || state.closed) return;
        if (f.size > 5 * 1024 * 1024) return tampilConn('Ukuran berkas maksimal 5 MB.');
        if (!/\.(jpe?g|png|pdf)$/i.test(f.name)) return tampilConn('Hanya berkas JPG, PNG, atau PDF.');

        const fd = new FormData();
        fd.append('token', state.token);
        fd.append('file', f);
        $prog.show().find('div').css('width', '0%');

        $.ajax({
            url: urls.upload, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            xhr() {
                const xhr = $.ajaxSettings.xhr();
                if (xhr.upload) xhr.upload.addEventListener('progress', (e) => e.lengthComputable && $prog.find('div').css('width', `${Math.round((e.loaded / e.total) * 100)}%`));
                return xhr;
            },
        })
            .done((res) => { tampilConn(''); renderPesan(res.message); scrollBawah(true); })
            .fail((xhr) => tampilConn(xhr.responseJSON?.errors?.file?.[0] || xhr.responseJSON?.message || 'Berkas gagal dikirim. Coba lagi.'))
            .always(() => $prog.hide());
    });

    // emoji sederhana
    const $emoji = $('#chat-emoji-pop');
    EMOJI.forEach((em) => $('<button type="button" class="rounded-md p-1 text-xl hover:bg-slate-100">').text(em).on('click', () => {
        const el = $input[0];
        const awal = el.selectionStart ?? $input.val().length;
        $input.val($input.val().slice(0, awal) + em + $input.val().slice(el.selectionEnd ?? awal)).trigger('input').trigger('focus');
        el.setSelectionRange(awal + em.length, awal + em.length);
        $emoji.hide();
    }).appendTo($emoji));
    $('#chat-emoji').on('click', () => $emoji.toggle());

    // ------------------------------------------------------------ render
    function ukuranTeks(b) {
        return b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;
    }

    /** Isi gelembung: teks (bila ada) dan lampiran (gambar = pratinjau, PDF = tautan). */
    function isiGelembung($bubble, m) {
        if (m.body) $bubble.append($('<div class="whitespace-pre-wrap break-words">').text(m.body));
        (m.attachments || []).forEach((a) => {
            if (a.is_image) {
                $bubble.append($('<a target="_blank" rel="noopener" class="mt-1 block">').attr('href', a.url).append($('<img class="max-h-44 rounded-lg" loading="lazy" alt="">').attr('src', a.url)));
            } else {
                $bubble.append($('<a target="_blank" rel="noopener" class="mt-1 flex items-center gap-2 rounded-lg bg-black/10 px-2.5 py-1.5 text-xs font-semibold underline">').attr('href', a.url)
                    .append($('<i class="bi bi-file-earmark-pdf text-base">'), $('<span class="truncate">').text(a.name), $('<span class="shrink-0 opacity-70">').text(ukuranTeks(a.size))));
            }
        });
        return $bubble;
    }

    function renderPesan(m) {
        if (state.rendered.has(m.id)) return;
        state.rendered.add(m.id);
        state.lastId = Math.max(state.lastId, m.id);

        let $el;
        if (m.sender === 'system') {
            $el = $('<div class="mx-auto max-w-[90%] rounded-full bg-slate-200/70 px-3 py-1.5 text-center text-xs text-slate-600">').text(m.body);
        } else if (m.sender === 'visitor') {
            $el = $('<div class="ml-auto flex max-w-[85%] flex-col items-end">').append(
                isiGelembung($('<div class="rounded-2xl rounded-br-md bg-primary px-3.5 py-2 text-sm text-white shadow-sm">'), m),
                $('<div class="mt-0.5 flex items-center gap-1 text-[0.7rem] text-slate-400">').append(
                    $('<span>').text(m.time),
                    $('<span class="chat-tick">').attr('data-id', m.id).text(m.read ? '✓✓ Dibaca' : '✓ Terkirim'),
                ),
            );
        } else {
            const nama = m.sender === 'bot' ? 'Asisten' : (m.name || 'Petugas');
            $el = $('<div class="flex max-w-[88%] items-end gap-2">').append(
                $('<span class="grid size-7 shrink-0 place-items-center rounded-full bg-accent text-xs font-bold text-ink">').text(nama.charAt(0).toUpperCase()),
                $('<div class="min-w-0">').append(
                    $('<div class="mb-0.5 text-[0.7rem] font-semibold text-slate-500">').text(nama),
                    isiGelembung($('<div class="rounded-2xl rounded-bl-md border border-slate-200 bg-white px-3.5 py-2 text-sm text-ink shadow-sm">'), m),
                    $('<div class="mt-0.5 text-[0.7rem] text-slate-400">').text(m.time),
                ),
            );
        }
        if (m.buttons?.length) {
            $list.find('.chat-faq-btns').remove(); // hanya tombol terbaru yang aktif
            $el.find('.min-w-0').first().append(tombolFaq(m.buttons));
        }
        $list.append($el);
    }

    /** Tombol pilihan cepat dari chatbot. */
    function tombolFaq(daftar) {
        const $g = $('<div class="chat-faq-btns mt-2 flex flex-wrap gap-1.5">');
        daftar.forEach((b) => $('<button type="button" class="rounded-full border border-primary/30 bg-white px-3 py-1 text-xs font-semibold text-primary hover:bg-primary hover:text-white">').text(b.label).on('click', function () {
            if (!state.token || state.closed) return;
            $g.find('button').prop('disabled', true);
            $.ajax({ url: urls.faq, method: 'POST', data: { token: state.token, faq_id: b.id }, dataType: 'json' })
                .done((res) => { renderPesan(res.message); (res.extra || []).forEach(renderPesan); scrollBawah(true); })
                .fail((xhr) => { $g.find('button').prop('disabled', false); tampilConn(xhr.status === 429 ? 'Terlalu cepat, tunggu sebentar.' : 'Gagal memuat jawaban. Coba lagi.'); setTimeout(() => tampilConn(''), 3000); });
        }).appendTo($g));
        return $g;
    }

    function scrollBawah(paksa) {
        const el = $list[0];
        const dekat = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
        if (paksa || dekat) el.scrollTop = el.scrollHeight;
    }

    function setStatus(teks) { $('#chat-status').text(teks); }

    function setBadge(n) {
        const naik = n > state.unread;
        state.unread = n;
        $badge.text(n > 9 ? '9+' : n).toggleClass('hidden', n === 0);
        $fab.toggleClass('chat-pulse', naik && n > 0);
    }

    function tampilConn(teks) {
        $conn.text(teks || '').toggle(!!teks);
    }

    /** Terapkan balasan server (poll/start) ke tampilan. */
    function terapkan(res) {
        const baru = (res.messages || []).filter((m) => !state.rendered.has(m.id));
        const dariPetugas = baru.filter((m) => m.sender === 'agent' || m.sender === 'bot');
        const tidakDilihat = !state.open || document.hidden;

        if (state.siap && tidakDilihat) {
            if (state.statusLama === 'menunggu' && res.status === 'dilayani') {
                beritahu('Giliran Anda', `Anda terhubung dengan ${res.agent || 'petugas'}.`, { tag: 'chat-giliran', onClick: bukaPanel });
                state.kedipN++;
            } else if (dariPetugas.length) {
                const m = dariPetugas[dariPetugas.length - 1];
                beritahu(`Pesan baru dari ${m.name || 'Petugas'}`, m.body || '[lampiran]', { tag: 'chat-pesan', onClick: bukaPanel });
                state.kedipN += dariPetugas.length;
            }
            if (state.kedipN) kedip(`(${state.kedipN}) Pesan baru`);
        }
        state.statusLama = res.status;
        state.siap = true;

        (res.messages || []).forEach(renderPesan);
        if (res.messages?.length) scrollBawah();

        $('.chat-tick').each(function () {
            if (Number($(this).data('id')) <= res.read_upto) $(this).text('✓✓ Dibaca');
        });

        $('#chat-typing').toggle(!!res.agent_typing);
        if (res.closed) {
            $('#chat-dot').removeClass('bg-emerald-400').addClass('bg-slate-400');
            setStatus('Percakapan selesai');
        } else if (res.status === 'dilayani') {
            $('#chat-dot').addClass('bg-emerald-400').removeClass('bg-slate-400');
            setStatus(`Dilayani${res.agent ? ' oleh ' + res.agent : ''}`);
        } else {
            setPetugas(res.agents_online ?? 0, res.jam_buka !== false);
        }
        tampilAntrian(res);
        if (res.export_url) $('#chat-download, #chat-download2').attr('href', res.export_url).filter('#chat-download').css('display', 'grid');
        if (res.closed) tampilRated(res.rated);

        if (res.closed && !state.closed) {
            state.closed = true;
            $('#chat-compose').hide();
            $('#chat-ended').css('display', 'flex');
        }
        if (!state.open) setBadge(res.unread || 0);
    }

    /** Banner antrian: posisi, estimasi tunggu, dan tawaran balas lewat email bila menunggu > 10 menit. */
    function tampilAntrian(res) {
        const q = res.queue;
        if (res.closed || (!q && !res.balas_email) || (res.bot_mode && !res.balas_email)) return $('#chat-queue').hide();

        let teks;
        if (res.balas_email) {
            teks = 'Pesan Anda akan dibalas lewat email.';
        } else {
            teks = `Anda di antrian ke-${q.position} · estimasi tunggu ±${q.estimate_min} menit`;
            if (!q.agents_online) teks += ' (menunggu petugas tersedia)';
        }
        $('#chat-queue-text').text(teks);
        $('#chat-offer').toggle(!!q?.offer_email && !state.tolakTawaran);
        $('#chat-queue').show();
    }

    $('#chat-wait').on('click', () => { state.tolakTawaran = true; $('#chat-offer').hide(); });
    $('#chat-leave-email').on('click', () => {
        $.post(urls.leave, { token: state.token, last_id: state.lastId }).done(terapkan).fail(() => tampilConn('Gagal menyimpan pilihan. Coba lagi.'));
    });

    // ------------------------------------------------------------ rating setelah sesi selesai
    let nilaiRating = 0;
    const warnaiBintang = (n) => $('.chat-star').each(function () { $(this).toggleClass('text-accent', Number($(this).data('n')) <= n).toggleClass('text-slate-300', Number($(this).data('n')) > n); });
    $('.chat-star').on('mouseenter', function () { warnaiBintang(Number($(this).data('n'))); }).on('mouseleave', () => warnaiBintang(nilaiRating)).on('click', function () { nilaiRating = Number($(this).data('n')); warnaiBintang(nilaiRating); });
    $('#chat-rating-kirim').on('click', () => {
        if (!nilaiRating) return $('#chat-rating-galat').text('Pilih jumlah bintang.');
        $.post($root.data('rate'), { token: state.token, rating: nilaiRating, komentar: $('#chat-rating-komentar').val() })
            .done(() => tampilRated(true))
            .fail((xhr) => (xhr.status === 409 ? tampilRated(true) : $('#chat-rating-galat').text(xhr.responseJSON?.errors?.rating?.[0] || xhr.responseJSON?.message || 'Gagal menyimpan.')));
    });

    function tampilRated(sudah) {
        $('#chat-rating').toggle(!sudah);
        $('#chat-rating-terima').toggle(!!sudah);
    }

    // ------------------------------------------------------------ polling
    function poll(segera) {
        if (!state.token || state.closed || state.inflight) return;
        state.inflight = true;

        $.ajax({ url: urls.poll, method: 'GET', data: { token: state.token, last_id: state.lastId, open: state.open ? 1 : 0 }, dataType: 'json', timeout: 10000 })
            .done((res) => { state.gagal = 0; tampilConn(''); terapkan(res); })
            .fail((xhr) => {
                if (xhr.status === 404) return akhiriLokal();
                state.gagal++;
                tampilConn('Koneksi terputus, mencoba lagi...');
            })
            .always(() => { state.inflight = false; if (!segera) jadwalkan(); });
    }

    function jadwalkan() {
        clearTimeout(state.timer);
        if (!state.token || state.closed) return;
        let jeda = state.open ? INTERVAL_BUKA : INTERVAL_TUTUP;
        if (document.hidden) jeda *= 3;
        if (state.gagal) jeda = Math.min(jeda * (1 + state.gagal), 30000);
        state.timer = setTimeout(() => poll(false), jeda);
    }

    $(document).on('visibilitychange', () => { if (!document.hidden) { clearTimeout(state.timer); poll(false); } });

    $('#chat-restart').on('click', akhiriLokal).on('click', () => tampilkan('#chat-start'));

    // ------------------------------------------------------------ mulai: pulihkan sesi bila ada
    if (state.token) {
        poll(false); // memuat pesan + badge walau panel tertutup
    }
}
