<?php

namespace App\Http\Controllers;

use App\Models\Pairing;
use App\Models\Pigeon;
use App\Http\Requests\StorePairingRequest;
use App\Http\Requests\UpdatePairingRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

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
        
        $query = Pairing::with(['sire', 'dam', 'offspring'])
            ->where('user_id', $user->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by pair name
        if ($request->filled('pair_name')) {
            $query->where('pair_name', $this->likeOperator(), '%' . $request->pair_name . '%');
        }

        // Search across sire and dam details
        if ($request->filled('search')) {
            $search = $request->search;
            $likeOp = $this->likeOperator();
            $query->whereHas('sire', function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, '%' . $search . '%')
                    ->orWhere('ring_number', $likeOp, '%' . $search . '%');
            })->orWhereHas('dam', function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, '%' . $search . '%')
                    ->orWhere('ring_number', $likeOp, '%' . $search . '%');
            });
        }

        // Sort by most recent first
        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 20);
        $pairings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('pairings/Index', [
            'pairings' => $pairings,
            'filters' => $request->only(['status', 'pair_name', 'search', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new pairing.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        // Get available sires (male pigeons not currently in active pairing)
        $sires = Pigeon::where('user_id', $user->id)
            ->where('gender', 'male')
            ->whereIn('status', ['alive'])
            ->whereDoesntHave('pairing', function ($query) {
                $query->where('status', 'active');
            })
            ->with([
                'sire:id,ring_number,name',
                'dam:id,ring_number,name'
            ])
            ->select('id', 'name', 'ring_number', 'bloodline', 'color', 'sire_id', 'dam_id', 'notes', 'remarks')
            ->orderBy('name')
            ->get();

        // Get available dams (female pigeons not currently in active pairing)
        $dams = Pigeon::where('user_id', $user->id)
            ->where('gender', 'female')
            ->whereIn('status', ['alive'])
            ->whereDoesntHave('pairing', function ($query) {
                $query->where('status', 'active');
            })
            ->with([
                'sire:id,ring_number,name',
                'dam:id,ring_number,name'
            ])
            ->select('id', 'name', 'ring_number', 'bloodline', 'color', 'sire_id', 'dam_id', 'notes', 'remarks')
            ->orderBy('name')
            ->get();

        return Inertia::render('pairings/Create', [
            'sires' => $sires,
            'dams' => $dams,
        ]);
    }

    /**
     * Store a newly created pairing.
     */
    public function store(StorePairingRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Check if either pigeon is already in an active pairing
        $sireInPairing = Pairing::where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->where('sire_id', $request->sire_id)
                    ->orWhere('dam_id', $request->sire_id);
            })
            ->exists();

        $damInPairing = Pairing::where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->where('sire_id', $request->dam_id)
                    ->orWhere('dam_id', $request->dam_id);
            })
            ->exists();

        if ($sireInPairing || $damInPairing) {
            return back()->withErrors([
                'sire_id' => 'One or both pigeons are already in an active pairing.',
            ]);
        }

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
            'started_at' => now(),
        ]);

        // Update pigeon statuses to 'breeding'
        Pigeon::whereIn('id', [$request->sire_id, $request->dam_id])
            ->update(['pigeon_status' => 'breeding']);

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
            'offspring' => function ($query) {
                $query->orderBy('hatch_date', 'desc')
                      ->orderBy('created_at', 'desc');
            }, 
            'clutches' => function ($query) {
                $query->with(['biologicalParents.sire', 'biologicalParents.dam'])
                      ->orderBy('clutch_number');
            },
            'fosterClutches' => function ($query) {
                $query->with(['pairing.sire', 'pairing.dam'])
                      ->orderBy('clutch_number');
            }
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
            'activePairings' => $activePairings,
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

        // Get available sires (include current sire even if in this pairing)
        $sires = Pigeon::where('user_id', $user->id)
            ->where('gender', 'male')
            ->whereIn('status', ['alive'])
            ->where(function ($query) use ($pairing) {
                $query->where('id', $pairing->sire_id)
                    ->orWhereDoesntHave('pairing', function ($q) {
                        $q->where('status', 'active');
                    });
            })
            ->select('id', 'name', 'ring_number', 'bloodline')
            ->orderBy('name')
            ->get();

        // Get available dams (include current dam even if in this pairing)
        $dams = Pigeon::where('user_id', $user->id)
            ->where('gender', 'female')
            ->whereIn('status', ['alive'])
            ->where(function ($query) use ($pairing) {
                $query->where('id', $pairing->dam_id)
                    ->orWhereDoesntHave('pairing', function ($q) {
                        $q->where('status', 'active');
                    });
            })
            ->select('id', 'name', 'ring_number', 'bloodline')
            ->orderBy('name')
            ->get();

        return Inertia::render('pairings/Edit', [
            'pairing' => $pairing,
            'sires' => $sires,
            'dams' => $dams,
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
            Pigeon::whereIn('id', [$pairing->sire_id, $pairing->dam_id])
                ->update(['pigeon_status' => 'stock']);
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

        if (!$pairing->isActive()) {
            return back()->withErrors(['status' => 'This pairing is already inactive.']);
        }

        // End the pairing
        $pairing->end();

        // Update pigeon statuses back to 'stock'
        Pigeon::whereIn('id', [$pairing->sire_id, $pairing->dam_id])
            ->update(['pigeon_status' => 'stock']);

        return redirect()->route('pairings.show', $pairing)
            ->with('success', 'Pairing session ended successfully.');
    }
}
