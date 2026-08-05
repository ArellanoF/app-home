<?php

namespace App\Http\Controllers;

use App\Models\FamilyNote;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FamilyNoteController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['content' => ['required', 'string', 'max:280']]);
        $author = $request->user();
        $note = FamilyNote::create([
            ...$validated,
            'house_id' => $author->house_id,
            'user_id' => $author->id,
        ]);

        try {
            app(WebPushService::class)->familyNoteCreated($note, $author);
        } catch (\Throwable $exception) {
            Log::error('El aviso se publicó, pero su notificación Web Push falló.', [
                'family_note_id' => $note->id,
                'exception' => $exception,
            ]);
        }

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
