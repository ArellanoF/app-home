<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tareas pendientes</title>
</head>
<body style="margin:0;background:#f4f3ee;color:#26332c;font-family:Arial,sans-serif">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px">
        <div style="background:#ffffff;border:1px solid #dfe4df;border-radius:8px;overflow:hidden">
            <div style="background:#ffffff;padding:12px 28px;text-align:center">
                <img src="{{ $message->embed(public_path('images/logo.png')) }}"
                    alt="Vestapp - Gestion del hogar" width="170"
                    style="display:block;width:170px;max-width:100%;height:auto;margin:0 auto;border:0">
            </div>
            <div style="background:#526c5c;color:#ffffff;padding:24px 28px">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase">{{ $recipient->house->name }}</div>
                <h1 style="margin:8px 0 0;font-size:24px">Tareas de esta semana</h1>
            </div>
            <div style="padding:26px 28px">
                <p style="margin:0 0 22px;line-height:1.5">Hola, {{ $recipient->name }}. Tienes {{ $tasks->count() }} {{ $tasks->count() === 1 ? 'tarea atrasada' : 'tareas atrasadas' }}.</p>

                @if ($overdueTasks->isNotEmpty())
                    <h2 style="margin:22px 0 10px;color:#a84f3c;font-size:16px">Atrasadas</h2>
                    @foreach ($overdueTasks as $task)
                        @include('mail.partials.task', ['task' => $task, 'accent' => '#a84f3c'])
                    @endforeach
                @endif

                @if ($thisWeekTasks->isNotEmpty())
                    <h2 style="margin:22px 0 10px;font-size:16px">Para esta semana</h2>
                    @foreach ($thisWeekTasks as $task)
                        @include('mail.partials.task', ['task' => $task, 'accent' => '#526c5c'])
                    @endforeach
                @endif

                @if ($laterTasks->isNotEmpty())
                    <h2 style="margin:22px 0 10px;font-size:16px">Otras pendientes</h2>
                    @foreach ($laterTasks as $task)
                        @include('mail.partials.task', ['task' => $task, 'accent' => '#9a8654'])
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</body>
</html>
