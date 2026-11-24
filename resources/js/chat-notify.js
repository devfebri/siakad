// Pembantu notifikasi Konsultasi Online: Web Notification API, bunyi pendek, dan judul tab berkedip.
// Dipakai chat-widget.js (pengunjung) dan admin-chat.js (petugas). Izin notifikasi hanya diminta lewat minta()
// setelah ada aksi pengguna (mulai chat / klik tombol), tidak saat halaman dibuka.

const judulAsli = document.title;
let kedipTimer = null;
let audio = null;

export function izin() {
    return 'Notification' in window ? Notification.permission : 'unsupported';
}

/** Minta izin notifikasi browser (abaikan bila sudah diputuskan). */
export function minta() {
    if (izin() !== 'default') return Promise.resolve(izin());
    try {
        const hasil = Notification.requestPermission();
        return Promise.resolve(hasil).catch(() => 'denied');
    } catch {
        return Promise.resolve('denied');
    }
}

/** Bunyi pendek (WebAudio, tanpa berkas suara). */
export function beep() {
    try {
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === 'suspended') audio.resume();
        const osc = audio.createOscillator();
        const gain = audio.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, audio.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.18, audio.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + 0.35);
        osc.connect(gain).connect(audio.destination);
        osc.start();
        osc.stop(audio.currentTime + 0.36);
    } catch { /* audio belum diizinkan browser */ }
}

/**
 * Notifikasi browser + bunyi. Hanya dipanggil saat pengguna tidak sedang melihat (tab tersembunyi / panel tertutup).
 * @param {{icon?: string, tag?: string, onClick?: () => void}} opsi
 */
export function beritahu(judul, isi, opsi = {}) {
    beep();
    if (izin() !== 'granted') return;
    try {
        const n = new Notification(judul, { body: String(isi).slice(0, 140), icon: opsi.icon, tag: opsi.tag || 'chat' });
        n.onclick = () => { window.focus(); opsi.onClick?.(); n.close(); };
        setTimeout(() => n.close(), 8000);
    } catch { /* beberapa browser seluler melarang konstruktor Notification */ }
}

/** Judul tab berkedip "(n) teks" sampai pengguna kembali ke tab. */
export function kedip(teks) {
    if (!document.hidden || kedipTimer) return;
    let nyala = false;
    kedipTimer = setInterval(() => {
        nyala = !nyala;
        document.title = nyala ? teks : judulAsli;
    }, 900);
}

export function berhentiKedip() {
    if (!kedipTimer) return;
    clearInterval(kedipTimer);
    kedipTimer = null;
    document.title = judulAsli;
}

document.addEventListener('visibilitychange', () => { if (!document.hidden) berhentiKedip(); });
window.addEventListener('focus', berhentiKedip);
