<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubSeasonController extends Controller
{
    public function create(Club $club): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        return Inertia::render('clubs/seasons/Create', [
            'club' => $club,
        ]);
    }

    public function store(Request $request, Club $club): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
        ]);

        $season = $club->seasons()->create($validated);

        return redirect()->route('clubs.seasons.show', [$club, $season])
            ->with('success', 'Season created successfully.');
    }

    public function show(Club $club, ClubSeason $season): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $season->load(['entries.pigeon' => function ($query) {
            $query->select('id', 'band_number', 'name', 'sex', 'status', 'pigeon_status');
        }, 'races' => function ($query) {
            $query->orderBy('race_date', 'desc');
        }]);

        // Add arrived_count and total_entries to each race
        $season->races->each(function ($race) {
            $race->arrived_count = $race->arrived_count;
            $race->total_entries = $race->total_entries;
        });

        // Get available pigeons (racing status, alive status, not already entered in this season)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->where('pigeon_status', 'racing')
            ->where('status', 'alive')
            ->whereNotIn('id', $season->entries->pluck('pigeon_id'))
            ->get();

        return Inertia::render('clubs/seasons/Show', [
            'club' => $club,
            'season' => $season,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    public function edit(Club $club, ClubSeason $season): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        return Inertia::render('clubs/seasons/Edit', [
            'club' => $club,
            'season' => $season,
        ]);
    }

    public function update(Request $request, Club $club, ClubSeason $season): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
        ]);

        $season->update($validated);

        return redirect()->route('clubs.seasons.show', [$club, $season])
            ->with('success', 'Season updated successfully.');
    }

    public function destroy(Club $club, ClubSeason $season): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $season->delete();

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Season deleted successfully.');
    }

    // Entry Management
    public function addEntry(Request $request, Club $club, ClubSeason $season): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_id' => ['required', 'exists:pigeons,id'],
            'entry_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verify the pigeon belongs to the user
        $pigeon = Pigeon::where('id', $validated['pigeon_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $season->entries()->attach($pigeon->id, [
            'entry_number' => $validated['entry_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Pigeon added to season.');
    }

    public function removeEntry(Club $club, ClubSeason $season, Pigeon $pigeon): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $season->entries()->detach($pigeon->id);

        // Also remove from all races in this season
        foreach ($season->races as $race) {
            $race->results()->detach($pigeon->id);
        }

        return back()->with('success', 'Pigeon removed from season.');
    }

    public function updateEntry(Request $request, Club $club, ClubSeason $season, Pigeon $pigeon): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'entry_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $season->entries()->updateExistingPivot($pigeon->id, [
            'entry_number' => $validated['entry_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Entry updated successfully.');
    }
}
