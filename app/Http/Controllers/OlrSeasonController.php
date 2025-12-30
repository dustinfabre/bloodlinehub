<?php

namespace App\Http\Controllers;

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

        return Inertia::render('olr-races/seasons/Create', [
            'olrRace' => $olrRace,
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
        ]);

        $season = $olrRace->seasons()->create($validated);

        return redirect()->route('olr-races.seasons.show', [$olrRace, $season])
            ->with('success', 'Season created successfully.');
    }

    public function show(OlrRace $olrRace, OlrSeason $season): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status', 'pigeons.pigeon_status');
        }, 'races' => function ($query) {
            $query->orderBy('race_date', 'desc');
        }]);

        // Add arrived_count and total_entries to each race
        $season->races->each(function ($race) {
            $race->arrived_count = $race->arrived_count;
            $race->total_entries = $race->total_entries;
        });

        // Get available pigeons (OLR type, alive status, not already entered in this season)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->where('race_type', 'olr')
            ->where('status', 'alive')
            ->whereNotIn('id', $season->entries->pluck('id'))
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
