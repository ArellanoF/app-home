<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_household_starts_with_fran_and_carmen(): void
    {
        $this->assertDatabaseHas('users', ['name' => 'Fran', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['name' => 'Carmen', 'is_active' => true]);
    }

    public function test_a_member_can_be_added_without_credentials(): void
    {
        $this->post(route('members.store'), ['name' => 'Alex', 'color' => 'blue'])
            ->assertRedirect(route('home').'#familia');

        $this->assertDatabaseHas('users', [
            'name' => 'Alex', 'email' => null, 'password' => null, 'is_active' => true,
        ]);
    }

    public function test_a_member_with_tasks_can_be_deactivated_without_losing_history(): void
    {
        $member = User::where('name', 'Carmen')->firstOrFail();
        $member->tasks()->create(['title' => 'Comprar pan']);

        $this->patch(route('members.toggle', $member));

        $this->assertFalse($member->fresh()->is_active);
        $this->assertDatabaseHas('tasks', ['title' => 'Comprar pan', 'user_id' => $member->id]);
    }
}
