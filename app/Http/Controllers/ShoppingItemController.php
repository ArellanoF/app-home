<?php

namespace App\Http\Controllers;

use App\Models\ShoppingItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShoppingItemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $item = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'category' => ['required', Rule::in(['fruit', 'fresh', 'pantry', 'cleaning', 'other'])],
        ]);

        ShoppingItem::create($item);

        return redirect(route('home').'#compra')->with('success', 'Artículo añadido a la compra.');
    }

    public function toggle(ShoppingItem $shoppingItem): JsonResponse
    {
        $shoppingItem->update(['purchased_at' => $shoppingItem->purchased_at ? null : now()]);

        return response()->json(['purchased' => $shoppingItem->purchased_at !== null]);
    }

    public function destroy(ShoppingItem $shoppingItem): RedirectResponse
    {
        $shoppingItem->delete();

        return redirect(route('home').'#compra')->with('success', 'Artículo eliminado.');
    }
}
