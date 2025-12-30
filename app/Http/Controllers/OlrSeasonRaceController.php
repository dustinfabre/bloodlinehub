<?php

namespace App\Http\Controllers;

use App\Models\OlrRace;
use App\Models\OlrSeason;
use App\Models\OlrSeasonRace;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OlrSeasonRaceController extends Controller
{
    public function create(OlrRace $olrRace, OlrSeason $season): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        return Inertia::render('olr-races/seasons/races/Create', [
            'olrRace' => $olrRace,
            'season' => $season,
        ]);
    }

    public function store(Request $request, OlrRace $olrRace, OlrSeason $season): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'release_point' => ['nullable', 'string', 'max:255'],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'distance_unit' => ['required', 'string', 'in:km,mi'],
            'race_date' => ['nullable', 'date'],
            'release_time' => ['nullable', 'date_format:H:i'],
            'weather_conditions' => ['nullable', 'string', 'max:255'],
            'wind_direction' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $race = $season->races()->create($validated);

        return redirect()->route('olr-races.seasons.races.show', [$olrRace, $season, $race])
            ->with('success', 'Race created successfully.');
    }

    public function show(OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $race->load(['results' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status', 'pigeons.pigeon_status');
        }]);
        $season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status', 'pigeons.pigeon_status');
        }]);

        // Get season entries that are not yet in this race
        $availablePigeons = $season->entries()
            ->whereNotIn('pigeons.id', $race->results->pluck('id'))
            ->get(['pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status', 'pigeons.pigeon_status']);

        return Inertia::render('olr-races/seasons/races/Show', [
            'olrRace' => $olrRace,
            'season' => $season,
            'race' => $race,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    public function edit(OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        return Inertia::render('olr-races/seasons/races/Edit', [
            'olrRace' => $olrRace,
            'season' => $season,
            'race' => $race,
        ]);
    }

    public function update(Request $request, OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'release_point' => ['nullable', 'string', 'max:255'],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'distance_unit' => ['required', 'string', 'in:km,mi'],
            'race_date' => ['nullable', 'date'],
            'release_time' => ['nullable', 'date_format:H:i'],
            'weather_conditions' => ['nullable', 'string', 'max:255'],
            'wind_direction' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $race->update($validated);

        return redirect()->route('olr-races.seasons.races.show', [$olrRace, $season, $race])
            ->with('success', 'Race updated successfully.');
    }

    public function destroy(OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $race->delete();

        return redirect()->route('olr-races.seasons.show', [$olrRace, $season])
            ->with('success', 'Race deleted successfully.');
    }

    // Result Management
    public function addResult(Request $request, OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_id' => ['required', 'exists:pigeons,id'],
            'position' => ['nullable', 'integer', 'min:1'],
            'arrival_time' => ['nullable', 'date_format:H:i:s'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'did_not_arrive' => ['nullable', 'boolean'],
        ]);

        // Verify the pigeon is an entry in this season
        $pigeon = $season->entries()
            ->where('pigeons.id', $validated['pigeon_id'])
            ->firstOrFail();

        $race->results()->attach($pigeon->id, [
            'position' => $validated['position'] ?? null,
            'arrival_time' => $validated['arrival_time'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'did_not_arrive' => $validated['did_not_arrive'] ?? false,
        ]);

        return back()->with('success', 'Result added successfully.');
    }

    public function removeResult(OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $race->results()->detach($pigeon->id);

        return back()->with('success', 'Result removed successfully.');
    }

    public function updateResult(Request $request, OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'position' => ['nullable', 'integer', 'min:1'],
            'arrival_time' => ['nullable', 'date_format:H:i:s'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'did_not_arrive' => ['nullable', 'boolean'],
        ]);

        $race->results()->updateExistingPivot($pigeon->id, [
            'position' => $validated['position'] ?? null,
            'arrival_time' => $validated['arrival_time'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'did_not_arrive' => $validated['did_not_arrive'] ?? false,
        ]);

        return back()->with('success', 'Result updated successfully.');
    }

    // Bulk add all season entries to a race
    public function addAllEntries(OlrRace $olrRace, OlrSeason $season, OlrSeasonRace $race): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $existingIds = $race->results->pluck('id');
        $entriesToAdd = $season->entries()
            ->whereNotIn('pigeons.id', $existingIds)
            ->pluck('pigeons.id');

        foreach ($entriesToAdd as $pigeonId) {
            $race->results()->attach($pigeonId, [
                'did_not_arrive' => false,
            ]);
        }

        return back()->with('success', count($entriesToAdd) . ' pigeons added to race.');
    }
}
