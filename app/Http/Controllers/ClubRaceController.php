<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClubRaceRequest;
use App\Http\Requests\UpdateClubRaceRequest;
use App\Models\ClubRace;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubRaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $clubRaces = ClubRace::where('user_id', auth()->id())
            ->withCount('pigeons')
            ->orderBy('race_date', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('club-races/Index', [
            'clubRaces' => $clubRaces,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('club-races/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClubRaceRequest $request): RedirectResponse
    {
        $clubRace = ClubRace::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('club-races.show', $clubRace)
            ->with('success', 'Club Race created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ClubRace $clubRace): Response
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        $clubRace->load('pigeons');

        // Get user's pigeons that can be added to this race (racing pigeons not already in this race)
        $availablePigeons = Pigeon::where('user_id', auth()->id())
            ->where('pigeon_status', 'racing')
            ->whereNotIn('id', $clubRace->pigeons->pluck('id'))
            ->get();

        return Inertia::render('club-races/Show', [
            'clubRace' => $clubRace,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClubRace $clubRace): Response
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        return Inertia::render('club-races/Edit', [
            'clubRace' => $clubRace,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClubRaceRequest $request, ClubRace $clubRace): RedirectResponse
    {
        $clubRace->update($request->validated());

        return redirect()->route('club-races.show', $clubRace)
            ->with('success', 'Club Race updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClubRace $clubRace): RedirectResponse
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        $clubRace->delete();

        return redirect()->route('club-races.index')
            ->with('success', 'Club Race deleted successfully.');
    }

    /**
     * Add a pigeon to the race.
     */
    public function addPigeon(Request $request, ClubRace $clubRace): RedirectResponse
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_id' => ['required', 'exists:pigeons,id'],
            'arrival_time' => ['nullable', 'date_format:H:i:s'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'position' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verify the pigeon belongs to the user
        $pigeon = Pigeon::where('id', $validated['pigeon_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $clubRace->pigeons()->attach($pigeon->id, [
            'arrival_time' => $validated['arrival_time'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'position' => $validated['position'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Pigeon added to race.');
    }

    /**
     * Remove a pigeon from the race.
     */
    public function removePigeon(ClubRace $clubRace, Pigeon $pigeon): RedirectResponse
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        $clubRace->pigeons()->detach($pigeon->id);

        return back()->with('success', 'Pigeon removed from race.');
    }

    /**
     * Update pigeon's race entry details.
     */
    public function updatePigeon(Request $request, ClubRace $clubRace, Pigeon $pigeon): RedirectResponse
    {
        abort_if($clubRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'arrival_time' => ['nullable', 'date_format:H:i:s'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'position' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $clubRace->pigeons()->updateExistingPivot($pigeon->id, $validated);

        return back()->with('success', 'Entry updated.');
    }
}
