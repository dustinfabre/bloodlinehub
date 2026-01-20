<?php

namespace App\Http\Controllers;

use App\Models\Clutch;
use App\Models\Pairing;
use Illuminate\Http\Request;

class ClutchController extends Controller
{
    /**
     * Store a newly created clutch in storage.
     */
    public function store(Request $request, Pairing $pairing)
    {
        $validated = $request->validate([
            'eggs_laid_date' => ['nullable', 'date'],
            'hatched_date' => ['nullable', 'date', 'after_or_equal:eggs_laid_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Calculate next clutch number
        $nextClutchNumber = $pairing->clutches()->max('clutch_number') + 1;

        $clutch = $pairing->clutches()->create([
            'clutch_number' => $nextClutchNumber,
            'eggs_laid_date' => $validated['eggs_laid_date'] ?? null,
            'hatched_date' => $validated['hatched_date'] ?? null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update pairing's current clutch number
        $pairing->update(['current_clutch_number' => $nextClutchNumber]);

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Clutch added successfully.');
    }

    /**
     * Update the specified clutch in storage.
     */
    public function update(Request $request, Pairing $pairing, Clutch $clutch)
    {
        // Ensure clutch belongs to this pairing
        if ($clutch->pairing_id !== $pairing->id) {
            abort(404);
        }

        $validated = $request->validate([
            'eggs_laid_date' => ['nullable', 'date'],
            'hatched_date' => ['nullable', 'date', 'after_or_equal:eggs_laid_date'],
            'status' => ['required', 'in:pending,successful,unsuccessful'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $clutch->update($validated);

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Clutch updated successfully.');
    }

    /**
     * Remove the specified clutch from storage.
     */
    public function destroy(Pairing $pairing, Clutch $clutch)
    {
        // Ensure clutch belongs to this pairing
        if ($clutch->pairing_id !== $pairing->id) {
            abort(404);
        }

        // Cannot delete if there are offspring associated
        if ($clutch->offspring()->count() > 0) {
            return redirect()->route('pairings.show', $pairing)
                ->with('error', 'Cannot delete clutch with offspring records.');
        }

        $clutch->delete();

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Clutch deleted successfully.');
    }
}
