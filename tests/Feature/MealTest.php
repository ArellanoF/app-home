<?php

namespace Tests\Feature;

use App\Models\Meal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_meal_can_be_created_and_replaced_in_the_same_slot(): void
    {
        $date = '2026-08-05';
        $payload = ['meal_date' => $date, 'meal_type' => 'lunch', 'name' => 'Lentejas'];

        $this->post(route('meals.store'), $payload)->assertRedirect();
        $this->post(route('meals.store'), [...$payload, 'name' => 'Arroz'])->assertRedirect();

        $this->assertDatabaseCount('meals', 1);
        $meal = Meal::firstOrFail();
        $this->assertSame('Arroz', $meal->name);
        $this->assertSame($date, $meal->meal_date->format('Y-m-d'));
    }

    public function test_the_weekly_menu_is_visible_on_the_dashboard(): void
    {
        Meal::create(['meal_date' => '2026-08-05', 'meal_type' => 'dinner', 'name' => 'Tortilla']);

        $this->get('/?menu_week=2026-08-05')->assertOk()->assertSee('Tortilla');
    }
}
