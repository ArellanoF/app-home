<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f4ed">
    <title>{{ $house->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar" aria-label="Navegación principal">
            <a class="brand" href="#" aria-label="{{ $house->name }}, inicio">
                <img src="{{ asset('images/logo.png') }}" alt="Vestapp - Gestion del hogar">
            </a>
            <nav class="side-nav">
                <a class="active" href="#inicio"><svg viewBox="0 0 24 24">
                        <path d="M4 13h6V4H4zm0 7h6v-5H4zm10 0h6v-9h-6zm0-16v5h6V4z" />
                    </svg>Resumen</a>
                <a href="#tareas"><svg viewBox="0 0 24 24">
                        <path d="m4 12 2 2 4-4M4 6l2 2 4-4m3 3h7m-7 6h7m-16 5 2 2 4-4m3 3h7" />
                    </svg>Tareas <span class="nav-count">{{ $pendingTasksCount }}</span></a>
                <a href="#calendario"><svg viewBox="0 0 24 24">
                        <path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Zm-2 6h18M8 2v4m8-4v4" />
                    </svg>Calendario</a>
                <a href="#compra"><svg viewBox="0 0 24 24">
                        <path d="M3 4h2l2 12h10l3-8H6m3 12h.01M17 20h.01" />
                    </svg>Lista de compra <span class="nav-count">{{ $shoppingPendingCount }}</span></a>
                <a href="#menu"><svg viewBox="0 0 24 24">
                        <path d="M7 3v8m-3-8v5a3 3 0 0 0 6 0V3m-3 8v10m10-18v18m0-18c-3 2-4 6-2 9h2" />
                    </svg>Menú semanal</a>
                <a href="#familia"><svg viewBox="0 0 24 24">
                        <path
                            d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8m13 10v-2a4 4 0 0 0-3-3.9m-3-12a4 4 0 0 1 0 7.75" />
                    </svg>Familia</a>
            </nav>
            <div class="home-card">
                <span class="eyebrow">Este mes</span>
                <strong>{{ $monthlyProgressMessage }}</strong>
                <div class="mini-progress"><span style="width: {{ $monthlyProgress }}%"></span></div>
                <small>
                    @if ($monthlyTasksCount)
                        {{ $monthlyProgress }}% completado · {{ $monthlyCompletedCount }} de {{ $monthlyTasksCount }}
                        tareas
                    @else
                        Crea una tarea para empezar
                    @endif
                </small>
                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                    @csrf
                    <button type="submit">Cerrar sesión</button>
                </form>
            </div>
        </aside>

        <main id="inicio" class="main-content">
            <header class="topbar">
                <div>
                    <p class="date-label">{{ $currentDateLabel }}</p>
                    <h1>{{ $greeting }} <span>{{ $greetingIcon }}</span></h1>
                    <p class="subtitle">Todo lo importante de casa, en un solo vistazo.</p>
                </div>
                <div class="top-actions">
                    <div class="notifications">
                    <button class="icon-button" type="button" data-notifications-toggle aria-label="Notificaciones"
                        aria-expanded="false" aria-controls="notifications-panel"><svg viewBox="0 0 24 24">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                        </svg>@if ($attentionTasks->isNotEmpty())<i></i><span class="notification-count">{{ $attentionTasks->count() }}</span>@endif</button>
                    <div class="notifications-panel" id="notifications-panel" data-notifications-panel hidden>
                        <header><strong>Avisos</strong><small>{{ $attentionTasks->count() }} {{ $attentionTasks->count() === 1 ? 'pendiente' : 'pendientes' }}</small></header>
                        @forelse ($attentionTasks as $attentionTask)
                            <a href="#task-{{ $attentionTask->id }}" data-notification-link>
                                <span class="notification-status {{ $attentionTask->due_date->isBefore($currentTime->startOfDay()) ? 'overdue' : '' }}"></span>
                                <span><strong>{{ $attentionTask->title }}</strong><small>{{ $attentionTask->due_date->isToday() ? 'Vence hoy' : 'Atrasada desde ' . $attentionTask->due_date->locale('es')->isoFormat('D MMM') }} · {{ $attentionTask->assignee->name }}</small></span>
                            </a>
                        @empty
                            <div class="notifications-empty"><strong>Todo al día</strong><small>No hay tareas atrasadas ni para hoy.</small></div>
                        @endforelse
                    </div>
                    </div>
                    <button class="primary-button top-task-button" data-open-task aria-label="Nueva tarea"><svg viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path d="M12 5v14m-7-7h14" />
                        </svg>Nueva tarea</button>
                </div>
            </header>

            <section class="overview-grid" aria-label="Resumen del día">
                <article class="focus-card {{ $weather ? 'has-weather' : '' }}">
                    <div class="focus-top">
                    <div class="focus-copy">
                        <span class="eyebrow light">HOY EN CASA</span>
                        <p><strong>{{ $pendingTasksCount }} {{ $pendingTasksCount === 1 ? 'tarea' : 'tareas' }}</strong>
                            pendientes y <strong>{{ count($googleCalendar['events']) }} eventos</strong> próximos en tu
                            agenda.</p>
                    </div>
                    @if ($weather)
                        <div class="focus-weather" aria-label="Tiempo en {{ $weather['location'] }}">
                            <span class="weather-icon" aria-hidden="true">{{ $weather['icon'] }}</span>
                            <span><strong>{{ $weather['temperature'] }}°</strong><small>{{ $weather['description'] }}</small><em>{{ $weather['location'] }}</em></span>
                        </div>
                    @endif
                    </div>
                    @if ($weather)
                        <div class="weather-details"><span>Máx. {{ $weather['max'] }}° · Mín. {{ $weather['min'] }}°</span><span>💧 {{ $weather['rain'] }}%</span><span>💨 {{ $weather['wind'] }} km/h</span><span>Sensación {{ $weather['apparent'] }}°</span></div>
                        <div class="weather-forecast" aria-label="Previsión para los próximos días">
                            @foreach ($weather['forecast'] as $day)
                                <div class="forecast-day" title="{{ $day['description'] }}">
                                    <strong>{{ $day['day'] }}</strong><small>{{ $day['date'] }}</small><span>{{ $day['icon'] }}</span><b>{{ $day['max'] }}° <i>{{ $day['min'] }}°</i></b><em>💧 {{ $day['rain'] }}%</em>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="decor-leaf" aria-hidden="true">⌁</div>
                </article>
            </section>

            <section id="avisos" class="house-notes" aria-label="Avisos familiares">
                <div class="house-notes-heading"><div><span class="eyebrow">TABLÓN</span><h2>Avisos de casa</h2></div>
                    <button class="text-button" type="button" data-open-note>＋ Añadir aviso</button></div>
                <div class="house-notes-list">
                    @forelse ($familyNotes as $note)
                        <article><p>{{ $note->content }}</p><footer><span class="avatar avatar-{{ $note->author->color }}">{{ str($note->author->name)->substr(0, 2)->upper() }}</span><small>{{ $note->author->name }} · {{ $note->created_at->locale('es')->diffForHumans() }}</small>
                            <form method="POST" action="{{ route('family-notes.destroy', $note) }}" data-fetch-form data-refresh=".house-notes-list">@csrf @method('DELETE')<button aria-label="Eliminar aviso">×</button></form></footer></article>
                    @empty
                        <p class="notes-empty">No hay avisos fijados.</p>
                    @endforelse
                </div>
            </section>

            <div class="home-tools-grid">
                <section id="compra" class="panel shopping-panel">
                    <div class="section-heading">
                        <div><span class="eyebrow">DESPENSA</span>
                            <h2>Lista de la compra <small class="heading-count">{{ $shoppingPendingCount }}</small>
                            </h2>
                        </div>
                        <button class="text-button" data-open-shopping>＋ Añadir</button>
                    </div>
                    <div class="shopping-list">
                        @forelse ($shoppingItems as $item)
                            @php($categoryData = ['food' => ['🍎', 'Comida'], 'cleaning' => ['🧽', 'Productos de limpieza'], 'other' => ['🛒', 'Otros']][$item->category] ?? ['🛒', 'Otros'])
                            <div class="shopping-row {{ $item->purchased_at ? 'purchased' : '' }}"
                                data-shopping-id="{{ $item->id }}">
                                <label><input type="checkbox" {{ $item->purchased_at ? 'checked' : '' }}><span
                                        class="checkmark"></span></label>
                                <span class="shopping-icon">{{ $categoryData[0] }}</span>
                                <span class="shopping-copy"><strong>{{ $item->name }}</strong><small>{{ $item->quantity ?: 'Cantidad sin indicar' }}
                                        · {{ $categoryData[1] }}</small></span>
                                <form method="POST" action="{{ route('shopping-items.destroy', $item) }}" data-fetch-form data-refresh="#compra,.side-nav">@csrf
                                    @method('DELETE')<button type="submit" aria-label="Eliminar {{ $item->name }}">×</button>
                                </form>
                            </div>
                        @empty
                            <div class="tool-empty"><span>🛒</span><strong>La lista está vacía</strong><small>Añade algo
                                    que falte en casa.</small></div>
                        @endforelse
                    </div>
                </section>

                <section id="menu" class="panel menu-panel">
                    <div class="section-heading menu-section-heading">
                        <div><span class="eyebrow">PLANIFICACIÓN</span>
                            <h2>Menú semanal</h2>
                        </div>
                        <div class="menu-week-controls">
                            <a href="{{ route('home', ['menu_week' => $menuWeekStart->subWeek()->format('Y-m-d')]) . '#menu' }}"
                                aria-label="Semana anterior">‹</a>
                            <button type="button" data-week-today-url="{{ route('home', ['menu_week' => now(config('app.timezone'))->startOfWeek()->format('Y-m-d')]) . '#menu' }}">Hoy</button>
                            <span>{{ $menuWeekStart->locale('es')->isoFormat('D MMM') }} —
                                {{ $menuWeekStart->endOfWeek()->locale('es')->isoFormat('D MMM') }}</span>
                            <a href="{{ route('home', ['menu_week' => $menuWeekStart->addWeek()->format('Y-m-d')]) . '#menu' }}"
                                aria-label="Semana siguiente">›</a>
                        </div>
                    </div>
                    <div class="weekly-menu-calendar">
                        @foreach ($menuDays as $day)
                            <article class="menu-day {{ $day->isToday() ? 'today' : '' }}">
                                <header>
                                    <span>{{ $day->locale('es')->isoFormat('ddd') }}</span><strong>{{ $day->day }}</strong>
                                </header>
                                @foreach (['lunch' => ['Comida', '☀️'], 'dinner' => ['Cena', '☾']] as $type => [$typeLabel, $typeIcon])
                                    @php($slotMeals = $weeklyMeals->get($day->format('Y-m-d') . '-' . $type, collect()))
                                    <div class="meal-slot {{ $slotMeals->isNotEmpty() ? 'has-meal' : '' }}">
                                    <button class="meal-slot-add" type="button"
                                        data-open-meal data-date="{{ $day->format('Y-m-d') }}"
                                        data-type="{{ $type }}" aria-label="Añadir {{ strtolower($typeLabel) }}">
                                        <span>{{ $typeIcon }} {{ $typeLabel }}</span>
                                        <strong>＋</strong>
                                    </button>
                                    @forelse ($slotMeals as $meal)
                                        <div class="meal-entry">
                                            <button type="button" data-open-meal data-meal-id="{{ $meal->id }}"
                                                data-date="{{ $day->format('Y-m-d') }}" data-type="{{ $type }}"
                                                data-name="{{ $meal->name }}" data-notes="{{ $meal->notes }}"
                                                data-ingredients="{{ collect($meal->ingredients)->map(fn ($ingredient) => $ingredient['name'] . ($ingredient['quantity'] ? ' | ' . $ingredient['quantity'] : ''))->implode("\n") }}">
                                                <strong>{{ $meal->name }}</strong>
                                                @if ($meal->notes)<small>{{ $meal->notes }}</small>@endif
                                            </button>
                                            @if (count($meal->ingredients ?? []))
                                                <form method="POST" action="{{ route('meals.shopping-list', $meal) }}" data-fetch-form data-refresh="#compra,.side-nav">@csrf
                                                    <button type="submit" class="meal-to-shopping" title="Añadir ingredientes a la compra" aria-label="Añadir ingredientes de {{ $meal->name }} a la compra">＋</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('meals.destroy', $meal) }}" data-fetch-form data-refresh="#menu">
                                                @csrf @method('DELETE')
                                                <button type="submit" aria-label="Eliminar {{ $meal->name }}">×</button>
                                            </form>
                                        </div>
                                    @empty
                                        <small class="meal-slot-empty">Añadir plato</small>
                                    @endforelse
                                    </div>
                                @endforeach
                            </article>
                        @endforeach
                    </div>
                    <p class="menu-hint">Pulsa en cualquier comida o cena para planificarla.</p>
                </section>
            </div>
            <div class="content-grid">
                <section id="tareas" class="panel tasks-panel">
                    <div class="section-heading">
                        <div><span class="eyebrow">TAREAS</span>
                            <h2>Lista de tareas <small class="heading-count">{{ $tasksCount }}</small></h2>
                        </div>
                        @if ($tasks->count() > 5)
                            <button class="text-button" type="button" data-toggle-tasks aria-expanded="false">
                                Ver todas <span>↓</span>
                            </button>
                        @endif
                    </div>
                    <div class="task-list">
                        @forelse ($tasks as $task)
                            <div id="task-{{ $task->id }}" class="task-row {{ $task->completed_at ? 'done' : '' }} {{ $loop->index >= 5 ? 'task-overflow' : '' }}"
                                data-task-id="{{ $task->id }}">
                                <label class="task-check"><input type="checkbox" {{ $task->completed_at ? 'checked' : '' }}><span
                                    class="checkmark"></span></label>
                                @php($taskIcons = ['home' => ['🏠', 'home'], 'cleaning' => ['🧹', 'cleaning'], 'kitchen' => ['🍽️', 'kitchen'], 'plants' => ['🌿', 'plants']])
                                <span
                                    class="task-icon {{ $taskIcons[$task->icon][1] ?? 'home' }}">{{ $taskIcons[$task->icon][0] ?? '🏠' }}</span>
                                <span class="task-copy">
                                    <strong>{{ $task->title }}</strong>
                                    <small data-task-date>
                                        @if ($task->completed_at)
                                            Completada {{ $task->completed_at->locale('es')->diffForHumans() }}
                                        @elseif ($task->due_date)
                                            {{ $task->due_date->isToday() ? 'Para hoy' : $task->due_date->locale('es')->isoFormat('D [de] MMMM') }}
                                        @else
                                            Sin fecha límite
                                        @endif
                                    </small>
                                    @if ($task->description)
                                        <span class="task-description">{{ $task->description }}</span>
                                    @endif
                                    @if ($task->recurrence !== 'none')
                                        <span class="task-recurrence">↻ {{ ['daily' => 'Cada día', 'weekly' => 'Cada semana', 'monthly' => 'Cada mes'][$task->recurrence] }}</span>
                                    @endif
                                </span>
                                <span class="avatar avatar-{{ $task->assignee->color }}"
                                    data-task-avatar title="{{ $task->assignee->name }}">{{ str($task->assignee->name)->substr(0, 2)->upper() }}</span>
                                @if (! $task->completed_at)
                                    <div class="task-quick-actions">
                                        <select data-task-assignee aria-label="Reasignar {{ $task->title }}">
                                            @foreach ($activeMembers as $member)
                                                <option value="{{ $member->id }}" @selected($member->id === $task->user_id)>{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                        <select data-task-postpone aria-label="Posponer {{ $task->title }}">
                                            <option value="">Posponer…</option><option value="tomorrow">A mañana</option><option value="next_week">A próxima semana</option>
                                        </select>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="tasks-empty"><strong>Aún no hay tareas</strong><small>Crea la primera para Fran
                                    o Carmen.</small></div>
                        @endforelse
                    </div>
                    <button class="add-task" data-open-task><span>＋</span>Añadir otra tarea</button>
                </section>

                <section id="calendario" class="panel calendar-panel">
                    <div class="section-heading calendar-heading">
                        <div><span class="eyebrow">AGENDA</span>
                            <h2 data-calendar-title>
                                {{ $dateFilterActive ? 'Eventos del ' . $selectedDate->locale('es')->isoFormat('D [de] MMMM') : 'Próximos eventos' }}
                            </h2>
                        </div>
                        @if ($googleCalendarUrl)
                            <a class="google-button" href="{{ $googleCalendarUrl }}" target="_blank"
                                rel="noopener noreferrer"><span class="google-g">G</span><span class="google-label">Abrir
                                    en Google</span></a>
                        @endif
                    </div>
                    <div class="calendar-week-nav">
                        <a href="{{ route('home', ['week' => $weekStart->subWeek()->format('Y-m-d')]) . '#calendario' }}"
                            aria-label="Semana anterior">‹</a>
                        <button type="button" data-week-today-url="{{ route('home', ['week' => now(config('app.timezone'))->startOfWeek()->format('Y-m-d')]) . '#calendario' }}">Hoy</button>
                        <button type="button" data-calendar-upcoming class="{{ $dateFilterActive ? '' : 'active' }}"
                            title="Mostrar próximos eventos">Próximos</button>
                        <span>{{ $weekStart->locale('es')->isoFormat('D MMM') }} —
                            {{ $weekStart->addDays(6)->locale('es')->isoFormat('D MMM') }}</span>
                        <a href="{{ route('home', ['week' => $weekStart->addWeek()->format('Y-m-d')]) . '#calendario' }}"
                            aria-label="Semana siguiente">›</a>
                    </div>
                    <div class="calendar-strip"
                        aria-label="Semana del {{ $weekStart->locale('es')->isoFormat('D [de] MMMM') }}">
                        @foreach ($weekDays as $day)
                            @php($hasEvents = $eventsByDate->has($day->format('Y-m-d')))
                            <a href="{{ route('home', ['date' => $day->format('Y-m-d')]) . '#calendario' }}"
                                data-calendar-date="{{ $day->format('Y-m-d') }}"
                                data-calendar-label="{{ $day->locale('es')->isoFormat('D [de] MMMM') }}"
                                class="{{ $dateFilterActive && $day->isSameDay($selectedDate) ? 'selected' : '' }} {{ $day->isToday() ? 'today' : '' }}"
                                aria-label="{{ $day->locale('es')->isoFormat('dddd D [de] MMMM') }}"
                                @if ($dateFilterActive && $day->isSameDay($selectedDate)) aria-current="date" @endif>
                                <span>{{ ['L', 'M', 'X', 'J', 'V', 'S', 'D'][$day->dayOfWeekIso - 1] }}</span>
                                <strong>{{ $day->day }}</strong>
                                @if ($hasEvents)
                                    <i
                                        title="Hay {{ $eventsByDate->get($day->format('Y-m-d'))->count() }} eventos"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    <div class="events-list" data-calendar-events
                        data-calendar-name="{{ $googleCalendar['calendar'] ?: 'Google Calendar' }}">
                        @forelse (collect($googleCalendar['events'])->groupBy(fn ($event) => $event['start']->format('Y-m-d')) as $eventDate => $dayEvents)
                            <h3 class="events-day-heading">{{ $dayEvents->first()['start']->isToday() ? 'Hoy' : str($dayEvents->first()['start']->locale('es')->isoFormat('dddd D [de] MMMM'))->ucfirst() }}</h3>
                            @foreach ($dayEvents as $event)
                            <article class="event-item {{ ['sage-event', 'clay-event', 'gold-event'][$loop->index % 3] }}">
                                <time>
                                    <strong>{{ $event['all_day'] ? 'Todo el día' : $event['start']->format('H:i') }}</strong>
                                    <small>{{ $event['all_day'] ? $event['start']->locale('es')->isoFormat('D MMM') : $event['end']->format('H:i') }}</small>
                                </time>
                                <div>
                                    <strong>{{ $event['title'] }}</strong>
                                    <small>{{ $event['location'] ?: 'Sin ubicación' }}</small>
                                </div>
                                <span class="event-status" title="Sincronizado con Google">G</span>
                            </article>
                            @endforeach
                        @empty
                            <div class="calendar-empty">
                                <strong>{{ $googleCalendar['synced'] ? 'No hay eventos este día' : 'No se pudo cargar el calendario' }}</strong>
                                <small>{{ $googleCalendar['synced'] ? 'Elige otro día de la semana o disfruta del hueco.' : 'Volveremos a intentarlo automáticamente.' }}</small>
                            </div>
                        @endforelse
                    </div>
                    <p class="sync-note {{ $googleCalendar['synced'] ? 'synced' : '' }}">
                        <span>{{ $googleCalendar['synced'] ? '✓' : '↻' }}</span>
                        {{ $googleCalendar['synced'] ? 'Sincronizado con Google Calendar' : 'Conexión temporalmente no disponible' }}
                    </p>
                </section>
            </div>

            <section id="familia" class="panel family-panel">
                <div class="section-heading">
                    <div><span class="eyebrow">EQUIPO</span>
                        <h2>Reparto de este mes</h2>
                    </div>
                    <button class="text-button" data-open-members>Gestionar equipo <span>→</span></button>
                </div>
                <div class="family-grid">
                    @foreach ($members as $member)
                        @php($percentage = $monthlyHouseCompletedCount ? (int) round(($member->monthly_completed_tasks_count / $monthlyHouseCompletedCount) * 100) : 0)
                        <article class="{{ $member->is_active ? '' : 'inactive-member' }}">
                            <span
                                class="avatar avatar-{{ $member->color }}">{{ str($member->name)->substr(0, 2)->upper() }}</span>
                            <div><strong>{{ $member->name }}</strong><small>{{ $member->monthly_completed_tasks_count }} completadas ·
                                    {{ $member->monthly_late_tasks_count }} con retraso
                                    @if ($member->monthly_average_delay_days > 0) · media {{ round($member->monthly_average_delay_days, 1) }} días @endif</small>
                                <div class="member-progress"><i style="width: {{ $percentage }}%"></i></div>
                            </div>
                            <b>{{ $member->is_active ? $percentage . '%' : 'Inactivo' }}</b>
                        </article>
                    @endforeach
                </div>
            </section>

        </main>

        <nav class="mobile-nav" aria-label="Navegación móvil">
            <a class="active" href="#inicio"><svg viewBox="0 0 24 24">
                    <path d="M4 13h6V4H4zm0 7h6v-5H4zm10 0h6v-9h-6zm0-16v5h6V4z" />
                </svg><span>Inicio</span></a>
            <a href="#tareas"><svg viewBox="0 0 24 24">
                    <path d="m4 12 2 2 4-4m3-3h7m-16 11 2 2 4-4m3 3h7" />
                </svg><span>Tareas</span></a>
            <button class="mobile-add" data-open-task aria-label="Nueva tarea"><svg viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path d="M12 5v14m-7-7h14" />
                </svg></button>
            <a href="#compra"><svg viewBox="0 0 24 24">
                    <path d="M3 4h2l2 12h10l3-8H6m3 12h.01M17 20h.01" />
                </svg><span>Compra</span></a>
            <a href="#menu"><svg viewBox="0 0 24 24">
                    <path d="M7 3v8m-3-8v5a3 3 0 0 0 6 0V3m-3 8v10m10-18v18" />
                </svg><span>Menú</span></a>
        </nav>
    </div>

    <dialog id="task-dialog">
        <form method="POST" action="{{ route('tasks.store') }}" data-prevent-double-submit data-fetch-form data-refresh="#tareas,.side-nav">
            @csrf
            <button class="dialog-close" type="button" data-close-task aria-label="Cerrar">×</button>
            <span class="eyebrow">NUEVA TAREA</span>
            <h2>¿Qué hay que hacer?</h2>
            <label>Nombre de la tarea<input type="text" name="title" value="{{ old('title') }}"
                    placeholder="Ej. Limpiar el baño" required></label>
            <fieldset class="icon-picker">
                <legend>Elige un icono</legend>
                @foreach (['home' => ['🏠', 'Hogar'], 'cleaning' => ['🧹', 'Limpieza'], 'kitchen' => ['🍽️', 'Cocina'], 'plants' => ['🌿', 'Plantas']] as $value => [$emoji, $label])
                    <label>
                        <input type="radio" name="icon" value="{{ $value }}"
                            @checked(old('icon', 'home') === $value)>
                        <span><b>{{ $emoji }}</b><small>{{ $label }}</small></span>
                    </label>
                @endforeach
            </fieldset>
            <label>Notas opcionales<input type="text" name="description" value="{{ old('description') }}"
                    placeholder="Algún detalle útil"></label>
            <div class="form-row"><label>Responsable<select name="user_id" required>
                        @foreach ($activeMembers as $member)
                            <option value="{{ $member->id }}" @selected((string) old('user_id') === (string) $member->id)>{{ $member->name }}
                            </option>
                        @endforeach
                    </select></label><label class="date-field">Fecha<span class="date-control"><span
                                class="date-value" data-date-value>{{ \Carbon\Carbon::parse(old('due_date', now()->format('Y-m-d')))->format('d/m/Y') }}</span><input
                                type="date" name="due_date" value="{{ old('due_date', now()->format('Y-m-d')) }}"><svg
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Zm-2 6h18M8 2v4m8-4v4" />
                            </svg></span></label></div>
            <label>Repetición<select name="recurrence" required>
                    <option value="none" @selected(old('recurrence', 'none') === 'none')>No se repite</option>
                    <option value="daily" @selected(old('recurrence') === 'daily')>Cada día</option>
                    <option value="weekly" @selected(old('recurrence') === 'weekly')>Cada semana</option>
                    <option value="monthly" @selected(old('recurrence') === 'monthly')>Cada mes</option>
                </select></label>
            <button class="primary-button dialog-submit" type="submit" data-submitting-label="Creando…">Crear tarea</button>
        </form>
    </dialog>
    <dialog id="members-dialog" class="members-dialog">
        <div class="members-dialog-body">
            <header class="members-dialog-header">
                <div><span class="eyebrow">EQUIPO DEL HOGAR</span>
                    <h2>Gestionar personas</h2>
                    <p>Añade personas y organiza quién se encarga de cada tarea.</p>
                </div>
                <button class="dialog-close" type="button" data-close-members aria-label="Cerrar">×</button>
            </header>
            <div class="member-editor-list" aria-label="Personas del hogar">
                @foreach ($members as $member)
                    <form method="POST" action="{{ route('members.update', $member) }}" data-fetch-form data-refresh=".member-editor-list,#familia"
                        class="member-editor {{ $member->is_active ? '' : 'inactive-member' }}">
                        @csrf @method('PUT')
                        <div class="member-identity">
                            <span
                                class="avatar avatar-{{ $member->color }}">{{ str($member->name)->substr(0, 2)->upper() }}</span>
                            <span><strong>{{ $member->name }}</strong><small>{{ $member->tasks_count }}
                                    {{ $member->tasks_count === 1 ? 'tarea asignada' : 'tareas asignadas' }}</small></span>
                            <i class="member-state">{{ $member->is_active ? 'Activo' : 'Inactivo' }}</i>
                        </div>
                        <div class="member-fields">
                            <label><span>Nombre</span><input name="name" value="{{ $member->name }}" required
                                    maxlength="80"></label>
                            <label><span>Color</span><select name="color">
                                    <option value="sage" @selected($member->color === 'sage')>Verde salvia</option>
                                    <option value="clay" @selected($member->color === 'clay')>Terracota</option>
                                    <option value="blue" @selected($member->color === 'blue')>Azul</option>
                                </select></label>
                        </div>
                        <div class="member-actions">
                            <button class="toggle-member" type="submit"
                                form="toggle-member-{{ $member->id }}">{{ $member->is_active ? 'Desactivar' : 'Volver a activar' }}</button>
                            <button class="save-member" type="submit">Guardar cambios</button>
                        </div>
                    </form>
                    <form id="toggle-member-{{ $member->id }}" method="POST" data-fetch-form data-refresh=".member-editor-list,#familia"
                        action="{{ route('members.toggle', $member) }}">@csrf @method('PATCH')</form>
                @endforeach
            </div>
            <form method="POST" action="{{ route('members.store') }}" class="new-member-form" data-fetch-form data-refresh=".member-editor-list,#familia">
                @csrf
                <div class="new-member-title"><span>＋</span>
                    <div><strong>Añadir otra persona</strong><small>No necesitará email ni contraseña.</small></div>
                </div>
                <div class="new-member-fields">
                    <label><span>Nombre</span><input name="name" placeholder="Ej. Ana" required
                            maxlength="80"></label>
                    <label><span>Color</span><select name="color">
                            <option value="sage">Verde salvia</option>
                            <option value="clay">Terracota</option>
                            <option value="blue">Azul</option>
                        </select></label>
                    <button class="primary-button" type="submit" data-submitting-label="Añadiendo…">Añadir al equipo</button>
                </div>
            </form>
            <p class="credentials-note"><span>i</span> Las credenciales se podrán activar más adelante sin perder
                tareas ni historial.</p>
        </div>
    </dialog>
    <dialog id="shopping-dialog" class="simple-dialog">
        <form method="POST" action="{{ route('shopping-items.store') }}" data-prevent-double-submit data-fetch-form data-refresh="#compra,.side-nav">
            @csrf
            <button class="dialog-close" type="button" data-close-shopping aria-label="Cerrar">×</button>
            <span class="eyebrow">LISTA DE LA COMPRA</span>
            <h2>Añadir artículo</h2>
            <label>¿Qué necesitas?<input name="name" placeholder="Ej. Leche" required maxlength="120"></label>
            <div class="form-row"><label>Cantidad<input name="quantity" placeholder="Ej. 2 litros"
                        maxlength="50"></label><label>Categoría<select name="category">
                        <option value="food">Comida</option>
                        <option value="cleaning">Productos de limpieza</option>
                        <option value="other">Otros</option>
                    </select></label></div>
            <button class="primary-button dialog-submit" type="submit" data-submitting-label="Añadiendo…">Añadir a la lista</button>
        </form>
    </dialog>
    <dialog id="meal-dialog" class="simple-dialog">
        <form method="POST" action="{{ route('meals.store') }}" data-meal-form data-prevent-double-submit data-fetch-form data-refresh="#menu"
            data-store-action="{{ route('meals.store') }}" data-update-action="{{ url('/meals') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" data-meal-method>
            <button class="dialog-close" type="button" data-close-meal aria-label="Cerrar">×</button>
            <span class="eyebrow">MENÚ SEMANAL</span>
            <h2 data-meal-dialog-title>Planificar comida</h2>
            <input type="hidden" name="meal_date"><input type="hidden" name="meal_type">
            <label>Plato<input name="name" placeholder="Ej. Lentejas con verduras" required
                    maxlength="150"></label>
            <label>Notas opcionales<input name="notes" placeholder="Ej. Preparar la noche anterior"
                    maxlength="1000"></label>
            <label>Ingredientes<textarea name="ingredients_text" rows="5" maxlength="3000"
                    placeholder="Un ingrediente por línea:&#10;Tomates | 4 unidades&#10;Arroz | 500 g"></textarea></label>
            <button class="primary-button dialog-submit" type="submit" data-submitting-label="Guardando…">Guardar en el menú</button>
        </form>
    </dialog>
    <dialog id="note-dialog" class="simple-dialog">
        <form method="POST" action="{{ route('family-notes.store') }}" data-prevent-double-submit data-fetch-form data-refresh=".house-notes-list">@csrf
            <button class="dialog-close" type="button" data-close-note aria-label="Cerrar">×</button>
            <span class="eyebrow">AVISO FAMILIAR</span><h2>Fijar un aviso</h2>
            <label>Mensaje<textarea name="content" rows="4" maxlength="280" placeholder="Ej. El técnico viene el jueves a las 10" required></textarea></label>
            <button class="primary-button dialog-submit" type="submit" data-submitting-label="Publicando…">Publicar aviso</button>
        </form>
    </dialog>
    <div class="toast {{ session('success') ? 'show' : '' }}" role="status" aria-live="polite">
        {{ session('success') }}</div>
    <script type="application/json" id="calendar-events-data">@json($calendarClientEvents)</script>
    @if ($errors->any())
        <script>
            window.taskFormHasErrors = true;
        </script>
    @endif
</body>

</html>
