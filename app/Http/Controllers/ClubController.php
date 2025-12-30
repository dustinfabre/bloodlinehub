<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function index(): Response
    {
        $clubs = Club::where('user_id', auth()->id())
            ->withCount('seasons')
            ->orderBy('name')
            ->get();

        return Inertia::render('clubs/Index', [
            'clubs' => $clubs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clubs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $club = Club::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Club created successfully.');
    }

    public function show(Club $club): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $club->load(['seasons' => function ($query) {
            $query->withCount(['entries', 'races'])->orderBy('year', 'desc');
        }]);

        return Inertia::render('clubs/Show', [
            'club' => $club,
        ]);
    }

    public function edit(Club $club): Response
    {
        abort_if($club->user_id !== auth()->id(), 403);

        return Inertia::render('clubs/Edit', [
            'club' => $club,
        ]);
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $club->update($validated);

        return redirect()->route('clubs.show', $club)
            ->with('success', 'Club updated successfully.');
    }

    public function destroy(Club $club): RedirectResponse
    {
        abort_if($club->user_id !== auth()->id(), 403);

        $club->delete();

        return redirect()->route('clubs.index')
            ->with('success', 'Club deleted successfully.');
    }
}
