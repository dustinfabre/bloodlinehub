<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\ClubSeasonEvent;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubSeasonEventController extends Controller
{
    public function store(Request $request, Club $club, ClubSeason $season): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $season->events()->create($validated);

        return back()->with('success', 'Event created successfully.');
    }

    public function show(Club $club, ClubSeason $season, ClubSeasonEvent $event): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $event->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'ring_number', 'personal_number', 'name', 'gender', 'status');
        }]);

        // Get team pigeons not yet entered in this event
        $season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'ring_number', 'personal_number', 'name', 'gender', 'status');
        }]);

        $enteredPigeonIds = $event->entries->pluck('id')->toArray();
        $availablePigeons = $season->entries
            ->filter(fn ($pigeon) => !in_array($pigeon->id, $enteredPigeonIds))
            ->values();

        return Inertia::render('clubs/seasons/events/Show', [
            'club' => $club,
            'season' => $season,
            'event' => $event,
            'availablePigeons' => $availablePigeons,
        ]);
    }

    public function update(Request $request, Club $club, ClubSeason $season, ClubSeasonEvent $event): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $event->update($validated);

        return back()->with('success', 'Event updated successfully.');
    }

    public function destroy(Club $club, ClubSeason $season, ClubSeasonEvent $event): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $event->delete();

        return back()->with('success', 'Event deleted successfully.');
    }

    public function addEntry(Request $request, Club $club, ClubSeason $season, ClubSeasonEvent $event): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_id' => ['required', 'exists:pigeons,id'],
        ]);

        $pigeon = Pigeon::where('id', $validated['pigeon_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!$event->entries()->where('pigeon_id', $pigeon->id)->exists()) {
            $event->entries()->attach($pigeon->id);
        }

        return back()->with('success', 'Pigeon added to event.');
    }

    public function addBulkEntries(Request $request, Club $club, ClubSeason $season, ClubSeasonEvent $event): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'pigeon_ids' => ['required', 'array'],
            'pigeon_ids.*' => ['exists:pigeons,id'],
        ]);

        $pigeons = Pigeon::whereIn('id', $validated['pigeon_ids'])
            ->where('user_id', auth()->id())
            ->pluck('id');

        foreach ($pigeons as $pigeonId) {
            if (!$event->entries()->where('pigeon_id', $pigeonId)->exists()) {
                $event->entries()->attach($pigeonId);
            }
        }

        return back()->with('success', count($pigeons) . ' pigeon(s) added to event.');
    }

    public function removeEntry(Club $club, ClubSeason $season, ClubSeasonEvent $event, Pigeon $pigeon): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $event->entries()->detach($pigeon->id);

        return back()->with('success', 'Pigeon removed from event.');
    }
}
