<?php

namespace App\Http\Controllers;

use App\Models\ShoppingItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShoppingItemController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $item = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'category' => ['required', Rule::in(['food', 'cleaning', 'other'])],
        ]);

        ShoppingItem::create([...$item, 'house_id' => $request->user()->house_id]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Artículo añadido a la compra.', 'refresh_url' => route('home')], 201)
            : redirect(route('home'))->with('success', 'Artículo añadido a la compra.');
    }

    public function toggle(ShoppingItem $shoppingItem): JsonResponse
    {
        abort_unless($shoppingItem->house_id === request()->user()->house_id, 404);
        $shoppingItem->update(['purchased_at' => $shoppingItem->purchased_at ? null : now()]);

        return response()->json(['purchased' => $shoppingItem->purchased_at !== null]);
    }

    public function destroy(ShoppingItem $shoppingItem): JsonResponse|RedirectResponse
    {
        abort_unless($shoppingItem->house_id === request()->user()->house_id, 404);
        $shoppingItem->delete();

        return request()->expectsJson()
            ? response()->json(['message' => 'Artículo eliminado.', 'refresh_url' => route('home')])
            : redirect(route('home'))->with('success', 'Artículo eliminado.');
    }
}
