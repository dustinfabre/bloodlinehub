<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\OlrRace;
use App\Models\OlrSeason;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OlrSeasonController extends Controller
{
    public function create(OlrRace $olrRace): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        // Get available pigeons (not deceased, missing, or flyaway)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->whereNotIn('status', ['deceased', 'missing', 'flyaway'])
            ->select('id', 'ring_number', 'personal_number', 'name', 'color')
            ->orderBy('ring_number')
            ->orderBy('personal_number')
            ->get();

        return Inertia::render('olr-races/seasons/Create', [
            'olrRace' => $olrRace,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    public function store(Request $request, OlrRace $olrRace): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
            'pigeon_ids' => ['nullable', 'array'],
            'pigeon_ids.*' => ['exists:pigeons,id'],
        ]);

        $season = $olrRace->seasons()->create([
            'name' => $validated['name'],
            'year' => $validated['year'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
        ]);

        // Add pigeons if provided
        if (!empty($validated['pigeon_ids'])) {
            $pigeons = Pigeon::whereIn('id', $validated['pigeon_ids'])
                ->where('user_id', auth()->id())
                ->pluck('id');
            $season->entries()->attach($pigeons);
        }

        return redirect()->route('olr-races.seasons.show', [$olrRace, $season])
            ->with('success', 'Season created successfully.');
    }

    public function show(OlrRace $olrRace, OlrSeason $season): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status');
        }, 'races' => function ($query) {
            $query->orderBy('race_date', 'desc');
        }]);

        // Add arrived_count and total_entries to each race
        $season->races->each(function ($race) {
            $race->arrived_count = $race->arrived_count;
            $race->total_entries = $race->total_entries;
        });

        // Get available pigeons (not already entered in this season)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->whereNotIn('status', ['deceased', 'missing', 'flyaway'])
            ->whereNotIn('id', $season->entries->pluck('id'))
            ->orderBy('ring_number')
            ->orderBy('personal_number')
            ->get();

        return Inertia::render('olr-races/seasons/Show', [
            'olrRace' => $olrRace,
            'season' => $season,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    public function edit(OlrRace $olrRace, OlrSeason $season): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        return Inertia::render('olr-races/seasons/Edit', [
            'olrRace' => $olrRace,
            'season' => $season,
        ]);
    }

    public function update(Request $request, OlrRace $olrRace, OlrSeason $season): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
        ]);

        $season->update($validated);

        return redirect()->route('olr-races.seasons.show', [$olrRace, $season])
            ->with('success', 'Season updated successfully.');
    }

    public function destroy(OlrRace $olrRace, OlrSeason $season): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $season->delete();

        return redirect()->route('olr-races.show', $olrRace)
            ->with('success', 'Season deleted successfully.');
    }

    // Entry Management
    public function addEntry(Request $request, OlrRace $olrRace, OlrSeason $season): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_id' => ['required', 'exists:pigeons,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verify the pigeon belongs to the user
        $pigeon = Pigeon::where('id', $validated['pigeon_id'])
            ->where('user_id', auth()->id())
            ->with('location')
            ->firstOrFail();

        $season->entries()->attach($pigeon->id, [
            'notes' => $validated['notes'] ?? null,
        ]);

        // Auto-set pigeon location to linked OLR location only if not already in OLR location
        $olrLocation = Location::where('olr_race_id', $olrRace->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($olrLocation) {
            $currentLocation = $pigeon->location;
            if (!$currentLocation || $currentLocation->type !== 'olr') {
                $pigeon->update(['location_id' => $olrLocation->id]);
            }
        }

        return back()->with('success', 'Pigeon added to season.');
    }

    public function addBulkEntries(Request $request, OlrRace $olrRace, OlrSeason $season): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_ids' => ['required', 'array'],
            'pigeon_ids.*' => ['exists:pigeons,id'],
        ]);

        // Verify all pigeons belong to the user
        $pigeons = Pigeon::whereIn('id', $validated['pigeon_ids'])
            ->where('user_id', auth()->id())
            ->pluck('id');

        if ($pigeons->count() !== count($validated['pigeon_ids'])) {
            return back()->withErrors(['pigeon_ids' => 'Some pigeons do not belong to you.']);
        }

        // Attach all pigeons
        foreach ($pigeons as $pigeonId) {
            if (!$season->entries()->where('pigeon_id', $pigeonId)->exists()) {
                $season->entries()->attach($pigeonId);
            }
        }

        // Auto-set pigeon location to linked OLR location only if not already in OLR location
        $olrLocation = Location::where('olr_race_id', $olrRace->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($olrLocation) {
            Pigeon::whereIn('id', $pigeons->toArray())
                ->where(function ($q) {
                    $q->whereNull('location_id')
                      ->orWhereHas('location', fn ($lq) => $lq->where('type', '!=', 'olr'));
                })
                ->update(['location_id' => $olrLocation->id]);
        }

        return back()->with('success', count($pigeons) . ' pigeon(s) added to season.');
    }

    public function removeEntry(OlrRace $olrRace, OlrSeason $season, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $season->entries()->detach($pigeon->id);

        // Also remove from all races in this season
        foreach ($season->races as $race) {
            $race->results()->detach($pigeon->id);
        }

        return back()->with('success', 'Pigeon removed from season.');
    }

    public function updateEntry(Request $request, OlrRace $olrRace, OlrSeason $season, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:stock,racing,breeding,injured,deceased,flyaway,missing'],
        ]);

        $updateData = [
            'notes' => $validated['notes'] ?? null,
        ];

        // If status is provided, update the pigeon's status directly
        if (isset($validated['status'])) {
            $pigeon->update(['status' => $validated['status']]);
        }

        $season->entries()->updateExistingPivot($pigeon->id, $updateData);

        return back()->with('success', 'Entry updated successfully.');
    }
}
