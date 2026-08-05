<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\ShoppingItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MealController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $meal = $request->validate([
            'meal_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(['lunch', 'dinner'])],
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'ingredients_text' => ['nullable', 'string', 'max:3000'],
        ]);

        $meal['ingredients'] = $this->parseIngredients($meal['ingredients_text'] ?? '');
        unset($meal['ingredients_text']);

        Meal::create([...$meal, 'house_id' => $request->user()->house_id]);

        $refreshUrl = route('home', ['menu_week' => $meal['meal_date']]);
        return $request->expectsJson()
            ? response()->json(['message' => 'Menú semanal actualizado.', 'refresh_url' => $refreshUrl], 201)
            : redirect($refreshUrl)->with('success', 'Menú semanal actualizado.');
    }

    public function update(Request $request, Meal $meal): JsonResponse|RedirectResponse
    {
        abort_unless($meal->house_id === $request->user()->house_id, 404);

        $validated = $request->validate([
            'meal_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(['lunch', 'dinner'])],
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'ingredients_text' => ['nullable', 'string', 'max:3000'],
        ]);

        $validated['ingredients'] = $this->parseIngredients($validated['ingredients_text'] ?? '');
        unset($validated['ingredients_text']);

        $meal->update($validated);

        $refreshUrl = route('home', ['menu_week' => $validated['meal_date']]);
        return $request->expectsJson()
            ? response()->json(['message' => 'Plato actualizado.', 'refresh_url' => $refreshUrl])
            : redirect($refreshUrl)->with('success', 'Plato actualizado.');
    }

    public function destroy(Meal $meal): JsonResponse|RedirectResponse
    {
        abort_unless($meal->house_id === request()->user()->house_id, 404);
        $week = $meal->meal_date->format('Y-m-d');
        $meal->delete();

        $refreshUrl = route('home', ['menu_week' => $week]);
        return request()->expectsJson()
            ? response()->json(['message' => 'Plato eliminado del menú.', 'refresh_url' => $refreshUrl])
            : redirect($refreshUrl)->with('success', 'Plato eliminado del menú.');
    }

    public function addIngredientsToShoppingList(Meal $meal): JsonResponse|RedirectResponse
    {
        abort_unless($meal->house_id === request()->user()->house_id, 404);

        foreach ($meal->ingredients ?? [] as $ingredient) {
            ShoppingItem::firstOrCreate([
                'house_id' => $meal->house_id,
                'name' => $ingredient['name'],
                'purchased_at' => null,
            ], [
                'quantity' => $ingredient['quantity'] ?: null,
                'category' => 'food',
            ]);
        }

        return request()->expectsJson()
            ? response()->json(['message' => 'Ingredientes añadidos a la compra.', 'refresh_url' => route('home')])
            : redirect(route('home'))->with('success', 'Ingredientes añadidos a la compra.');
    }

    /** @return array<int, array{name: string, quantity: string}> */
    private function parseIngredients(string $ingredients): array
    {
        return collect(preg_split('/\R/', $ingredients))
            ->map(function (string $line) {
                [$name, $quantity] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

                return ['name' => $name, 'quantity' => $quantity];
            })
            ->filter(fn (array $ingredient) => $ingredient['name'] !== '')
            ->values()
            ->all();
    }
}
