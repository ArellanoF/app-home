<?php

namespace App\Http\Controllers;

use App\Models\FamilyNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FamilyNoteController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['content' => ['required', 'string', 'max:280']]);
        FamilyNote::create([
            ...$validated,
            'house_id' => $request->user()->house_id,
            'user_id' => $request->user()->id,
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Aviso publicado.', 'refresh_url' => route('home')], 201)
            : redirect(route('home'))->with('success', 'Aviso publicado.');
    }

    public function destroy(FamilyNote $familyNote): JsonResponse|RedirectResponse
    {
        abort_unless($familyNote->house_id === request()->user()->house_id, 404);
        $familyNote->delete();

        return request()->expectsJson()
            ? response()->json(['message' => 'Aviso eliminado.', 'refresh_url' => route('home')])
            : redirect(route('home'))->with('success', 'Aviso eliminado.');
    }
}
