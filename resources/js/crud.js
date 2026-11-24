// CRUD berbasis jQuery AJAX + DataTables server-side (tampilan Tailwind, modal <dialog> bawaan browser).
//
// Markup lengkap lihat komponen <x-admin.crud> (resources/views/components/admin/crud.blade.php):
//   <div data-crud data-name="Mata Pelajaran" data-url=".../data"
//        data-store=".../mapel" data-update=".../mapel/:id" data-destroy=".../mapel/:id">
//     <button data-crud-add>...</button>
//     <table><thead><tr>
//        <th data-col="no">, <th data-col="nama_kolom">, <th data-col="actions" class="no-sort">
//     </tr></thead></table>
//     <dialog class="modal"><form>... <input name="..."> ...</form></dialog>
//   </div>
// Atribut data-store / data-update / data-destroy yang tidak ada = aksi tersebut tidak ditampilkan.
//   (opsional) data-show=".../kelas/open/:id" menambah tombol Detail yang membuka halaman lain.
//   Alur: klik Tambah/Ubah -> dialog terbuka -> submit -> $.ajax ke server -> JSON -> toast + tabel dimuat ulang.
import $ from 'jquery';
import DataTable from 'datatables.net';
import Swal from './swal';

window.$ = window.jQuery = $;
DataTable.use($); // pasang plugin jQuery ($.fn.DataTable)

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const BRAND = getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#1e3a8a';

export const dtLanguage = {
    search: '',
    searchPlaceholder: 'Cari data...',
    lengthMenu: 'Tampilkan _MENU_',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
    infoEmpty: 'Belum ada data',
    infoFiltered: '(disaring dari _MAX_ data)',
    zeroRecords: 'Data tidak ditemukan',
    emptyTable: 'Belum ada data',
    processing: 'Memuat...',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' },
};

export function toast(message, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true,
    });
}

window.toast = toast; // dipakai komponen Alpine di halaman lain

function errorMessage(xhr) {
    if (xhr.status === 419) return 'Sesi berakhir. Muat ulang halaman lalu coba lagi.';
    if (xhr.status === 403) return 'Anda tidak memiliki izin untuk aksi ini.';
    return xhr.responseJSON?.message || 'Terjadi kesalahan pada server. Coba lagi.';
}

function request(method, url, data = '') {
    const isForm = data instanceof FormData;
    if (isForm && method !== 'POST') data.append('_method', method);

    return $.ajax({
        url,
        method: 'POST',
        data: isForm ? data : data + (method === 'POST' ? '' : `${data ? '&' : ''}_method=${method}`),
        processData: !isForm,
        contentType: isForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrf },
    });
}

// Render khusus kolom: <th data-col="url" data-cell="image|file|link|avatar|badge">.
// Nilai dibangun lewat jQuery agar aman dari XSS. (Jangan pakai data-render: dibaca DataTables sendiri.)
const renderers = {
    avatar: (url) => $('<div>').append($('<img>', { src: url, alt: '', class: 'avatar-img', loading: 'lazy' })).html(),
    image: (url) => (url ? $('<div>').append($('<img>', { src: url, alt: '', class: 'thumb', loading: 'lazy' })).html() : ''),
    file: (url) => (url ? $('<div>').append($('<a>', { href: url, class: 'btn btn-soft btn-sm' }).html('<i class="bi bi-download"></i>Unduh')).html() : '<span class="text-slate-400">-</span>'),
    link: (url) => (url ? $('<div>').append($('<a>', { href: url, target: '_blank', rel: 'noopener noreferrer', class: 'btn btn-soft btn-sm' }).html('<i class="bi bi-box-arrow-up-right"></i>Buka')).html() : '<span class="text-slate-400">-</span>'),
    // Nilai bentuk "teks|url": tautan yang dibuka di tab baru.
    tautan: (value) => {
        if (!value) return '<span class="text-slate-400">-</span>';
        const i = String(value).lastIndexOf('|');
        return $('<div>').append($('<a>', { href: String(value).slice(i + 1), target: '_blank', rel: 'noopener noreferrer', class: 'inline-flex items-center gap-1 font-semibold' }).text(String(value).slice(0, i)).append(' <i class="bi bi-box-arrow-up-right text-xs"></i>')).html();
    },
    // Nilai bentuk "Label|warna" (warna: primary, success, danger, warning, accent, muted)
    badge: (value) => {
        if (!value) return '';
        const [label, color] = String(value).split('|');
        return $('<div>').append($('<span>', { class: `badge badge-${color || 'primary'}` }).text(label)).html();
    },
};

function initCrud(root) {
    const cfg = root.dataset;
    const $table = $(root).find('table').first();
    const dialog = root.querySelector('dialog');
    const form = dialog?.querySelector('form');
    let editId = null;

    // Tombol di kolom "Aksi". Hanya yang URL-nya diberikan di atribut data-* yang muncul.
    const buttons = [
        cfg.show && `<a class="btn btn-info btn-sm" data-crud-show title="${cfg.showLabel || 'Detail'}"><i class="bi bi-${cfg.showIcon || 'eye'}"></i><span class="hidden xl:inline">${cfg.showLabel || 'Detail'}</span></a>`,
        cfg.update && (cfg.editLink
            ? '<a class="btn btn-warning btn-sm" data-crud-edit-link title="Ubah"><i class="bi bi-pencil-square"></i><span class="hidden xl:inline">Ubah</span></a>'
            : '<button type="button" class="btn btn-warning btn-sm" data-crud-edit title="Ubah"><i class="bi bi-pencil-square"></i><span class="hidden xl:inline">Ubah</span></button>'),
        cfg.destroy && '<button type="button" class="btn btn-danger btn-sm" data-crud-delete title="Hapus"><i class="bi bi-trash"></i><span class="hidden xl:inline">Hapus</span></button>',
    ].filter(Boolean).join('');

    const columns = [...$table.find('thead th')].map((th) => {
        const label = th.textContent.trim();
        const base = { createdCell: (td) => td.setAttribute('data-label', label) };

        if (th.dataset.col === 'no') {
            return { ...base, data: null, orderable: false, searchable: false,
                render: (d, t, r, meta) => table.page.info().start + meta.row + 1 };
        }
        if (th.dataset.col === 'actions') {
            return { ...base, data: null, orderable: false, searchable: false, className: 'text-right whitespace-nowrap',
                render: () => `<div class="inline-flex flex-wrap justify-end gap-1.5">${buttons}</div>` };
        }
        const custom = renderers[th.dataset.cell];
        return { ...base, data: th.dataset.col, name: th.dataset.col,
            orderable: !th.classList.contains('no-sort'),
            render: custom ? (value, type) => (type === 'display' ? custom(value) : value) : DataTable.render.text() };
    });

    const table = $table.DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: cfg.url,
            type: 'GET',
            // Elemen [data-crud-filter="nama"] (select di luar tabel) dikirim sebagai parameter ?nama=nilai
            data: (d) => { root.querySelectorAll('[data-crud-filter]').forEach((el) => { if (el.value) d[el.dataset.crudFilter] = el.value; }); },
            error: (xhr) => toast(errorMessage(xhr), 'error'),
        },
        columns,
        order: [],
        search: { search: new URLSearchParams(location.search).get('cari') || '' }, // ?cari=... dari pencarian topbar
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: dtLanguage,
        layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
    });
    $table.closest('.dt-container').parent().addClass('relative');
    root.querySelectorAll('[data-crud-filter]').forEach((el) => el.addEventListener('change', () => table.ajax.reload()));

    // ---------------------------------------------------------------- form tambah / ubah
    const clearErrors = () => {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.field-error[data-ajax]').forEach((el) => el.remove());
    };

    const open = (row) => {
        if (!form) return;
        clearErrors();
        form.reset();
        editId = row ? row.id : null;
        if (row) {
            [...form.elements].forEach((el) => {
                if (el.type === 'checkbox' && el.dataset.fill) {
                    // Kelompok checkbox: data-fill="nama_kunci_array" (mis. kelas_ids) berisi daftar nilai yang dicentang.
                    el.checked = (row[el.dataset.fill] || []).map(String).includes(el.value);
                } else if (el.type === 'checkbox' && el.name in row) {
                    el.checked = Boolean(Number(row[el.name])); // centang tunggal (mis. aktif)
                } else if (el.name && el.type !== 'file' && el.name in row) {
                    el.value = row[el.name] ?? '';
                }
            });
        }
        // Elemen khusus mode: data-create-only (hanya saat tambah), data-edit-only (hanya saat ubah),
        // data-lock-on-edit (input dikunci saat ubah, mis. email).
        form.querySelectorAll('[data-create-only]').forEach((el) => { el.hidden = !!row; });
        form.querySelectorAll('[data-edit-only]').forEach((el) => { el.hidden = !row; });
        form.querySelectorAll('[data-lock-on-edit]').forEach((el) => { el.readOnly = !!row; });
        dialog.querySelector('[data-modal-title]').textContent = `${row ? 'Ubah' : 'Tambah'} ${cfg.name}`;
        dialog.showModal();
        form.querySelector('input:not([type=hidden]):not([type=checkbox]):not([readonly]), select, textarea')?.focus();
    };

    root.querySelector('[data-crud-add]')?.addEventListener('click', () => open(null));
    dialog?.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', () => dialog.close()));
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }); // klik latar menutup

    // data-show / data-edit-link: ":id" diganti nilai kolom (data-show-key, bawaan "id"); data-show-blank = tab baru.
    $table.on('click', '[data-crud-show]', function () {
        const row = table.row($(this).closest('tr')).data();
        const url = cfg.show.replace(':id', encodeURIComponent(row[cfg.showKey || 'id']));
        if (cfg.showBlank !== undefined) window.open(url, '_blank', 'noopener');
        else window.location.href = url;
    });
    $table.on('click', '[data-crud-edit-link]', function () {
        window.location.href = cfg.editLink.replace(':id', table.row($(this).closest('tr')).data().id);
    });
    $table.on('click', '[data-crud-edit]', function () {
        open(table.row($(this).closest('tr')).data());
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearErrors();
        const submit = form.querySelector('[type=submit]');
        submit.disabled = true;

        const editing = editId !== null;
        request(editing ? 'PUT' : 'POST', editing ? cfg.update.replace(':id', editId) : cfg.store, form.querySelector('input[type=file]') ? new FormData(form) : $(form).serialize())
            .done((res) => {
                dialog.close();
                toast(res.message);
                document.dispatchEvent(new CustomEvent('crud:changed', { detail: { name: cfg.name } }));
                table.ajax.reload(null, !editing); // data baru tampil di halaman pertama; ubah tetap di halaman yang sama
            })
            .fail((xhr) => {
                if (xhr.status !== 422) return toast(errorMessage(xhr), 'error');
                showErrors(form, xhr.responseJSON.errors);
            })
            .always(() => { submit.disabled = false; });
    });

    // ---------------------------------------------------------------- hapus
    $table.on('click', '[data-crud-delete]', function () {
        const row = table.row($(this).closest('tr')).data();
        Swal.fire({
            title: 'Hapus data ini?',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;
            request('DELETE', cfg.destroy.replace(':id', row.id))
                .done((res) => {
                    toast(res.message);
                    document.dispatchEvent(new CustomEvent('crud:changed', { detail: { name: cfg.name } }));
                    const info = table.page.info();
                    // Baris terakhir di halaman ini terhapus: mundur satu halaman agar tidak kosong.
                    if (info.end - info.start === 1 && info.page > 0) table.page(info.page - 1);
                    table.draw(false);
                })
                .fail((xhr) => Swal.fire({ icon: 'error', title: 'Gagal menghapus', text: errorMessage(xhr), confirmButtonColor: BRAND }));
        });
    });
}

function showErrors(form, errors) {
    Object.entries(errors).forEach(([name, messages]) => {
        const input = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
        if (!input) return;
        input.classList.add('is-invalid');
        const holder = input.closest('[data-field]') || input.parentElement;
        const p = document.createElement('p');
        p.className = 'field-error';
        p.dataset.ajax = '';
        p.textContent = messages[0];
        holder.append(p);
    });
    form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ---------------------------------------------------------------- wilayah yang dimuat ulang via AJAX: <div id="x" data-ajax-region>
// Form <form data-ajax-form> di dalamnya yang membalas {redirect} tidak memindah halaman; isi wilayah diganti dengan
// versi terbaru dari server (elemen dengan id yang sama), lalu form barunya diaktifkan.
function refreshRegion(region, url) {
    region.setAttribute('aria-busy', 'true');
    region.classList.add('opacity-60', 'pointer-events-none');
    $.get(url)
        .done((html) => {
            const fresh = $('<div>').append($.parseHTML(html)).find(`#${region.id}`);
            if (!fresh.length) {
                window.location.href = url;
                return;
            }
            const y = window.scrollY;
            $(region).html(fresh.html());
            window.scrollTo(0, y);
            region.querySelectorAll('form[data-ajax-form]').forEach(initAjaxForm);
        })
        .fail(() => { window.location.href = url; })
        .always(() => {
            region.removeAttribute('aria-busy');
            region.classList.remove('opacity-60', 'pointer-events-none');
        });
}

// ---------------------------------------------------------------- form halaman penuh: <form data-ajax-form>
// Kirim lewat AJAX (FormData). Balasan JSON {message, redirect?, preview?}: redirect = pindah halaman setelah toast,
// preview = URL gambar baru untuk <img data-preview>. Error 422 tampil di bawah field.
function initAjaxForm(form) {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.field-error[data-ajax]').forEach((el) => el.remove());

        const submit = form.querySelector('[type=submit]');
        submit.disabled = true;
        const label = submit.innerHTML;
        submit.innerHTML = `${label} <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>`;

        request(form.dataset.method || 'POST', form.action, new FormData(form))
            .done((res) => {
                if (res.preview) {
                    let img = form.querySelector('[data-preview]');
                    if (!img) {
                        // Belum ada gambar sebelumnya: buat pratinjau di atas input file.
                        img = $('<img>', { alt: '', class: 'mb-2 block max-h-48 w-full rounded-xl object-cover' }).attr('data-preview', '')[0];
                        form.querySelector('input[type=file]')?.before(img);
                    }
                    img.src = res.preview;
                }
                const region = form.closest('[data-ajax-region]');
                if (region && res.redirect) {
                    // Tanpa muat ulang halaman: ambil ulang halaman, ganti isi wilayah saja, scroll tetap.
                    refreshRegion(region, res.redirect);
                    toast(res.message, res.gagal ? 'warning' : 'success');
                    return;
                }
                if (res.redirect) {
                    try { sessionStorage.setItem('flash-toast', res.message); } catch (e) { /* diabaikan */ }
                    window.location.href = res.redirect;
                    return;
                }
                if (form.hasAttribute('data-reset')) form.reset();
                toast(res.message);
            })
            .fail((xhr) => {
                if (xhr.status !== 422) return toast(errorMessage(xhr), 'error');
                showErrors(form, xhr.responseJSON.errors);
            })
            .always(() => {
                submit.disabled = false;
                submit.innerHTML = label;
            });
    });
}

// Toast yang dikirim dari halaman sebelumnya (setelah redirect)
try {
    const pending = sessionStorage.getItem('flash-toast');
    if (pending) {
        sessionStorage.removeItem('flash-toast');
        toast(pending);
    }
} catch (e) { /* penyimpanan diblokir */ }

DataTable.ext.errMode = 'none';
document.querySelectorAll('[data-crud]').forEach(initCrud);
document.querySelectorAll('form[data-ajax-form]').forEach(initAjaxForm);
