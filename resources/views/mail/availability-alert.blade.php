<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $recovered ? 'Servicio recuperado' : 'Servicio no disponible' }}</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5">
    <h1 style="font-size: 20px">
        {{ $recovered ? 'Vestapp vuelve a estar disponible' : 'Vestapp no está disponible' }}
    </h1>

    <p>
        @if ($recovered)
            La página ha respondido correctamente de nuevo.
        @else
            La página no ha respondido correctamente en varias comprobaciones consecutivas.
        @endif
    </p>

    <p><strong>URL:</strong> <a href="{{ $url }}">{{ $url }}</a></p>
    <p><strong>Comprobado:</strong> {{ $checkedAt }}</p>

    @if (! $recovered && $failureReason)
        <p><strong>Error:</strong> {{ $failureReason }}</p>
    @endif
</body>
</html>
