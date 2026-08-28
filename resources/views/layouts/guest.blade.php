<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('images/logo-assesg.png') }}" type="image/png">

    <title>{{ $title ?? 'Entrar' }} — ASSESG</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased">
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-50 via-canvas to-secondary-50 px-4 py-12">
        {{ $slot }}
    </div>
</body>
</html>
