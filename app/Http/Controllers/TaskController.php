<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\WebPushService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['required', Rule::in(['home', 'cleaning', 'kitchen', 'plants'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query
                    ->where('house_id', $request->user()->house_id)
                    ->where('is_active', true)),
            ],
            'due_date' => ['nullable', 'date'],
            'recurrence' => ['required', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
        ]);

        if ($validated['recurrence'] !== 'none' && empty($validated['due_date'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Las tareas recurrentes necesitan una fecha.', 'errors' => ['due_date' => ['Las tareas recurrentes necesitan una fecha.']]], 422);
            }

            return back()->withErrors(['due_date' => 'Las tareas recurrentes necesitan una fecha.'])->withInput();
        }

        $creator = $request->user();
        $task = Task::create([
            ...$validated,
            'house_id' => $creator->house_id,
            'created_by_user_id' => $creator->id,
        ]);

        if ($task->user_id !== $creator->id) {
            try {
                app(WebPushService::class)->taskAssigned($task, $creator);
            } catch (\Throwable $exception) {
                Log::error('La tarea se creó, pero su notificación Web Push falló.', [
                    'task_id' => $task->id,
                    'exception' => $exception,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Tarea creada correctamente.', 'refresh_url' => route('home')], 201);
        }

        return to_route('home')->with('success', 'Tarea creada correctamente.');
    }

    public function toggle(Task $task): JsonResponse
    {
        abort_unless($task->house_id === request()->user()->house_id, 404);

        $nextDueDate = DB::transaction(function () use ($task) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $completing = $task->completed_at === null;

            $today = now(config('app.timezone'))->startOfDay();

            if (! $completing
                || $task->recurrence === 'none'
                || ! $task->due_date
                || $task->due_date->isBefore($today)) {
                $task->update(['completed_at' => $completing ? now() : null]);

                return null;
            }

            $nextDueDate = match ($task->recurrence) {
                'daily' => $task->due_date->addDay(),
                'weekly' => $task->due_date->addWeek(),
                'monthly' => $task->due_date->addMonthNoOverflow(),
            };

            $task->update([
                'due_date' => $nextDueDate,
                'completed_at' => null,
            ]);

            return $nextDueDate;
        });

        $task->refresh();

        return response()->json([
            'completed' => $task->completed_at !== null,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'next_due_date' => $nextDueDate?->format('Y-m-d'),
        ]);
    }

    public function reassign(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->house_id === $request->user()->house_id, 404);
        $validated = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query
                    ->where('house_id', $request->user()->house_id)
                    ->where('is_active', true)),
            ],
        ]);

        $task->update($validated);
        $task->load('assignee');

        return response()->json([
            'name' => $task->assignee->name,
            'initials' => str($task->assignee->name)->substr(0, 2)->upper(),
            'color' => $task->assignee->color,
        ]);
    }

    public function postpone(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->house_id === $request->user()->house_id, 404);
        $validated = $request->validate(['until' => ['required', Rule::in(['tomorrow', 'next_week'])]]);
        $dueDate = $validated['until'] === 'tomorrow'
            ? now(config('app.timezone'))->addDay()->startOfDay()
            : now(config('app.timezone'))->addWeek()->startOfWeek();
        $task->update(['due_date' => $dueDate]);

        return response()->json([
            'due_date' => $dueDate->format('Y-m-d'),
            'label' => $dueDate->locale('es')->isoFormat('D [de] MMMM'),
        ]);
    }
}
