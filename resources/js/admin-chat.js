// Panel petugas Konsultasi Online (admin): jQuery + AJAX + polling. Markup: resources/views/chat/admin.blade.php
// - Antrian/"Chat saya"/petugas: GET /admin/chat/queue tiap 5 detik (sekaligus tanda petugas masih aktif).
// - Chat yang sedang dibuka: poll tiap 3 detik. Beberapa chat dibuka sebagai tab.
// - Tidak ada request bertumpuk; interval diperlambat saat tab browser tidak aktif.
import $ from 'jquery';
import { izin, minta, beritahu, kedip } from './chat-notify';

const $root = $('#admin-chat');
if ($root.length) {
    initAdminChat();
}

function initAdminChat() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const base = $root.data('base');
    const $msgs = $('#ac-messages');
    const state = { active: null, tabs: [], names: {}, lastId: 0, rendered: new Set(), qBusy: false, rBusy: false, tQ: null, tR: null, me: null, data: null, dilihat: null };

    const jeda = (ms) => (document.hidden ? ms * 3 : ms);

    // ------------------------------------------------------------ antrian
    function muatQueue() {
        if (state.qBusy) return;
        state.qBusy = true;
        $.getJSON($root.data('queue'))
            .done((res) => { $('#ac-conn').hide(); state.data = res; state.me = res.me; kabarkanBaru(res); gambar(res); })
            .fail(() => $('#ac-conn').show())
            .always(() => { state.qBusy = false; clearTimeout(state.tQ); state.tQ = setTimeout(muatQueue, jeda(5000)); });
    }

    /** Notifikasi browser + bunyi saat ada chat baru masuk antrian (dan saat chat dibagikan ke saya). */
    function kabarkanBaru(res) {
        const sekarang = new Set([...res.waiting, ...res.mine].map((s) => s.id));
        if (state.dilihat) {
            const baruAntri = res.waiting.filter((s) => !state.dilihat.has(s.id));
            const baruMilik = res.mine.filter((s) => !state.dilihat.has(s.id) && !state.tabs.includes(s.id));
            const daftar = [...baruAntri.map((s) => ['Chat baru di antrian', `${s.nama} · ${s.topik}`]), ...baruMilik.map((s) => ['Chat baru untuk Anda', `${s.nama} · ${s.topik}`])];
            if (daftar.length) {
                beritahu(daftar[0][0], daftar[0][1] + (daftar.length > 1 ? ` (+${daftar.length - 1} lagi)` : ''), { tag: 'chat-antrian', onClick: () => window.focus() });
                kedip(`(${daftar.length}) Chat baru`);
            }
        }
        state.dilihat = sekarang;
    }

    function barisSesi(s, aksi) {
        const $b = $('<div class="flex items-start gap-2 px-4 py-3 hover:bg-primary-soft">').toggleClass('bg-primary-soft', s.id === state.active);
        const $isi = $('<button type="button" class="min-w-0 flex-1 text-left">').append(
            $('<div class="flex items-center gap-2">').append(
                $('<span class="min-w-0 flex-1 truncate font-semibold">').text(s.nama),
                s.belum_dibaca ? $('<span class="rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">').text(s.belum_dibaca) : '',
            ),
            $('<div class="truncate text-xs text-slate-500">').text(`${s.topik} · ${s.menunggu}`),
            s.balas_email ? $('<span class="badge badge-accent mt-1">').text('balas via email') : '',
        ).on('click', () => buka(s.id, s.nama));
        $b.append($isi);
        if (aksi) $b.append(aksi);
        return $b;
    }

    function gambar(res) {
        state.names = { ...state.names, ...Object.fromEntries([...res.waiting, ...res.mine].map((s) => [s.id, s.nama])) };

        $('#ac-n-wait').text(res.waiting.length);
        $('#ac-n-mine').text(res.mine.length);
        $('#ac-status').val(res.me.status);

        const $w = $('#ac-wait').empty();
        if (!res.waiting.length) $w.append($('<p class="px-4 py-3 text-sm text-slate-500">').text('Tidak ada antrian.'));
        res.waiting.forEach((s) => $w.append(barisSesi(s, $('<button type="button" class="btn btn-accent btn-sm shrink-0">').text('Ambil chat').on('click', () => ambil(s.id, s.nama)))));

        const $m = $('#ac-mine').empty();
        if (!res.mine.length) $m.append($('<p class="px-4 py-3 text-sm text-slate-500">').text('Belum ada chat.'));
        res.mine.forEach((s) => $m.append(barisSesi(s)));

        const $a = $('#ac-agents').empty();
        res.agents.forEach((a) => $a.append($('<li class="flex items-center gap-2 py-0.5">').append(
            $('<span class="size-2 rounded-full">').addClass(a.online ? 'bg-emerald-500' : 'bg-slate-300'),
            $('<span class="flex-1 truncate">').text(a.name + (a.id === res.me.id ? ' (saya)' : '')),
            $('<span class="text-xs text-slate-400">').text(`${a.beban}/${res.maks_chat}`),
        )));

        // menu "Alihkan ke" berisi petugas lain
        const $t = $('#ac-transfer').empty().append('<option value="">Alihkan ke...</option>');
        res.agents.filter((a) => a.id !== res.me.id).forEach((a) => $t.append($('<option>').val(a.id).text(`${a.name}${a.online ? ' • online' : ''}`)));

        renderTabs();
    }

    $('#ac-status').on('change', function () {
        const status = $(this).val();
        $.post($root.data('status-url'), { status }).done(() => { window.toast?.(`Status: ${status}`); muatQueue(); });
    });

    function ambil(id, nama) {
        $.post(`${base}/${id}/take`).done(() => { muatQueue(); buka(id, nama); }).fail((xhr) => { window.toast?.(xhr.responseJSON?.message || 'Gagal mengambil chat.', 'error'); muatQueue(); });
    }

    // ------------------------------------------------------------ tab & ruang
    function buka(id, nama) {
        if (nama) state.names[id] = nama;
        if (!state.tabs.includes(id)) state.tabs.push(id);
        pilih(id);
    }

    function pilih(id) {
        state.active = id; state.lastId = 0; state.rendered.clear(); $msgs.empty();
        $('#ac-empty').hide(); $('#ac-room').css('display', 'flex');
        clearTimeout(state.tR);
        renderTabs();
        pollRoom();
    }

    function tutupTab(id) {
        state.tabs = state.tabs.filter((t) => t !== id);
        if (state.active === id) {
            const next = state.tabs[state.tabs.length - 1];
            if (next) return pilih(next);
            state.active = null; clearTimeout(state.tR);
            $('#ac-room').hide(); $('#ac-empty').show();
        }
        renderTabs();
    }

    function renderTabs() {
        const $t = $('#ac-tabs').empty().toggle(state.tabs.length > 0);
        const belum = Object.fromEntries([...(state.data?.waiting || []), ...(state.data?.mine || [])].map((s) => [s.id, s.belum_dibaca]));
        state.tabs.forEach((id) => {
            const aktif = id === state.active;
            $t.append($('<div role="tab" class="flex shrink-0 items-center gap-1.5 rounded-t-lg border border-b-0 px-3 py-1.5 text-sm">')
                .addClass(aktif ? 'border-slate-200 bg-white font-semibold text-primary' : 'border-transparent text-slate-500 hover:bg-white/70')
                .append(
                    $('<button type="button" class="max-w-32 truncate">').text(state.names[id] || `#${id}`).on('click', () => pilih(id)),
                    !aktif && belum[id] ? $('<span class="rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">').text(belum[id]) : '',
                    $('<button type="button" class="text-slate-400 hover:text-red-600" aria-label="Tutup tab">').html('<i class="bi bi-x"></i>').on('click', () => tutupTab(id)),
                ));
        });
    }

    function pollRoom() {
        if (!state.active || state.rBusy) return;
        state.rBusy = true;
        const id = state.active;

        $.getJSON(`${base}/${id}/poll`, { last_id: state.lastId })
            .done((res) => {
                if (id !== state.active) return;
                const s = res.session;
                $('#ac-nama').text(s.nama);
                $('#ac-info').text([s.topik, s.email, s.whatsapp].filter(Boolean).join(' · '));
                $('#ac-email-note').toggle(!!s.balas_email);

                const selesai = s.status === 'selesai';
                const menunggu = s.status === 'menunggu';
                const milikLain = !!s.agent_id && s.agent_id !== state.me?.id;
                $('#ac-take').toggle(menunggu);
                $('#ac-form, #ac-quick').toggle(!selesai && !milikLain);
                $('#ac-close').toggle(!selesai && !milikLain);
                $('#ac-transfer').toggle(!selesai && !milikLain);
                if (milikLain && !selesai) $('#ac-info').text(`Dilayani oleh ${s.agent} · ` + $('#ac-info').text());

                res.messages.forEach(render);
                if (res.messages.length) $msgs[0].scrollTop = $msgs[0].scrollHeight;
                $('#ac-typing').toggle(!!res.visitor_typing);
            })
            .fail((xhr) => { if (xhr.status === 404) tutupTab(id); })
            .always(() => { state.rBusy = false; clearTimeout(state.tR); state.tR = setTimeout(pollRoom, jeda(3000)); });
    }

    function ukuranTeks(b) {
        return b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;
    }

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

    function render(m) {
        if (state.rendered.has(m.id)) return;
        state.rendered.add(m.id);
        state.lastId = Math.max(state.lastId, m.id);

        let $el;
        if (m.sender === 'system') {
            $el = $('<div class="mx-auto max-w-[90%] rounded-full bg-slate-200/70 px-3 py-1.5 text-center text-xs text-slate-600">').text(m.body);
        } else {
            const saya = m.sender === 'agent';
            $el = $('<div class="flex max-w-[80%] flex-col">').addClass(saya ? 'ml-auto items-end' : 'items-start').append(
                $('<div class="mb-0.5 text-[0.7rem] font-semibold text-slate-500">').text(saya ? (m.name || 'Petugas') : (m.sender === 'bot' ? 'Asisten' : 'Pengunjung')),
                isiGelembung($('<div class="rounded-2xl px-3.5 py-2 text-sm shadow-sm">').addClass(saya ? 'bg-primary text-white rounded-br-md' : 'border border-slate-200 bg-white rounded-bl-md'), m),
                $('<div class="mt-0.5 text-[0.7rem] text-slate-400">').text(m.time),
            );
        }
        $msgs.append($el);
    }

    // ------------------------------------------------------------ aksi
    $('#ac-form').on('submit', function (e) {
        e.preventDefault();
        const $in = $('#ac-input');
        const body = $in.val().trim();
        if (!body || !state.active) return;
        const $btn = $(this).find('button').prop('disabled', true);

        $.post(`${base}/${state.active}/reply`, { body })
            .done((res) => { $in.val(''); render(res.message); $msgs[0].scrollTop = $msgs[0].scrollHeight; muatQueue(); })
            .fail((xhr) => window.toast?.(xhr.responseJSON?.message || 'Balasan gagal dikirim.', 'error'))
            .always(() => $btn.prop('disabled', false));
    });

    $('#ac-input').on('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#ac-form').trigger('submit'); } });

    let typingAt = 0;
    $('#ac-input').on('input', () => {
        if (state.active && Date.now() - typingAt > 2500) { typingAt = Date.now(); $.post(`${base}/${state.active}/typing`); }
    });

    // kirim gambar/PDF
    $('#ac-attach').on('click', () => $('#ac-file').trigger('click'));
    $('#ac-file').on('change', function () {
        const f = this.files[0];
        this.value = '';
        if (!f || !state.active) return;
        if (f.size > 5 * 1024 * 1024) return window.toast?.('Ukuran berkas maksimal 5 MB.', 'error');
        if (!/\.(jpe?g|png|pdf)$/i.test(f.name)) return window.toast?.('Hanya berkas JPG, PNG, atau PDF.', 'error');

        const fd = new FormData();
        fd.append('file', f);
        const $prog = $('#ac-progress').show();
        $.ajax({
            url: `${base}/${state.active}/upload`, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            xhr() {
                const xhr = $.ajaxSettings.xhr();
                if (xhr.upload) xhr.upload.addEventListener('progress', (e) => e.lengthComputable && $prog.find('div').css('width', `${Math.round((e.loaded / e.total) * 100)}%`));
                return xhr;
            },
        })
            .done((res) => { render(res.message); $msgs[0].scrollTop = $msgs[0].scrollHeight; muatQueue(); })
            .fail((xhr) => window.toast?.(xhr.responseJSON?.errors?.file?.[0] || xhr.responseJSON?.message || 'Berkas gagal dikirim.', 'error'))
            .always(() => $prog.hide().find('div').css('width', '0%'));
    });

    // izin notifikasi browser: diminta lewat klik tombol (bukan saat halaman dibuka)
    const $notif = $('#ac-notif');
    const segarkanNotif = () => $notif.toggle(izin() === 'default');
    $notif.on('click', () => minta().then(segarkanNotif));
    segarkanNotif();

    $('#ac-take').on('click', () => state.active && ambil(state.active, state.names[state.active]));

    // balasan cepat (template jawaban)
    ($root.data('quick') || []).forEach((q) => {
        $('<button type="button" class="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-primary hover:text-primary">')
            .text(q.judul).attr('title', q.isi)
            .on('click', () => { const $in = $('#ac-input'); $in.val(($in.val() ? $in.val() + '\n' : '') + q.isi).trigger('focus'); })
            .appendTo('#ac-quick');
    });

    $('#ac-transfer').on('change', function () {
        const tujuan = $(this).val();
        if (!tujuan || !state.active) return;
        const nama = $(this).find('option:selected').text();
        const kirim = () => $.post(`${base}/${state.active}/transfer`, { agent_id: tujuan })
            .done(() => { window.toast?.('Chat dialihkan.'); tutupTab(state.active); muatQueue(); })
            .fail((xhr) => window.toast?.(xhr.responseJSON?.message || 'Gagal mengalihkan.', 'error'));
        window.Swal
            ? window.Swal.fire({ title: 'Alihkan chat?', text: `Chat ini akan dialihkan ke ${nama}.`, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, alihkan', cancelButtonText: 'Batal', reverseButtons: true }).then((r) => (r.isConfirmed ? kirim() : $(this).val('')))
            : (window.confirm('Alihkan chat?') ? kirim() : $(this).val(''));
    });

    $('#ac-close').on('click', () => {
        if (!state.active) return;
        const id = state.active;
        const tutup = () => $.post(`${base}/${id}/close`).done(() => { pollRoom(); muatQueue(); });
        window.Swal
            ? window.Swal.fire({ title: 'Tutup sesi?', text: 'Pengunjung tidak bisa membalas lagi.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, tutup', cancelButtonText: 'Batal', reverseButtons: true }).then((r) => r.isConfirmed && tutup())
            : window.confirm('Tutup sesi ini?') && tutup();
    });

    $(document).on('visibilitychange', () => { if (!document.hidden) { muatQueue(); pollRoom(); } });

    muatQueue();
}
