<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') · {{ config('school.short_name') }}</title>
    @if (config('school.logo'))
        <link rel="icon" href="{{ asset(config('school.logo')) }}">
    @endif
    @include('layouts._tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @yield('content')

    @if (Session::has('alert.config'))
        <script>window.__flash = {!! Session::pull('alert.config') !!};</script>
    @endif
</body>
</html>
