@extends('layouts.guest')

@section('title', 'Login Platform')

@section('content')
    <div class="grid min-h-screen place-items-center bg-gradient-to-br from-primary to-primary-deep px-6 py-12">
        <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-lift">
            <div class="mb-6 flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-primary text-2xl text-accent"><i class="bi bi-buildings"></i></span>
                <div class="leading-tight">
                    <p class="font-display text-lg font-extrabold text-primary">Panel Platform</p>
                    <p class="text-xs text-slate-500">Khusus Super Admin</p>
                </div>
            </div>

            <form method="POST" action="{{ route('platform.login') }}" class="space-y-4" novalidate>
                @csrf
                <div>
                    <label for="username" class="label">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" maxlength="50" autofocus required
                           class="input @error('username') is-invalid @enderror">
                    @error('username')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required class="input @error('password') is-invalid @enderror">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="check"> Ingat saya</label>
                <button type="submit" class="btn btn-primary btn-lg btn-block"><i class="bi bi-box-arrow-in-right"></i> Masuk</button>
            </form>
        </div>
    </div>
@endsection
