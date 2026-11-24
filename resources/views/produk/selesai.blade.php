@extends('layouts.landing')

@section('title', $hasil ? 'Sekolah Aktif' : 'Tautan Tidak Berlaku')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        @if ($hasil)
            @php $s = $hasil['sekolah']; @endphp
            <span class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-100 text-4xl text-emerald-600"><i class="bi bi-check-lg"></i></span>
            <h1 class="mt-6 font-display text-3xl font-extrabold text-primary">Sekolah Anda sudah aktif!</h1>
            <p class="mt-3 text-lg text-slate-600"><strong>{{ $s->nama }}</strong> siap dipakai.</p>

            <dl class="card mx-auto mt-6 max-w-md space-y-3 p-5 text-left text-sm">
                <div><dt class="text-slate-500">Alamat situs</dt><dd class="font-semibold break-all">{{ $s->url('/') }}</dd></div>
                <div><dt class="text-slate-500">Username masuk</dt><dd class="font-semibold">{{ $hasil['pendaftaran']->data['admin']['username'] }}</dd></div>
                <div><dt class="text-slate-500">Kata sandi</dt><dd class="font-semibold">Yang tadi Anda isi saat mendaftar</dd></div>
            </dl>

            @if ($hasil['trial'])
                <p class="mt-4 rounded-xl bg-accent-soft p-4 text-sm"><i class="bi bi-gift-fill"></i> Masa percobaan gratis aktif sampai <strong>{{ today()->addDays(\App\Support\Pendaftaran::trialHari() - 1)->translatedFormat('d F Y') }}</strong>.</p>
            @elseif ($hasil['invoice'])
                <p class="mt-4 rounded-xl bg-accent-soft p-4 text-sm">Tagihan <strong>{{ $hasil['invoice']->nomor }}</strong> sebesar <strong>Rp {{ number_format($hasil['invoice']->jumlah, 0, ',', '.') }}</strong> bisa dilihat dan dibayar di menu <em>Langganan Saya</em> setelah masuk.</p>
            @endif

            <a href="{{ $s->url('/login') }}" class="btn btn-accent btn-lg mt-8"><i class="bi bi-box-arrow-in-right"></i> Masuk Sekarang</a>
        @else
            <span class="mx-auto grid size-20 place-items-center rounded-full bg-red-100 text-4xl text-red-600"><i class="bi bi-x-lg"></i></span>
            <h1 class="mt-6 font-display text-3xl font-extrabold text-primary">Tautan sudah tidak berlaku</h1>
            <p class="mt-3 text-lg text-slate-600">Tautan ini sudah dipakai, sudah lewat {{ \App\Support\Pendaftaran::BERLAKU_JAM }} jam, atau alamat sekolah sudah diambil pihak lain.</p>
            <a href="{{ route('daftar') }}" class="btn btn-primary btn-lg mt-8">Daftar Lagi</a>
        @endif
    </div>
@endsection
