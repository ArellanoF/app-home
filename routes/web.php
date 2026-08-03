<?php

use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ShoppingItemController;
use App\Http\Controllers\TaskController;
use App\Models\Meal;
use App\Models\ShoppingItem;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (GoogleCalendarService $calendar, Request $request) {
    $currentTime = CarbonImmutable::now(config('app.timezone'));
    $greeting = match (true) {
        $currentTime->hour < 12 => ['Buenos días', '☀️'],
        $currentTime->hour < 20 => ['Buenas tardes', '🌤️'],
        default => ['Buenas noches', '🌙'],
    };
    $dateFilterActive = false;

    try {
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('date'), config('app.timezone'))->startOfDay()
            : CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $dateFilterActive = $request->filled('date');
    } catch (\Throwable) {
        $selectedDate = CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    $weekStart = $selectedDate->startOfWeek();
    $weekDays = collect(range(0, 6))->map(fn ($offset) => $weekStart->addDays($offset));
    $weekCalendar = $calendar->upcoming(200, $weekStart);
    $eventsByDate = collect($weekCalendar['events'])->groupBy(fn ($event) => $event['start']->format('Y-m-d'));
    $selectedEvents = $dateFilterActive
        ? $eventsByDate->get($selectedDate->format('Y-m-d'), collect())->values()->all()
        : collect($weekCalendar['events'])
            ->filter(fn ($event) => $event['end']->greaterThanOrEqualTo(now(config('app.timezone'))))
            ->take(5)
            ->values()
            ->all();
    $googleCalendar = array_merge($weekCalendar, ['events' => $selectedEvents]);
    $calendarClientEvents = collect($weekCalendar['events'])->map(fn ($event) => [
        'id' => $event['id'],
        'title' => $event['title'],
        'location' => $event['location'] ?: 'Sin ubicación',
        'date' => $event['start']->format('Y-m-d'),
        'date_label' => $event['start']->locale('es')->isoFormat('ddd D MMM'),
        'start' => $event['all_day'] ? 'Todo el día' : $event['start']->format('H:i'),
        'end' => $event['all_day'] ? $event['start']->locale('es')->isoFormat('D MMM') : $event['end']->format('H:i'),
        'all_day' => $event['all_day'],
        'is_upcoming' => $event['end']->greaterThanOrEqualTo(now(config('app.timezone'))),
    ])->values();

    try {
        $menuDate = $request->filled('menu_week')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('menu_week'), config('app.timezone'))
            : CarbonImmutable::now(config('app.timezone'));
    } catch (\Throwable) {
        $menuDate = CarbonImmutable::now(config('app.timezone'));
    }
    $menuWeekStart = $menuDate->startOfWeek();
    $menuDays = collect(range(0, 6))->map(fn ($offset) => $menuWeekStart->addDays($offset));
    $weeklyMeals = Meal::whereBetween('meal_date', [$menuWeekStart, $menuWeekStart->endOfWeek()])
        ->get()
        ->keyBy(fn ($meal) => $meal->meal_date->format('Y-m-d').'-'.$meal->meal_type);
    $monthlyTasks = Task::query()
        ->where(function ($query) {
            $query->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->orWhere(function ($query) {
                    $query->whereNull('due_date')
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                });
        });
    $monthlyTasksCount = (clone $monthlyTasks)->count();
    $monthlyCompletedCount = (clone $monthlyTasks)->whereNotNull('completed_at')->count();
    $monthlyProgress = $monthlyTasksCount > 0
        ? (int) round(($monthlyCompletedCount / $monthlyTasksCount) * 100)
        : 0;

    $showAllTasks = $request->boolean('tasks');
    $tasksQuery = Task::query()->with('assignee')
        ->orderByRaw('completed_at IS NOT NULL')
        ->orderByRaw('due_date IS NULL')
        ->orderBy('due_date')
        ->latest('created_at');
    $tasksCount = (clone $tasksQuery)->count();

    return view('welcome', [
        'greeting' => $greeting[0],
        'greetingIcon' => $greeting[1],
        'currentDateLabel' => str($currentTime->locale('es')->isoFormat('dddd, D [de] MMMM'))->ucfirst(),
        'googleCalendar' => $googleCalendar,
        'googleCalendarUrl' => config('services.google_calendar.public_url'),
        'selectedDate' => $selectedDate,
        'weekDays' => $weekDays,
        'weekStart' => $weekStart,
        'eventsByDate' => $eventsByDate,
        'dateFilterActive' => $dateFilterActive,
        'calendarClientEvents' => $calendarClientEvents,
        'shoppingItems' => ShoppingItem::query()->orderByRaw('purchased_at IS NOT NULL')->latest()->get(),
        'shoppingPendingCount' => ShoppingItem::whereNull('purchased_at')->count(),
        'menuWeekStart' => $menuWeekStart,
        'menuDays' => $menuDays,
        'weeklyMeals' => $weeklyMeals,
        'tasks' => $showAllTasks ? $tasksQuery->get() : $tasksQuery->limit(4)->get(),
        'tasksCount' => $tasksCount,
        'showAllTasks' => $showAllTasks,
        'pendingTasksCount' => Task::whereNull('completed_at')->count(),
        'completedTasksCount' => Task::whereNotNull('completed_at')->count(),
        'members' => User::query()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(),
        'activeMembers' => User::where('is_active', true)->orderBy('name')->get(),
        'monthlyTasksCount' => $monthlyTasksCount,
        'monthlyCompletedCount' => $monthlyCompletedCount,
        'monthlyProgress' => $monthlyProgress,
        'monthlyProgressMessage' => match (true) {
            $monthlyTasksCount === 0 => 'Aún no hay tareas este mes',
            $monthlyProgress === 100 => '¡Todo listo este mes!',
            $monthlyProgress >= 75 => 'El hogar va genial',
            $monthlyProgress >= 40 => 'Vamos por buen camino',
            default => 'Queda trabajo por hacer',
        },
    ]);
})->name('home');

Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
Route::patch('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
Route::post('/members', [HouseholdMemberController::class, 'store'])->name('members.store');
Route::put('/members/{member}', [HouseholdMemberController::class, 'update'])->name('members.update');
Route::patch('/members/{member}/toggle', [HouseholdMemberController::class, 'toggle'])->name('members.toggle');
Route::post('/shopping-items', [ShoppingItemController::class, 'store'])->name('shopping-items.store');
Route::patch('/shopping-items/{shoppingItem}/toggle', [ShoppingItemController::class, 'toggle'])->name('shopping-items.toggle');
Route::delete('/shopping-items/{shoppingItem}', [ShoppingItemController::class, 'destroy'])->name('shopping-items.destroy');
Route::post('/meals', [MealController::class, 'store'])->name('meals.store');
Route::delete('/meals/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');
