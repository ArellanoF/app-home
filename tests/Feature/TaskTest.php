<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_can_be_created_for_carmen(): void
    {
        $response = $this->post(route('tasks.store'), [
            'title' => 'Limpiar el baño',
            'icon' => 'cleaning',
            'description' => 'Usar el producto para los azulejos.',
            'user_id' => User::where('name', 'Carmen')->value('id'),
            'due_date' => '2026-08-04',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('tasks', [
            'title' => 'Limpiar el baño',
            'description' => 'Usar el producto para los azulejos.',
            'user_id' => User::where('name', 'Carmen')->value('id'),
        ]);
    }

    public function test_only_an_active_household_member_can_be_assigned(): void
    {
        $this->post(route('tasks.store'), [
            'title' => 'Tarea no válida',
            'icon' => 'home',
            'user_id' => 999999,
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_a_task_can_be_toggled(): void
    {
        $task = Task::create([
            'title' => 'Regar las plantas',
            'icon' => 'plants',
            'user_id' => User::where('name', 'Fran')->value('id'),
        ]);

        $this->patchJson(route('tasks.toggle', $task))
            ->assertOk()
            ->assertJson(['completed' => true]);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_the_monthly_progress_is_calculated_from_real_tasks(): void
    {
        $fran = User::where('name', 'Fran')->firstOrFail();

        $fran->tasks()->create([
            'title' => 'Tarea terminada',
            'icon' => 'home',
            'due_date' => now()->startOfMonth(),
            'completed_at' => now(),
        ]);
        $fran->tasks()->create([
            'title' => 'Tarea pendiente',
            'icon' => 'home',
            'due_date' => now()->endOfMonth(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('50% completado')
            ->assertViewHas('monthlyCompletedCount', 1)
            ->assertViewHas('monthlyTasksCount', 2);
    }

    public function test_the_task_card_shows_four_tasks_and_can_expand(): void
    {
        $fran = User::where('name', 'Fran')->firstOrFail();

        foreach (range(1, 5) as $number) {
            $fran->tasks()->create(['title' => "Tarea {$number}", 'icon' => 'home']);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ver todas')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->count() === 4);

        $this->get(route('home', ['tasks' => 1]))
            ->assertOk()
            ->assertSee('Ver menos')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->count() === 5);
    }
}
