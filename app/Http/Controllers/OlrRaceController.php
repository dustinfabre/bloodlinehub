<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOlrRaceRequest;
use App\Http\Requests\UpdateOlrRaceRequest;
use App\Models\OlrRace;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OlrRaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $olrRaces = OlrRace::where('user_id', auth()->id())
            ->withCount('pigeons')
            ->orderBy('year', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('olr-races/Index', [
            'olrRaces' => $olrRaces,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('olr-races/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOlrRaceRequest $request): RedirectResponse
    {
        $olrRace = OlrRace::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('olr-races.show', $olrRace)
            ->with('success', 'OLR Race created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(OlrRace $olrRace): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $olrRace->load('pigeons');

        // Get user's pigeons that can be added to this race (race_type = 'olr' and not already in this race)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->where('race_type', 'olr')
            ->whereNotIn('id', $olrRace->pigeons->pluck('id'))
            ->get();

        return Inertia::render('olr-races/Show', [
            'olrRace' => $olrRace,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OlrRace $olrRace): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        return Inertia::render('olr-races/Edit', [
            'olrRace' => $olrRace,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOlrRaceRequest $request, OlrRace $olrRace): RedirectResponse
    {
        $olrRace->update($request->validated());

        return redirect()->route('olr-races.show', $olrRace)
            ->with('success', 'OLR Race updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OlrRace $olrRace): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $olrRace->delete();

        return redirect()->route('olr-races.index')
            ->with('success', 'OLR Race deleted successfully.');
    }

    /**
     * Add a pigeon to the race.
     */
    public function addPigeon(Request $request, OlrRace $olrRace): RedirectResponse
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

        $olrRace->pigeons()->attach($pigeon->id, [
            'entry_number' => $validated['entry_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Pigeon added to race.');
    }

    /**
     * Remove a pigeon from the race.
     */
    public function removePigeon(OlrRace $olrRace, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $olrRace->pigeons()->detach($pigeon->id);

        return back()->with('success', 'Pigeon removed from race.');
    }

    /**
     * Update pigeon's race entry details.
     */
    public function updatePigeon(Request $request, OlrRace $olrRace, Pigeon $pigeon): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'entry_number' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $olrRace->pigeons()->updateExistingPivot($pigeon->id, $validated);

        return back()->with('success', 'Entry updated.');
    }
}
