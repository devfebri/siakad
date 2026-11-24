@extends('layouts.admin')

@section('title', 'Riwayat Konsultasi')

@section('content')
    <x-admin.page-header title="Konsultasi Online" subtitle="Riwayat semua percakapan.">
        <a href="{{ route('admin/chat.riwayat.export', $filter) }}" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </x-admin.page-header>

    <x-admin.subnav :items="[['Percakapan Langsung', route('admin/chat'), ['admin/chat']], ['Riwayat', route('admin/chat.riwayat'), ['admin/chat/riwayat*']], ['Statistik', route('admin/chat.statistik'), ['admin/chat/statistik']], ['Pengaturan', route('admin/chat.pengaturan'), ['admin/chat/pengaturan']]]" />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6" aria-label="Filter riwayat">
        <div><label class="label" for="f-dari">Dari</label><input id="f-dari" type="date" name="dari" value="{{ $filter['dari'] ?? '' }}" class="input"></div>
        <div><label class="label" for="f-sampai">Sampai</label><input id="f-sampai" type="date" name="sampai" value="{{ $filter['sampai'] ?? '' }}" class="input"></div>
        <div>
            <label class="label" for="f-topik">Topik</label>
            <select id="f-topik" name="topik" class="select">
                <option value="">Semua</option>
                @foreach ($topik as $t)<option value="{{ $t }}" @selected(($filter['topik'] ?? '') === $t)>{{ $t }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="f-agent">Petugas</label>
            <select id="f-agent" name="agent_id" class="select">
                <option value="">Semua</option>
                @foreach ($petugas as $p)<option value="{{ $p->id }}" @selected((string) ($filter['agent_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="f-status">Status</label>
            <select id="f-status" name="status" class="select">
                <option value="">Semua</option>
                @foreach (['menunggu', 'dilayani', 'selesai'] as $st)<option value="{{ $st }}" @selected(($filter['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="f-q">Cari nama/email</label>
            <div class="flex gap-2">
                <input id="f-q" name="q" value="{{ $filter['q'] ?? '' }}" class="input" maxlength="100">
                <button class="btn btn-primary" aria-label="Terapkan filter"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </form>

    <div class="card overflow-x-auto p-2">
        <table class="table-clean">
            <thead><tr><th>Tanggal</th><th>Pengunjung</th><th>Topik</th><th>Petugas</th><th>Status</th><th>Pesan</th><th>Rating</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($daftar as $s)
                    <tr>
                        <td class="whitespace-nowrap text-slate-600">{{ $s->created_at?->translatedFormat('d M Y H:i') }}</td>
                        <td><span class="font-semibold">{{ $s->nama }}</span><br><span class="text-xs text-slate-500">{{ $s->email }}</span></td>
                        <td class="text-slate-600">{{ $s->topik }}</td>
                        <td class="text-slate-600">{{ $s->agent?->name ?? '-' }}</td>
                        <td><span @class(['badge', 'badge-success' => $s->status === 'selesai', 'badge-warning' => $s->status === 'menunggu', 'badge-primary' => $s->status === 'dilayani'])>{{ $s->status }}</span></td>
                        <td>{{ $s->messages_count }}</td>
                        <td class="whitespace-nowrap text-accent-dark">{{ $s->rating ? str_repeat('★', $s->rating).str_repeat('☆', 5 - $s->rating) : '-' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin/chat.riwayat.show', $s) }}" class="btn btn-soft btn-sm"><i class="bi bi-eye"></i> Lihat</a>
                            <a href="{{ route('admin/chat.pdf', $s) }}" class="btn btn-outline btn-sm" aria-label="Unduh PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">Belum ada percakapan yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
