<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Pigeon;
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

    /**
     * The birds at one location, where birds can be added (moved here from wherever they are) or removed.
     */
    public function show(Request $request, Location $location): Response
    {
        $this->authorizeOwner($request, $location);

        $user = $request->user();

        // Every bird assigned here, gone ones too, so they can be cleared before the location is deleted
        $pigeons = $location->pigeons()
            ->with(Pigeon::SEARCH_RELATIONS)
            ->orderBy('ring_number')
            ->orderBy('name')
            ->get([...Pigeon::SEARCH_COLUMNS, 'status'])
            ->map(fn (Pigeon $pigeon) => [...$pigeon->searchSummary(), 'status' => $pigeon->status]);

        return Inertia::render('locations/Show', [
            'location' => $location->only(['id', 'name', 'type', 'is_breeding', 'description', 'olr_race_id']),
            'pigeons' => $pigeons,
            // Birds in the loft that are not here yet, for the add picker. Loaded only when the picker opens.
            'candidates' => Inertia::optional(fn () => Pigeon::where('user_id', $user->id)
                ->inLoft()
                ->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', '!=', $location->id))
                ->with([...Pigeon::SEARCH_RELATIONS, 'location:id,name'])
                ->orderBy('ring_number')
                ->orderBy('name')
                ->get([...Pigeon::SEARCH_COLUMNS, 'status', 'location_id'])
                ->map(fn (Pigeon $pigeon) => [
                    ...$pigeon->searchSummary(),
                    'status' => $pigeon->status,
                    'location_id' => $pigeon->location_id,
                    'location_name' => $pigeon->location?->name,
                ])),
            // The other locations, to narrow the picker to the birds at one of them
            'otherLocations' => Location::where('user_id', $user->id)
                ->whereKeyNot($location->id)
                ->orderBy('name')
                ->get(['id', 'name']),
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
            'is_breeding' => ['boolean'],
        ], [
            'name.unique' => 'You already have a location with this name.',
        ]);

        Location::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => 'manual',
            'is_breeding' => $validated['is_breeding'] ?? false,
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Location created successfully.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeOwner($request, $location);

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
            'is_breeding' => ['boolean'],
        ], [
            'name.unique' => 'You already have a location with this name.',
        ]);

        // One-loft race locations hold race entries, not breeding pairs
        if ($location->isOlr()) {
            unset($validated['is_breeding']);
        }

        $location->update($validated);

        return back()->with('success', 'Location updated successfully.');
    }

    /**
     * Put birds at this location. A bird at another location is moved here.
     */
    public function addPigeons(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeOwner($request, $location);

        $userId = $request->user()->id;

        $validated = $request->validate([
            'pigeon_ids' => ['required', 'array', 'min:1'],
            'pigeon_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['pigeon_ids'])));

        if (Pigeon::where('user_id', $userId)->whereIn('id', $ids)->count() !== count($ids)) {
            return back()->withErrors(['pigeon_ids' => 'Some of these pigeons are not yours.']);
        }

        $arriving = Pigeon::where('user_id', $userId)
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', '!=', $location->id));

        $transferred = (clone $arriving)->whereNotNull('location_id')->count();
        $added = $arriving->update(['location_id' => $location->id]);

        $message = "Added {$added} pigeon".($added === 1 ? '' : 's')." to {$location->name}.";
        if ($transferred > 0) {
            $message .= " {$transferred} moved from another location.";
        }

        return back()->with('success', $message);
    }

    /**
     * Take birds off this location, leaving them with no location.
     */
    public function removePigeons(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeOwner($request, $location);

        $validated = $request->validate([
            'pigeon_ids' => ['required', 'array', 'min:1'],
            'pigeon_ids.*' => ['integer'],
        ]);

        $removed = Pigeon::where('user_id', $request->user()->id)
            ->where('location_id', $location->id)
            ->whereIn('id', $validated['pigeon_ids'])
            ->update(['location_id' => null]);

        return back()->with('success', "Removed {$removed} pigeon".($removed === 1 ? '' : 's')." from {$location->name}.");
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeOwner($request, $location);

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

    private function authorizeOwner(Request $request, Location $location): void
    {
        if ($location->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
