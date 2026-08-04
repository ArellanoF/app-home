<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseholdMemberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(['sage', 'clay', 'blue'])],
        ]);

        User::create([...$validated, 'house_id' => $request->user()->house_id]);

        return redirect(route('home').'#familia')->with('success', 'Miembro añadido al equipo.');
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->house_id === $request->user()->house_id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(['sage', 'clay', 'blue'])],
        ]);

        $member->update($validated);

        return redirect(route('home').'#familia')->with('success', 'Miembro actualizado.');
    }

    public function toggle(User $member): RedirectResponse
    {
        abort_unless($member->house_id === request()->user()->house_id, 404);

        if ($member->is_active && User::where('house_id', $member->house_id)->where('is_active', true)->count() === 1) {
            return back()->withErrors(['member' => 'Debe quedar al menos un miembro activo.']);
        }

        $member->update(['is_active' => ! $member->is_active]);

        return redirect(route('home').'#familia')->with('success', $member->is_active ? 'Miembro activado.' : 'Miembro desactivado.');
    }
}
