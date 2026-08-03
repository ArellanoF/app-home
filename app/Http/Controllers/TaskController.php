<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['required', Rule::in(['home', 'cleaning', 'kitchen', 'plants'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'due_date' => ['nullable', 'date'],
        ]);

        Task::create($validated);

        return to_route('home')->with('success', 'Tarea creada correctamente.');
    }

    public function toggle(Task $task): JsonResponse
    {
        $task->update([
            'completed_at' => $task->completed_at ? null : now(),
        ]);

        return response()->json([
            'completed' => $task->completed_at !== null,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ]);
    }
}
