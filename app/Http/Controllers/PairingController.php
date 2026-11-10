<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePairingRequest;
use App\Http\Requests\UpdatePairingRequest;
use App\Models\Location;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Services\Merits\GoldenPairs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PairingController extends Controller
{
    /**     * Get the appropriate case-insensitive LIKE operator for the current database.
     */
    private function likeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }

    /**     * Display a listing of pairings.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // The status, pair name and search filters; shared by the list and the counts on the location tabs
        $applyFilters = function ($query) use ($request) {
            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by pair name
            if ($request->filled('pair_name')) {
                $query->where('pair_name', $this->likeOperator(), '%'.$request->pair_name.'%');
            }

            // Search across sire and dam details
            if ($request->filled('search')) {
                $search = $request->search;
                $likeOp = $this->likeOperator();
                // Grouped so the OR stays inside the user's own pairings
                $query->where(function ($query) use ($search, $likeOp) {
                    $query->whereHas('sire', function ($q) use ($search, $likeOp) {
                        $q->where('name', $likeOp, '%'.$search.'%')
                            ->orWhere('ring_number', $likeOp, '%'.$search.'%');
                    })->orWhereHas('dam', function ($q) use ($search, $likeOp) {
                        $q->where('name', $likeOp, '%'.$search.'%')
                            ->orWhere('ring_number', $likeOp, '%'.$search.'%');
                    });
                });
            }
        };

        $query = Pairing::with(['sire', 'dam', 'offspring', 'breedingLocation:id,name'])
            ->where('user_id', $user->id);
        $applyFilters($query);

        // One tab per breeding location, each with its number of pairs under the current filters
        $breedingLocations = Location::where('user_id', $user->id)
            ->where('is_breeding', true)
            ->select('id', 'name')
            ->withCount(['pairings' => $applyFilters])
            ->orderBy('name')
            ->get();
        $allCount = (clone $query)->count();

        // The tab: pairs bred at one location
        $location = ctype_digit((string) $request->query('location')) ? (int) $request->query('location') : null;
        if ($location) {
            $query->where('breeding_location_id', $location);
        }

        // Sort by pair name
        $query->orderBy('pair_name', 'asc');

        // Only the page sizes the page offers, so one request can't pull every pairing at once
        $perPage = in_array($request->integer('per_page'), [10, 20, 50, 100], true) ? $request->integer('per_page') : 20;
        $pairings = $query->paginate($perPage)->withQueryString();

        // Golden Pair status of each pair on this page
        $golden = app(GoldenPairs::class)->forPairs($user->id, $pairings->getCollection()->map(fn (Pairing $pairing) => [$pairing->sire_id, $pairing->dam_id]));
        $pairings->getCollection()->each(fn (Pairing $pairing) => $pairing->setAttribute(
            'golden_pair',
            $golden[GoldenPairs::key((int) $pairing->sire_id, (int) $pairing->dam_id)] ?? null,
        ));

        return Inertia::render('pairings/Index', [
            'pairings' => $pairings,
            'breedingLocations' => $breedingLocations,
            'allCount' => $allCount,
            'filters' => [...$request->only(['status', 'pair_name', 'search', 'per_page']), 'location' => $location],
        ]);
    }

    /**
     * Show the form for creating a new pairing.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        // Available sires and dams, in the standard pigeon search format (see CLAUDE.md)
        $breeders = fn (string $gender) => Pigeon::where('user_id', $user->id)
            ->where('gender', $gender)
            ->inLoft()
            ->with(Pigeon::SEARCH_RELATIONS)
            ->orderBy('name')
            ->get(Pigeon::SEARCH_COLUMNS)
            ->map->searchSummary();

        $locations = Location::where('user_id', $user->id)
            ->orderByDesc('is_breeding')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_breeding']);

        // The breeding location tab the pair is added from; with no tab, the only breeding location if there is one
        $breedingLocations = $locations->where('is_breeding', true);
        $locationId = $locations->firstWhere('id', $request->integer('location'))?->id
            ?? ($breedingLocations->count() === 1 ? $breedingLocations->first()->id : null);

        return Inertia::render('pairings/Create', [
            'sires' => $breeders('male'),
            'dams' => $breeders('female'),
            'locations' => $locations,
            // Pre-selected birds, e.g. from Pairing Analysis, and location
            'prefill' => [
                'sire_id' => $request->integer('sire_id') ?: null,
                'dam_id' => $request->integer('dam_id') ?: null,
                'breeding_location_id' => $locationId,
            ],
        ]);
    }

    /**
     * Store a newly created pairing.
     */
    public function store(StorePairingRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Check for previous pairings of the same pigeons to determine clutch number
        $previousPairings = Pairing::where('user_id', $user->id)
            ->where('sire_id', $request->sire_id)
            ->where('dam_id', $request->dam_id)
            ->count();

        $clutchNumber = $previousPairings + 1;

        // Create the pairing
        $pairing = Pairing::create([
            'user_id' => $user->id,
            'sire_id' => $request->sire_id,
            'dam_id' => $request->dam_id,
            'pair_name' => $request->pair_name ?? "Pair #{$clutchNumber}",
            'status' => 'active',
            'current_clutch_number' => $clutchNumber,
            'breeding_location_id' => $request->breeding_location_id ?: null,
            'started_at' => now(),
        ]);

        // Update pigeon statuses to 'breeding' and move to breeding location
        $pigeonUpdate = ['status' => 'breeding'];
        if ($request->breeding_location_id) {
            $pigeonUpdate['location_id'] = $request->breeding_location_id;
        }
        Pigeon::where('user_id', $user->id)
            ->whereIn('id', [$request->sire_id, $request->dam_id])
            ->update($pigeonUpdate);

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Pairing created successfully.');
    }

    /**
     * Display the specified pairing.
     */
    public function show(Request $request, Pairing $pairing): Response
    {
        // Ensure user owns this pairing
        if ($pairing->user_id !== $request->user()->id) {
            abort(403);
        }

        $pairing->load([
            'sire',
            'dam',
            'breedingLocation',
            'offspring' => function ($query) {
                $query->with('location:id,name,type')
                    ->orderBy('hatch_date', 'desc')
                    ->orderBy('created_at', 'desc');
            },
            'clutches' => function ($query) {
                $query->with(['biologicalParents.sire', 'biologicalParents.dam', 'successLocation:id,name'])
                    ->orderBy('clutch_number');
            },
            'fosterClutches' => function ($query) {
                $query->with(['pairing.sire', 'pairing.dam'])
                    ->orderBy('clutch_number');
            },
        ]);

        // Get all user's active pairings for foster selection
        $activePairings = Pairing::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('id', '!=', $pairing->id)
            ->with(['sire', 'dam'])
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'pair_name' => $p->pair_name,
                    'sire_name' => $p->sire->name ?? $p->sire->ring_number,
                    'dam_name' => $p->dam->name ?? $p->dam->ring_number,
                ];
            });

        return Inertia::render('pairings/Show', [
            'pairing' => $pairing,
            'goldenPair' => app(GoldenPairs::class)->forPair($request->user()->id, $pairing->sire_id, $pairing->dam_id),
            'activePairings' => $activePairings,
            'locations' => Location::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    /**
     * Show the form for editing the specified pairing.
     */
    public function edit(Request $request, Pairing $pairing): Response
    {
        // Ensure user owns this pairing
        if ($pairing->user_id !== $request->user()->id) {
            abort(403);
        }

        $user = $request->user();

        // Birds in the loft, plus this pair's own sire and dam even if they have since gone
        $sires = Pigeon::where('user_id', $user->id)
            ->where('gender', 'male')
            ->where(fn ($q) => $q->inLoft()->orWhere('id', $pairing->sire_id))
            ->select('id', 'name', 'ring_number', 'bloodline')
            ->orderBy('name')
            ->get();

        $dams = Pigeon::where('user_id', $user->id)
            ->where('gender', 'female')
            ->where(fn ($q) => $q->inLoft()->orWhere('id', $pairing->dam_id))
            ->select('id', 'name', 'ring_number', 'bloodline')
            ->orderBy('name')
            ->get();

        return Inertia::render('pairings/Edit', [
            'pairing' => $pairing,
            'sires' => $sires,
            'dams' => $dams,
            'locations' => Location::where('user_id', $user->id)
                ->orderByDesc('is_breeding')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'is_breeding']),
        ]);
    }

    /**
     * Update the specified pairing.
     */
    public function update(UpdatePairingRequest $request, Pairing $pairing): RedirectResponse
    {
        // Ensure user owns this pairing
        if ($pairing->user_id !== $request->user()->id) {
            abort(403);
        }

        $pairing->update($request->validated());

        // An active pair moved to another breeding location takes its sire and dam with it
        if ($pairing->isActive() && $pairing->breeding_location_id && $pairing->wasChanged('breeding_location_id')) {
            Pigeon::where('user_id', $pairing->user_id)
                ->whereIn('id', [$pairing->sire_id, $pairing->dam_id])
                ->update(['location_id' => $pairing->breeding_location_id]);
        }

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Pairing updated successfully.');
    }

    /**
     * Remove the specified pairing.
     */
    public function destroy(Request $request, Pairing $pairing): RedirectResponse
    {
        // Ensure user owns this pairing
        if ($pairing->user_id !== $request->user()->id) {
            abort(403);
        }

        // If active, update pigeon statuses back to 'stock'
        if ($pairing->isActive()) {
            Pigeon::where('user_id', $pairing->user_id)
                ->whereIn('id', [$pairing->sire_id, $pairing->dam_id])
                ->update(['status' => 'stock']);
        }

        $pairing->delete();

        return redirect()->route('pairings.index')
            ->with('success', 'Pairing deleted successfully.');
    }

    /**
     * End an active pairing session.
     */
    public function endSession(Request $request, Pairing $pairing): RedirectResponse
    {
        // Ensure user owns this pairing
        if ($pairing->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $pairing->isActive()) {
            return back()->withErrors(['status' => 'This pairing is already inactive.']);
        }

        // End the pairing
        $pairing->end();

        // Update pigeon statuses back to 'stock'
        Pigeon::where('user_id', $pairing->user_id)
            ->whereIn('id', [$pairing->sire_id, $pairing->dam_id])
            ->update(['status' => 'stock']);

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Pairing session ended successfully.');
    }
}
