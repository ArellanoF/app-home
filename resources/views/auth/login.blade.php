<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1e8">
    <meta name="color-scheme" content="light">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="application-name" content="Vestapp">
    <style>html{color-scheme:light;background:#f4f1e8}body{margin:0;background:#f4f1e8;color:#26312d}</style>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
    @foreach ([
        [320, 568, 2, 640, 1136], [375, 667, 2, 750, 1334], [414, 896, 2, 828, 1792],
        [360, 780, 3, 1080, 2340], [375, 812, 3, 1125, 2436], [390, 844, 3, 1170, 2532],
        [393, 852, 3, 1179, 2556], [402, 874, 3, 1206, 2622], [414, 896, 3, 1242, 2688],
        [428, 926, 3, 1284, 2778], [430, 932, 3, 1290, 2796], [440, 956, 3, 1320, 2868],
    ] as [$deviceWidth, $deviceHeight, $ratio, $imageWidth, $imageHeight])
        <link rel="apple-touch-startup-image"
            media="(device-width: {{ $deviceWidth }}px) and (device-height: {{ $deviceHeight }}px) and (-webkit-device-pixel-ratio: {{ $ratio }}) and (orientation: portrait)"
            href="{{ asset("images/launch/launch-{$imageWidth}x{$imageHeight}.png") }}">
    @endforeach
    <title>Entrar · Vestapp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-card">
        <img class="login-logo" src="{{ asset('images/logo.png') }}" alt="Vestapp - Gestion del hogar">
        <span class="eyebrow">TU HOGAR</span>
        <h1>Bienvenido a Vestapp</h1>
        <p>Entra para organizar las tareas, la compra y el menú de casa.</p>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label>Correo electrónico<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required></label>
            <label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
            @error('email')<small class="login-error">{{ $message }}</small>@enderror
            <button class="primary-button" type="submit">Entrar</button>
        </form>
        <small class="login-session-note">La sesión permanecerá iniciada en este dispositivo.</small>
    </main>
</body>
</html>
