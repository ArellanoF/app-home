<?php

namespace App\Http\Controllers;

use App\Models\FamilyNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FamilyNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['content' => ['required', 'string', 'max:280']]);
        FamilyNote::create([
            ...$validated,
            'house_id' => $request->user()->house_id,
            'user_id' => $request->user()->id,
        ]);

        return redirect(route('home').'#avisos')->with('success', 'Aviso publicado.');
    }

    public function destroy(FamilyNote $familyNote): RedirectResponse
    {
        abort_unless($familyNote->house_id === request()->user()->house_id, 404);
        $familyNote->delete();

        return redirect(route('home').'#avisos')->with('success', 'Aviso eliminado.');
    }
}
