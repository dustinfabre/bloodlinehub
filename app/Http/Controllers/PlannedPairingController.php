<?php

namespace App\Http\Controllers;

use App\Models\PlannedPairing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The breeding plan: pairs saved for the next season. Listed and analysed on the Pairing Analysis page.
 */
class PlannedPairingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $owned = fn () => Rule::exists('pigeons', 'id')->where('user_id', $user->id);

        $validated = $request->validate([
            'sire_id' => ['required', 'integer', $owned()],
            'dam_id' => ['required', 'integer', 'different:sire_id', $owned()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        PlannedPairing::updateOrCreate(
            ['user_id' => $user->id, 'sire_id' => $validated['sire_id'], 'dam_id' => $validated['dam_id']],
            ['notes' => $validated['notes'] ?? null],
        );

        return back()->with('success', 'Saved to your breeding plan.');
    }

    public function update(Request $request, PlannedPairing $plannedPairing): RedirectResponse
    {
        $this->ensureOwner($request, $plannedPairing);

        $plannedPairing->update($request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Plan note saved.');
    }

    public function destroy(Request $request, PlannedPairing $plannedPairing): RedirectResponse
    {
        $this->ensureOwner($request, $plannedPairing);

        $plannedPairing->delete();

        return back()->with('success', 'Removed from your breeding plan.');
    }

    private function ensureOwner(Request $request, PlannedPairing $plannedPairing): void
    {
        if ($plannedPairing->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
