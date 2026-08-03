<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MealController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $meal = $request->validate([
            'meal_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(['lunch', 'dinner'])],
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $existingMeal = Meal::query()
            ->whereDate('meal_date', $meal['meal_date'])
            ->where('meal_type', $meal['meal_type'])
            ->first();

        if ($existingMeal) {
            $existingMeal->update(['name' => $meal['name'], 'notes' => $meal['notes'] ?? null]);
        } else {
            Meal::create($meal);
        }

        return redirect(route('home', ['menu_week' => $meal['meal_date']]).'#menu')->with('success', 'Menú semanal actualizado.');
    }

    public function destroy(Meal $meal): RedirectResponse
    {
        $week = $meal->meal_date->format('Y-m-d');
        $meal->delete();

        return redirect(route('home', ['menu_week' => $week]).'#menu')->with('success', 'Plato eliminado del menú.');
    }
}
