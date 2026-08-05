<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseholdMemberController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(['sage', 'clay', 'blue'])],
        ]);

        User::create([...$validated, 'house_id' => $request->user()->house_id]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Miembro añadido al equipo.', 'refresh_url' => route('home')], 201)
            : redirect(route('home'))->with('success', 'Miembro añadido al equipo.');
    }

    public function update(Request $request, User $member): JsonResponse|RedirectResponse
    {
        abort_unless($member->house_id === $request->user()->house_id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(['sage', 'clay', 'blue'])],
        ]);

        $member->update($validated);

        return $request->expectsJson()
            ? response()->json(['message' => 'Miembro actualizado.', 'refresh_url' => route('home')])
            : redirect(route('home'))->with('success', 'Miembro actualizado.');
    }

    public function toggle(User $member): JsonResponse|RedirectResponse
    {
        abort_unless($member->house_id === request()->user()->house_id, 404);

        if ($member->is_active && User::where('house_id', $member->house_id)->where('is_active', true)->count() === 1) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Debe quedar al menos un miembro activo.', 'errors' => ['member' => ['Debe quedar al menos un miembro activo.']]], 422);
            }
            return back()->withErrors(['member' => 'Debe quedar al menos un miembro activo.']);
        }

        $member->update(['is_active' => ! $member->is_active]);

        $message = $member->is_active ? 'Miembro activado.' : 'Miembro desactivado.';
        return request()->expectsJson()
            ? response()->json(['message' => $message, 'refresh_url' => route('home')])
            : redirect(route('home'))->with('success', $message);
    }
}
