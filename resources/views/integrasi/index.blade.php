@extends('layouts.admin')

@section('title', 'Integrasi E-Learning')

@section('content')
    <x-admin.page-header title="Integrasi E-Learning" subtitle="Hubungkan data sekolah ke aplikasi E-Learning. Cukup ikuti 3 langkah di bawah." />

    {{-- ============================================================ kunci baru (sekali saja) --}}
    @if ($kunciBaru)
        <div class="card mb-6 border-2 border-amber-300 bg-amber-50 p-6" x-data="{ salin(t, e) { navigator.clipboard.writeText(t); e.target.textContent = 'Tersalin ✓'; } }">
            <h2 class="font-display text-lg font-bold text-amber-900"><i class="bi bi-exclamation-triangle-fill"></i> Simpan kunci ini sekarang</h2>
            <p class="mt-1 text-sm text-amber-900">Kunci hanya tampil <strong>sekali</strong>. Salin dan berikan kepada pengelola aplikasi E-Learning. Kalau hilang, tekan "Buat Ulang Kunci" di bawah.</p>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach ([['ID Klien', $kunciBaru['client_id']], ['Kunci Rahasia', $kunciBaru['client_secret']], ['Kunci Tanda Tangan Kabar', $kunciBaru['webhook_secret']]] as [$label, $nilai])
                    <div>
                        <dt class="font-semibold">{{ $label }}</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2">
                            <code class="min-w-0 flex-1 rounded-lg bg-white px-3 py-2 break-all">{{ $nilai }}</code>
                            <button type="button" class="btn btn-outline btn-sm" @click="salin(@js($nilai), $event)">Salin</button>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    {{-- ============================================================ langkah 1 --}}
    <div class="card mb-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 1</p>
                <h2 class="font-display text-xl font-bold">Nyalakan sambungan</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Setelah dinyalakan, aplikasi E-Learning bisa mengambil data siswa, guru, kelas, dan jadwal sekolah ini. Data tidak bisa diubah dari E-Learning, hanya dibaca.</p>
            </div>
            <span @class(['badge text-sm', 'badge-success' => $klien?->aktif, 'badge-muted' => ! $klien?->aktif])>{{ $klien?->aktif ? 'Menyala' : 'Belum menyala' }}</span>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            @if ($klien?->aktif)
                <form method="POST" action="{{ route('admin/integrasi.aktifkan') }}" onsubmit="return confirm('Kunci lama akan berhenti berlaku. Lanjutkan membuat kunci baru?')">@csrf
                    <button class="btn btn-outline"><i class="bi bi-arrow-repeat"></i> Buat Ulang Kunci</button>
                </form>
                <form method="POST" action="{{ route('admin/integrasi.nonaktifkan') }}" onsubmit="return confirm('Matikan sambungan? E-Learning tidak bisa lagi mengambil data.')">@csrf
                    <button class="btn btn-danger"><i class="bi bi-power"></i> Matikan</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin/integrasi.aktifkan') }}">@csrf
                    <button class="btn btn-primary btn-lg"><i class="bi bi-plug-fill"></i> Nyalakan Sambungan</button>
                </form>
            @endif
        </div>

        @if ($klien?->aktif)
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">ID Klien</dt><dd class="font-mono break-all">{{ $klien->client_id }}</dd></div>
                <div><dt class="text-slate-500">Kunci Rahasia</dt><dd class="font-mono">{{ \App\Support\Integrasi::sembunyikan('xxxxxxxx') }} <span class="text-xs text-slate-400">(disembunyikan)</span></dd></div>
                <div><dt class="text-slate-500">Terakhir dipakai E-Learning</dt><dd>{{ $klien->terakhir_dipakai?->diffForHumans() ?? 'Belum pernah' }}</dd></div>
            </dl>
        @endif
    </div>

    @if ($klien?->aktif)
        {{-- ============================================================ langkah 2 --}}
        <div class="card mb-6 p-6">
            <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 2</p>
            <h2 class="font-display text-xl font-bold">Berikan alamat ini ke pengelola E-Learning</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-slate-500">Alamat pengambilan data</dt><dd><code class="rounded-lg bg-slate-100 px-3 py-1.5 break-all">{{ $apiUrl }}</code></dd></div>
                <div><dt class="text-slate-500">Alamat meminta izin (token)</dt><dd><code class="rounded-lg bg-slate-100 px-3 py-1.5 break-all">{{ $tokenUrl }}</code></dd></div>
            </dl>
            <p class="mt-4 text-sm text-slate-600">Selain alamat, mereka perlu <strong>ID Klien</strong> dan <strong>Kunci Rahasia</strong> dari kotak kuning di atas. Panduan teknis untuk mereka ada di <code>docs/api.md</code>.</p>
        </div>

        {{-- ============================================================ langkah 3 --}}
        <div class="card mb-6 p-6">
            <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 3 (boleh dilewati)</p>
            <h2 class="font-display text-xl font-bold">Kabari E-Learning saat data berubah</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Kalau diisi, setiap ada siswa, guru, kelas, atau jadwal yang berubah, E-Learning langsung diberi tahu. Alamat ini diberikan oleh pengelola E-Learning.</p>
            <form method="POST" action="{{ route('admin/integrasi.webhook') }}" class="mt-4 flex flex-wrap items-start gap-3">@csrf @method('PUT')
                <div class="min-w-0 flex-1">
                    <input type="url" name="webhook_url" value="{{ old('webhook_url', $klien->webhook_url) }}" placeholder="https://elearning.contoh.sch.id/webhook" class="input" maxlength="500" aria-label="Alamat penerima kabar">
                    @error('webhook_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-primary">Simpan</button>
            </form>
        </div>

        {{-- ============================================================ masuk ke E-Learning (SSO) --}}
        <div class="card mb-6 p-6">
            <p class="text-xs font-bold tracking-widest text-accent-dark uppercase">Langkah 4</p>
            <h2 class="font-display text-xl font-bold">Tombol "Buka E-Learning" untuk guru dan siswa</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Guru dan siswa yang sudah masuk ke aplikasi ini cukup menekan satu tombol, lalu langsung masuk ke E-Learning tanpa mengetik kata sandi lagi.</p>
            <form method="POST" action="{{ route('admin/integrasi.sso') }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
                <div>
                    <label class="mb-1 block text-sm font-semibold" for="elearning_url">Alamat aplikasi E-Learning</label>
                    <input id="elearning_url" type="url" name="elearning_url" value="{{ old('elearning_url', $klien->elearning_url) }}" placeholder="https://elearning.contoh.sch.id" class="input" maxlength="500">
                    @error('elearning_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold" for="redirect_url">Alamat kembali setelah masuk</label>
                    <input id="redirect_url" type="url" name="redirect_url" value="{{ old('redirect_url', $klien->redirect_url) }}" placeholder="https://elearning.contoh.sch.id/auth/callback" class="input" maxlength="500">
                    <p class="mt-1 text-xs text-slate-500">Diberikan oleh pengelola E-Learning. Harus sama persis.</p>
                    @error('redirect_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="sso_aktif" value="1" class="check" @checked(old('sso_aktif', $klien->sso_aktif))> Tampilkan tombol "Buka E-Learning" untuk guru dan siswa</label>
                @error('sso_aktif')<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror
                <div class="sm:col-span-2"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>

        {{-- ============================================================ uji sambungan --}}
        <div class="card mb-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold">Cek sambungan</h2>
                    <p class="text-sm text-slate-600">Pastikan kunci dan alamat sudah benar sebelum dipakai.</p>
                </div>
                <form method="POST" action="{{ route('admin/integrasi.uji') }}">@csrf<button class="btn btn-outline"><i class="bi bi-activity"></i> Cek Sekarang</button></form>
            </div>
            @if ($hasilUji)
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($hasilUji as [$label, $ok, $pesan])
                        <li class="flex items-start gap-2"><i class="bi {{ $ok === null ? 'bi-dash-circle text-slate-400' : ($ok ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500') }} mt-0.5"></i><span><strong>{{ $label }}:</strong> {{ $pesan }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ============================================================ riwayat --}}
        <div class="card mb-6">
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="font-display text-lg font-bold">Riwayat pengiriman kabar</h2></div>
            @if ($riwayat->isEmpty())
                <p class="p-6 text-sm text-slate-500">Belum ada pengiriman.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 uppercase"><tr><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Kabar</th><th class="px-4 py-2">Hasil</th><th class="px-4 py-2">Keterangan</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($riwayat as $r)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td class="px-4 py-2">{{ $r->event }}@if ($r->percobaan > 1) <span class="text-xs text-slate-400">(percobaan ke-{{ $r->percobaan }})</span>@endif</td>
                                    <td class="px-4 py-2"><span class="badge badge-{{ \App\WebhookLog::WARNA[$r->status] }}">{{ \App\WebhookLog::STATUS[$r->status] }}</span></td>
                                    <td class="px-4 py-2 text-slate-500">{{ $r->pesan ?: ($r->http_status ? 'Kode '.$r->http_status : '-') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
