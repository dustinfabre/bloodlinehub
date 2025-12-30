<?php

namespace App\Http\Controllers;

use App\Models\OlrRace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OlrRaceController extends Controller
{
    public function index(): Response
    {
        $olrRaces = OlrRace::where('user_id', auth()->id())
            ->withCount('seasons')
            ->orderBy('name')
            ->get();

        return Inertia::render('olr-races/Index', [
            'olrRaces' => $olrRaces,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('olr-races/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $olrRace = OlrRace::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('olr-races.show', $olrRace)
            ->with('success', 'OLR Race created successfully.');
    }

    public function show(OlrRace $olrRace): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $olrRace->load(['seasons' => function ($query) {
            $query->withCount(['entries', 'races'])->orderBy('year', 'desc');
        }]);

        return Inertia::render('olr-races/Show', [
            'olrRace' => $olrRace,
        ]);
    }

    public function edit(OlrRace $olrRace): Response
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        return Inertia::render('olr-races/Edit', [
            'olrRace' => $olrRace,
        ]);
    }

    public function update(Request $request, OlrRace $olrRace): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $olrRace->update($validated);

        return redirect()->route('olr-races.show', $olrRace)
            ->with('success', 'OLR Race updated successfully.');
    }

    public function destroy(OlrRace $olrRace): RedirectResponse
    {
        abort_if($olrRace->user_id !== auth()->id(), 403);

        $olrRace->delete();

        return redirect()->route('olr-races.index')
            ->with('success', 'OLR Race deleted successfully.');
    }
}
