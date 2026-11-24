// Grid jadwal mingguan (Alpine). Dipakai di halaman admin (per kelas), guru, dan siswa.
//   <div x-data="jadwalGrid('/admin/jadwal/grid', '#filter-kelas')">
export default function jadwalGrid(url, kelasSelector = null) {
    return {
        hari: [],
        hariIni: 0,
        memuat: true,
        error: false,

        init() {
            this.muat();
            document.addEventListener('crud:changed', () => this.muat());
            if (kelasSelector) {
                document.querySelector(kelasSelector)?.addEventListener('change', () => this.muat());
            }
        },

        get kosong() {
            return this.hari.every((h) => h.items.length === 0);
        },

        async muat() {
            this.memuat = true;
            this.error = false;
            try {
                const kelas = kelasSelector ? document.querySelector(kelasSelector)?.value : '';
                const res = await fetch(url + (kelas ? `?kelas_id=${encodeURIComponent(kelas)}` : ''), { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(res.status);
                const json = await res.json();
                this.hari = json.hari;
                this.hariIni = json.hari_ini;
            } catch (e) {
                this.error = true;
            } finally {
                this.memuat = false;
            }
        },
    };
}
