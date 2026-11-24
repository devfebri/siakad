@extends('layouts.admin')

@section('title', 'Detail Percakapan')

@section('content')
    <x-admin.page-header title="Percakapan: {{ $sesi->nama }}" subtitle="{{ $sesi->topik }} · {{ $sesi->created_at?->translatedFormat('d F Y, H:i') }} WIB" :back="route('admin/chat.riwayat')">
        <a href="{{ route('admin/chat.pdf', $sesi) }}" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5 text-sm">
            <dl class="space-y-2">
                <div><dt class="text-xs text-slate-500">Email</dt><dd>{{ $sesi->email }}</dd></div>
                <div><dt class="text-xs text-slate-500">WhatsApp</dt><dd>{{ $sesi->whatsapp ?? '-' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Petugas</dt><dd>{{ $sesi->agent?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Status</dt><dd class="capitalize">{{ $sesi->status }}</dd></div>
                <div><dt class="text-xs text-slate-500">Penilaian</dt><dd class="text-accent-dark">{{ $sesi->rating ? str_repeat('★', $sesi->rating).str_repeat('☆', 5 - $sesi->rating).' ('.$sesi->rating.'/5)' : 'Belum dinilai' }}</dd></div>
                @if ($sesi->rating_komentar)<div><dt class="text-xs text-slate-500">Komentar</dt><dd class="whitespace-pre-wrap">{{ $sesi->rating_komentar }}</dd></div>@endif
            </dl>
        </div>

        <div class="card space-y-3 bg-surface p-5 lg:col-span-2">
            @foreach ($pesan as $m)
                @if ($m->sender === 'system')
                    <div class="mx-auto max-w-[90%] rounded-full bg-slate-200/70 px-3 py-1.5 text-center text-xs text-slate-600">{{ $m->body }}</div>
                @else
                    @php $petugas = $m->sender !== 'visitor'; @endphp
                    <div @class(['flex max-w-[85%] flex-col', 'ml-auto items-end' => $petugas, 'items-start' => ! $petugas])>
                        <div class="mb-0.5 text-xs font-semibold text-slate-500">{{ $petugas ? ($m->sender === 'bot' ? 'Asisten' : ($m->user?->name ?? 'Petugas')) : $sesi->nama }}</div>
                        <div @class(['rounded-2xl px-3.5 py-2 text-sm shadow-sm', 'rounded-br-md bg-primary text-white' => $petugas, 'rounded-bl-md border border-slate-200 bg-white' => ! $petugas])>
                            @if ($m->body !== '')<div class="break-words whitespace-pre-wrap">{{ $m->body }}</div>@endif
                            @foreach ($m->attachments as $a)
                                <a href="{{ route('admin/chat.file', $a->id) }}" target="_blank" rel="noopener" class="mt-1 flex items-center gap-2 text-xs font-semibold underline"><i class="bi bi-paperclip"></i>{{ $a->nama_asli }}</a>
                            @endforeach
                        </div>
                        <div class="mt-0.5 text-xs text-slate-400">{{ $m->created_at?->format('d M H:i') }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
