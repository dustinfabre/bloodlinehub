<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\ClubSeasonRace;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubSeasonRaceController extends Controller
{
    public function create(Club $club, ClubSeason $season): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        return Inertia::render('clubs/seasons/races/Create', [
            'club' => $club,
            'season' => $season,
        ]);
    }

    public function store(Request $request, Club $club, ClubSeason $season): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

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

        return redirect()->route('clubs.seasons.races.show', [$club, $season, $race])
            ->with('success', 'Race created successfully.');
    }

    public function show(Club $club, ClubSeason $season, ClubSeasonRace $race): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $race->load(['results.pigeon' => function ($query) {
            $query->select('id', 'band_number', 'name', 'sex', 'status', 'pigeon_status');
        }]);
        $season->load(['entries.pigeon' => function ($query) {
            $query->select('id', 'band_number', 'name', 'sex', 'status', 'pigeon_status');
        }]);

        return Inertia::render('clubs/seasons/races/Show', [
            'club' => $club,
            'season' => $season,
            'race' => $race,
        ]);
    }

    public function edit(Club $club, ClubSeason $season, ClubSeasonRace $race): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        return Inertia::render('clubs/seasons/races/Edit', [
            'club' => $club,
            'season' => $season,
            'race' => $race,
        ]);
    }

    public function update(Request $request, Club $club, ClubSeason $season, ClubSeasonRace $race): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

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

        return redirect()->route('clubs.seasons.races.show', [$club, $season, $race])
            ->with('success', 'Race updated successfully.');
    }

    public function destroy(Club $club, ClubSeason $season, ClubSeasonRace $race): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $race->delete();

        return redirect()->route('clubs.seasons.show', [$club, $season])
            ->with('success', 'Race deleted successfully.');
    }

    // Result Management
    public function addResult(Request $request, Club $club, ClubSeason $season, ClubSeasonRace $race): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

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

    public function removeResult(Club $club, ClubSeason $season, ClubSeasonRace $race, Pigeon $pigeon): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $race->results()->detach($pigeon->id);

        return back()->with('success', 'Result removed successfully.');
    }

    public function updateResult(Request $request, Club $club, ClubSeason $season, ClubSeasonRace $race, Pigeon $pigeon): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

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
    public function addAllEntries(Club $club, ClubSeason $season, ClubSeasonRace $race): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

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
