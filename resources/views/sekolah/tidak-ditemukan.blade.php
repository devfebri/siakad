<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; font-family: Inter, system-ui, sans-serif; }
        main { max-width: 28rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; color: #1e3a8a; }
        p { margin: 0; color: #475569; line-height: 1.6; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $judul }}</h1>
        <p>{{ $pesan }}</p>
    </main>
</body>
</html>
