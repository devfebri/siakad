@extends('layouts.public')

@section('title', 'Riwayat Konsultasi')

@section('content')
    <x-public.page-title title="Riwayat Konsultasi" subtitle="Percakapan Anda dengan petugas {{ config('school.short_name') }}." crumb="Riwayat Konsultasi" />

    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <div class="card mb-6 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-display text-lg font-bold">{{ $sesi->topik }}</p>
                    <p class="text-sm text-slate-500">{{ $sesi->nama }} · {{ $sesi->created_at?->translatedFormat('d F Y, H:i') }} WIB</p>
                    <p class="mt-1 text-sm text-slate-500">Petugas: {{ $sesi->agent?->name ?? '-' }} ·
                        <span @class(['badge', 'badge-success' => $sesi->status === 'selesai', 'badge-warning' => $sesi->status !== 'selesai'])>{{ ucfirst($sesi->status) }}</span>
                    </p>
                </div>
                <a href="{{ route('chat.export', $sesi->riwayat_token) }}" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
            </div>
        </div>

        <div class="space-y-3 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-100">
            @foreach ($pesan as $m)
                @if ($m->sender === 'system')
                    <div class="mx-auto max-w-[90%] rounded-full bg-slate-100 px-3 py-1.5 text-center text-xs text-slate-600">{{ $m->body }}</div>
                @else
                    @php $saya = $m->sender === 'visitor'; @endphp
                    <div @class(['flex max-w-[85%] flex-col', 'ml-auto items-end' => $saya, 'items-start' => ! $saya])>
                        <div class="mb-0.5 text-xs font-semibold text-slate-500">{{ $saya ? $sesi->nama : ($m->sender === 'bot' ? 'Asisten' : ($m->user?->name ?? 'Petugas')) }}</div>
                        <div @class(['rounded-2xl px-3.5 py-2 text-sm', 'rounded-br-md bg-primary text-white' => $saya, 'rounded-bl-md border border-slate-200 bg-slate-50' => ! $saya])>
                            @if ($m->body !== '')<div class="break-words whitespace-pre-wrap">{{ $m->body }}</div>@endif
                            @foreach ($m->attachments as $a)
                                <a href="{{ $urlLampiran($a) }}" target="_blank" rel="noopener" class="mt-1 flex items-center gap-2 text-xs font-semibold underline"><i class="bi bi-paperclip"></i>{{ $a->nama_asli }}</a>
                            @endforeach
                        </div>
                        <div class="mt-0.5 text-xs text-slate-400">{{ $m->created_at?->format('d M H:i') }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Penilaian (jQuery + AJAX) --}}
        @if ($sesi->isClosed())
            <div class="card mt-6 p-5" id="rating-box" data-url="{{ route('chat.rate') }}" data-riwayat="{{ $sesi->riwayat_token }}" data-rating="{{ (int) $sesi->rating }}">
                <h2 class="font-display text-lg font-bold">Penilaian layanan</h2>
                <p id="rating-done" class="mt-2 text-sm text-slate-600" style="display:none">Terima kasih atas penilaian Anda: <span id="rating-stars" class="font-semibold text-accent-dark"></span></p>
                <div id="rating-form" class="mt-2">
                    <div class="flex gap-1 text-3xl" role="radiogroup" aria-label="Penilaian bintang">
                        @foreach (range(1, 5) as $n)
                            <button type="button" class="rating-star leading-none text-slate-300 transition hover:scale-110" data-n="{{ $n }}" aria-label="{{ $n }} bintang">★</button>
                        @endforeach
                    </div>
                    <textarea id="rating-komentar" maxlength="300" rows="2" class="textarea mt-3" placeholder="Komentar singkat (opsional)"></textarea>
                    <p id="rating-galat" class="field-error"></p>
                    <button type="button" id="rating-kirim" class="btn btn-primary mt-3">Kirim Penilaian</button>
                </div>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () { // app.js (module) sudah memasang $ saat DOMContentLoaded
        const $box = $('#rating-box');
        if (!$box.length) return;
        let nilai = Number($box.data('rating')) || 0;
        const warnai = (n) => $('.rating-star').each(function () { $(this).toggleClass('text-accent', Number($(this).data('n')) <= n).toggleClass('text-slate-300', Number($(this).data('n')) > n); });
        const selesai = (n) => { $('#rating-form').hide(); $('#rating-stars').text('★'.repeat(n) + '☆'.repeat(5 - n)); $('#rating-done').show(); };

        if (nilai) return selesai(nilai);
        $('.rating-star').on('mouseenter', function () { warnai(Number($(this).data('n'))); }).on('mouseleave', () => warnai(nilai)).on('click', function () { nilai = Number($(this).data('n')); warnai(nilai); });
        $('#rating-kirim').on('click', function () {
            if (!nilai) return $('#rating-galat').text('Pilih jumlah bintang.');
            $.ajax({ url: $box.data('url'), method: 'POST', dataType: 'json', headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
                data: { riwayat: $box.data('riwayat'), rating: nilai, komentar: $('#rating-komentar').val() } })
                .done(() => selesai(nilai))
                .fail((xhr) => $('#rating-galat').text(xhr.responseJSON?.errors?.rating?.[0] || xhr.responseJSON?.message || 'Gagal menyimpan.'));
        });
    });
</script>
@endpush
