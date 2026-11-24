{{-- Layar izin OAuth. Klien E-Learning sekolah tepercaya sehingga layar ini dilewati (KlienOauth::skipsAuthorization); disediakan agar Passport selalu punya tampilan. --}}
@extends('layouts.guest')

@section('content')
    <div class="mx-auto max-w-md p-6 text-center">
        <h1 class="font-display text-xl font-bold">Masuk ke E-Learning</h1>
        <p class="mt-2 text-sm text-slate-600">Aplikasi {{ $client->name }} meminta masuk atas nama Anda.</p>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="mt-4">@csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="btn btn-primary">Izinkan</button>
        </form>
    </div>
@endsection
