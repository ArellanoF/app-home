<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1e8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="application-name" content="Vestapp">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
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
