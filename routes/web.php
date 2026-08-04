<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\FamilyNoteController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ShoppingItemController;
use App\Http\Controllers\TaskController;
use App\Models\Meal;
use App\Models\FamilyNote;
use App\Models\ShoppingItem;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
Route::get('/', function (GoogleCalendarService $calendar, WeatherService $weatherService, Request $request) {
    $house = $request->user()->house;
    $houseId = $house->id;
    $currentTime = CarbonImmutable::now(config('app.timezone'));
    $greeting = match (true) {
        $currentTime->hour < 12 => ['Buenos días', '☀️'],
        $currentTime->hour < 20 => ['Buenas tardes', '🌤️'],
        default => ['Buenas noches', '🌙'],
    };
    $dateFilterActive = false;

    try {
        $calendarDate = $request->filled('date')
            ? $request->string('date')
            : $request->string('week');
        $selectedDate = $calendarDate->isNotEmpty()
            ? CarbonImmutable::createFromFormat('Y-m-d', $calendarDate, config('app.timezone'))->startOfDay()
            : CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $dateFilterActive = $request->filled('date');
    } catch (\Throwable) {
        $selectedDate = CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    $weekStart = $selectedDate->startOfWeek();
    $weekEnd = $weekStart->endOfWeek();
    $weekDays = collect(range(0, 6))->map(fn ($offset) => $weekStart->addDays($offset));
    $weekCalendar = $calendar->upcoming(200, $weekStart, $house->google_calendar_ical_url);
    $weekEvents = collect($weekCalendar['events'])
        ->filter(fn ($event) => $event['start']->lessThanOrEqualTo($weekEnd)
            && $event['end']->greaterThanOrEqualTo($weekStart))
        ->values();
    $eventsByDate = $weekEvents->groupBy(fn ($event) => $event['start']->format('Y-m-d'));
    $selectedEvents = $dateFilterActive
        ? $eventsByDate->get($selectedDate->format('Y-m-d'), collect())->values()->all()
        : ($request->filled('week')
            ? $weekEvents->all()
            : $weekEvents
                ->filter(fn ($event) => $event['end']->greaterThanOrEqualTo(now(config('app.timezone'))))
                ->take(5)
                ->values()
                ->all());
    $googleCalendar = array_merge($weekCalendar, ['events' => $selectedEvents]);
    $calendarClientEvents = $weekEvents->map(fn ($event) => [
        'id' => $event['id'],
        'title' => $event['title'],
        'location' => $event['location'] ?: 'Sin ubicación',
        'date' => $event['start']->format('Y-m-d'),
        'date_label' => $event['start']->locale('es')->isoFormat('ddd D MMM'),
        'start' => $event['all_day'] ? 'Todo el día' : $event['start']->format('H:i'),
        'end' => $event['all_day'] ? $event['start']->locale('es')->isoFormat('D MMM') : $event['end']->format('H:i'),
        'all_day' => $event['all_day'],
        'is_upcoming' => $request->filled('week')
            || $event['end']->greaterThanOrEqualTo(now(config('app.timezone'))),
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
    $weeklyMeals = Meal::where('house_id', $houseId)
        ->whereBetween('meal_date', [$menuWeekStart, $menuWeekStart->endOfWeek()])
        ->get()
        ->groupBy(fn ($meal) => $meal->meal_date->format('Y-m-d').'-'.$meal->meal_type);
    $monthlyTasks = Task::query()->where('house_id', $houseId)
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

    $pendingTasks = Task::query()->with('assignee')->where('house_id', $houseId)
        ->whereNull('completed_at')
        ->orderByRaw('due_date IS NULL')
        ->orderBy('due_date')
        ->latest('created_at')
        ->get();
    $recentCompletedTasks = Task::query()->with('assignee')->where('house_id', $houseId)
        ->whereNotNull('completed_at')
        ->latest('completed_at')
        ->limit(5)
        ->get();
    $tasks = $pendingTasks->concat($recentCompletedTasks);
    $attentionTasks = $pendingTasks
        ->filter(fn ($task) => $task->due_date?->lessThanOrEqualTo($currentTime->startOfDay()))
        ->take(5);
    $tasksCount = Task::where('house_id', $houseId)->count();
    $members = User::query()->where('house_id', $houseId)
        ->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
            'tasks as monthly_completed_tasks_count' => fn ($query) => $query
                ->whereBetween('completed_at', [$currentTime->startOfMonth(), $currentTime->endOfMonth()]),
            'tasks as monthly_late_tasks_count' => fn ($query) => $query
                ->whereBetween('completed_at', [$currentTime->startOfMonth(), $currentTime->endOfMonth()])
                ->whereNotNull('due_date')
                ->whereRaw('DATE(completed_at) > due_date'),
        ])
        ->orderByDesc('is_active')
        ->orderBy('name')
        ->get();
    $members->each(function (User $member) use ($currentTime) {
        $member->monthly_average_delay_days = (float) Task::query()
            ->where('user_id', $member->id)
            ->whereBetween('completed_at', [$currentTime->startOfMonth(), $currentTime->endOfMonth()])
            ->whereNotNull('due_date')
            ->whereRaw('DATE(completed_at) > due_date')
            ->selectRaw('COALESCE(AVG(DATEDIFF(completed_at, due_date)), 0) as average_delay')
            ->value('average_delay');
    });

    return view('welcome', [
        'house' => $house,
        'weather' => $weatherService->currentFor($house),
        'greeting' => $greeting[0],
        'greetingIcon' => $greeting[1],
        'currentTime' => $currentTime,
        'currentDateLabel' => str($currentTime->locale('es')->isoFormat('dddd, D [de] MMMM'))->ucfirst(),
        'googleCalendar' => $googleCalendar,
        'googleCalendarUrl' => $house->google_calendar_url,
        'selectedDate' => $selectedDate,
        'weekDays' => $weekDays,
        'weekStart' => $weekStart,
        'eventsByDate' => $eventsByDate,
        'dateFilterActive' => $dateFilterActive,
        'calendarClientEvents' => $calendarClientEvents,
        'shoppingItems' => ShoppingItem::query()->where('house_id', $houseId)->whereNull('purchased_at')->latest()->get(),
        'shoppingPendingCount' => ShoppingItem::where('house_id', $houseId)->whereNull('purchased_at')->count(),
        'menuWeekStart' => $menuWeekStart,
        'menuDays' => $menuDays,
        'weeklyMeals' => $weeklyMeals,
        'tasks' => $tasks,
        'attentionTasks' => $attentionTasks,
        'tasksCount' => $tasksCount,
        'recentCompletedTasksCount' => $recentCompletedTasks->count(),
        'pendingTasksCount' => Task::where('house_id', $houseId)->whereNull('completed_at')->count(),
        'completedTasksCount' => Task::where('house_id', $houseId)->whereNotNull('completed_at')->count(),
        'members' => $members,
        'activeMembers' => User::where('house_id', $houseId)->where('is_active', true)->orderBy('name')->get(),
        'familyNotes' => FamilyNote::with('author')->where('house_id', $houseId)->latest()->limit(6)->get(),
        'monthlyHouseCompletedCount' => Task::where('house_id', $houseId)
            ->whereBetween('completed_at', [$currentTime->startOfMonth(), $currentTime->endOfMonth()])->count(),
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
Route::patch('/tasks/{task}/reassign', [TaskController::class, 'reassign'])->name('tasks.reassign');
Route::patch('/tasks/{task}/postpone', [TaskController::class, 'postpone'])->name('tasks.postpone');
Route::post('/members', [HouseholdMemberController::class, 'store'])->name('members.store');
Route::put('/members/{member}', [HouseholdMemberController::class, 'update'])->name('members.update');
Route::patch('/members/{member}/toggle', [HouseholdMemberController::class, 'toggle'])->name('members.toggle');
Route::post('/shopping-items', [ShoppingItemController::class, 'store'])->name('shopping-items.store');
Route::patch('/shopping-items/{shoppingItem}/toggle', [ShoppingItemController::class, 'toggle'])->name('shopping-items.toggle');
Route::delete('/shopping-items/{shoppingItem}', [ShoppingItemController::class, 'destroy'])->name('shopping-items.destroy');
Route::post('/meals', [MealController::class, 'store'])->name('meals.store');
Route::put('/meals/{meal}', [MealController::class, 'update'])->name('meals.update');
Route::delete('/meals/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');
Route::post('/meals/{meal}/shopping-list', [MealController::class, 'addIngredientsToShoppingList'])->name('meals.shopping-list');
Route::post('/family-notes', [FamilyNoteController::class, 'store'])->name('family-notes.store');
Route::delete('/family-notes/{familyNote}', [FamilyNoteController::class, 'destroy'])->name('family-notes.destroy');
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
