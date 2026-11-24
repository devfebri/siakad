import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/app.css';

import Alpine from 'alpinejs';
import Swal from './swal';
import './crud';
import jadwalGrid from './jadwal';

// Pustaka berat (SweetAlert, Chart.js, Quill, animate.css) baru diunduh saat halaman memerlukannya.
window.Alpine = Alpine;
window.Swal = Swal;
Alpine.data('jadwalGrid', jadwalGrid);
Alpine.start();

// ---------------------------------------------------------------- animate.css saat di-scroll: <div data-animate="fadeInUp" data-delay="200">
(async () => {
    const items = document.querySelectorAll('[data-animate]');
    if (!items.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    await import('animate.css');
    document.documentElement.classList.add('anim-ready');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            const el = entry.target;
            if (el.dataset.delay) {
                el.style.setProperty('--animate-delay', `${el.dataset.delay}ms`);
                el.classList.add('animate__delay-custom');
            }
            el.classList.add('animate__animated', `animate__${el.dataset.animate}`);
            observer.unobserve(el);
        });
    }, { threshold: 0.12 });
    items.forEach((el) => observer.observe(el));
})();

const BRAND = getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#1e3a8a';

// ---------------------------------------------------------------- konfirmasi: <form data-confirm="pesan">
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form.dataset?.confirm;
    if (!message || form.dataset.confirmed) {
        return;
    }
    event.preventDefault();
    Swal.fire({
        title: 'Yakin?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});

// ---------------------------------------------------------------- pesan flash dari server (window.__flash)
if (window.__flash) {
    // Format sweetalert v8: { config: {...opsi Swal...}, type: 'config' }
    const flash = window.__flash.config ?? window.__flash;
    Swal.fire({ confirmButtonColor: BRAND, ...(flash.toast ? { timer: 3500 } : {}), ...flash });
}

// ---------------------------------------------------------------- editor teks: <div data-editor="id_input_tersembunyi">
const holders = document.querySelectorAll('[data-editor]');
if (holders.length) {
    Promise.all([import('quill'), import('quill/dist/quill.snow.css')]).then(([{ default: Quill }]) => holders.forEach((holder) => mulaiEditor(Quill, holder)));
}

function mulaiEditor(Quill, holder) {
    const input = document.getElementById(holder.dataset.editor);
    const quill = new Quill(holder, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'link'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['clean'],
            ],
        },
    });
    quill.root.innerHTML = input.value || '';
    // Selalu sinkron ke input tersembunyi, jadi form biasa maupun form AJAX membaca isi terbaru.
    const sync = () => { input.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML; };
    quill.on('text-change', sync);
}

// ---------------------------------------------------------------- grafik: <canvas data-chart='{"type":"bar",...}'>
const canvases = document.querySelectorAll('canvas[data-chart]');
if (canvases.length) {
    import('chart.js/auto').then(({ default: Chart }) => {
        window.Chart = Chart;
        Chart.defaults.font.family = "'Inter Variable', system-ui, sans-serif";
        Chart.defaults.color = '#64748b';
        canvases.forEach((canvas) => new Chart(canvas, JSON.parse(canvas.dataset.chart)));
    });
}

// ---------------------------------------------------------------- hitung mundur: <div data-countdown="2026-12-31T23:59:59">
document.querySelectorAll('[data-countdown]').forEach((box) => {
    const target = new Date(box.dataset.countdown).getTime();
    const cells = {
        days: box.querySelector('[data-unit="days"]'),
        hours: box.querySelector('[data-unit="hours"]'),
        minutes: box.querySelector('[data-unit="minutes"]'),
        seconds: box.querySelector('[data-unit="seconds"]'),
    };
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const left = Math.max(0, target - Date.now());
        const s = Math.floor(left / 1000);
        cells.days && (cells.days.textContent = pad(Math.floor(s / 86400)));
        cells.hours && (cells.hours.textContent = pad(Math.floor((s % 86400) / 3600)));
        cells.minutes && (cells.minutes.textContent = pad(Math.floor((s % 3600) / 60)));
        cells.seconds && (cells.seconds.textContent = pad(s % 60));
        if (left === 0) {
            box.dispatchEvent(new CustomEvent('countdown:end'));
            return false;
        }
        return true;
    };
    if (tick()) {
        const timer = setInterval(() => { if (!tick()) clearInterval(timer); }, 1000);
    }
});
