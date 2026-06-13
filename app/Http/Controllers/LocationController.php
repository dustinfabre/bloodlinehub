<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $locations = Location::where('user_id', $request->user()->id)
            ->withCount(['pigeons', 'pairings', 'clutches'])
            ->orderBy('name')
            ->get();

        return Inertia::render('locations/Index', [
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'You already have a location with this name.',
        ]);

        Location::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => 'manual',
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Location created successfully.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        if ($location->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations')
                    ->where(fn ($q) => $q->where('user_id', $request->user()->id))
                    ->ignore($location->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'You already have a location with this name.',
        ]);

        $location->update($validated);

        return back()->with('success', 'Location updated successfully.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        if ($location->user_id !== $request->user()->id) {
            abort(403);
        }

        $pigeonCount = $location->pigeons()->count();
        $pairingCount = $location->pairings()->count();
        $clutchCount = $location->clutches()->count();

        if ($pigeonCount + $pairingCount + $clutchCount > 0) {
            return back()->withErrors([
                'location' => "Cannot delete '{$location->name}'. It is still assigned to {$pigeonCount} pigeon(s), {$pairingCount} pairing(s), and {$clutchCount} clutch record(s). Reassign them first.",
            ]);
        }

        $location->delete();

        return back()->with('success', 'Location deleted successfully.');
    }
}
