@extends('layouts.guest')

@section('title', 'Simulasi Pembayaran')

@section('content')
    <div class="mx-auto max-w-md p-6 text-center">
        <p class="badge badge-warning">Simulasi: bukan uang sungguhan</p>
        <h1 class="mt-3 font-display text-2xl font-bold">Bayar Rp {{ number_format($bayar->jumlah, 0, ',', '.') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $bayar->invoice->nomor }} · {{ $bayar->order_id }}</p>
        <form method="POST" action="{{ $aksi ?? route('pembayaran.simulasi.kirim', $bayar->order_id) }}" class="mt-6 grid gap-3">@csrf
            <button name="status" value="berhasil" class="btn btn-success btn-lg">Pembayaran berhasil</button>
            <button name="status" value="gagal" class="btn btn-outline">Pembayaran gagal</button>
            <button name="status" value="kedaluwarsa" class="btn btn-ghost">Kedaluwarsa</button>
        </form>
    </div>
@endsection
