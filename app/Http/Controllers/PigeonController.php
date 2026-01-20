<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePigeonRequest;
use App\Http\Requests\UpdatePigeonRequest;
use App\Models\Pigeon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PigeonController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        $perPage = $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 10;

        $query = Pigeon::query()
            ->with([
                'sire:id,ring_number,personal_number,color',
                'dam:id,ring_number,personal_number,color',
            ])
            ->where('user_id', $user->id);

        // Search functionality
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ring_number', 'like', "%{$search}%")
                    ->orWhere('personal_number', 'like', "%{$search}%")
                    ->orWhere('bloodline', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by pigeon_status
        if ($pigeonStatus = $request->input('pigeon_status')) {
            $query->where('pigeon_status', $pigeonStatus);
        }

        // Filter by race_type
        if ($raceType = $request->input('race_type')) {
            $query->where('race_type', $raceType);
        }

        // Filter by bloodline
        if ($bloodline = $request->input('bloodline')) {
            $query->where('bloodline', $bloodline);
        }

        $pigeons = $query->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Pigeon $pigeon) => $this->transformPigeon($pigeon));

        // Get unique bloodlines for filter
        $bloodlines = Pigeon::where('user_id', $user->id)
            ->whereNotNull('bloodline')
            ->distinct()
            ->orderBy('bloodline')
            ->pluck('bloodline');

        // Get unique colors for filter
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        return Inertia::render('pigeons/Index', [
            'pigeons' => $pigeons,
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'pigeon_status' => $request->input('pigeon_status'),
                'race_type' => $request->input('race_type'),
                'bloodline' => $request->input('bloodline'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        
        // Get unique bloodlines and colors for autocomplete
        $bloodlines = Pigeon::where('user_id', $user->id)
            ->whereNotNull('bloodline')
            ->distinct()
            ->orderBy('bloodline')
            ->pluck('bloodline');
            
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        // Get pre-filled data from query params (from pairing)
        $prefill = $request->only(['sire_id', 'dam_id', 'pairing_id', 'clutch_id', 'hatch_date']);

        return Inertia::render('pigeons/Create', [
            'parentOptions' => $this->parentOptions($user->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'prefill' => $prefill,
        ]);
    }

    public function store(StorePigeonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['photos'] = $data['photos'] ?? [];

        $pigeon = Pigeon::create($data);

        return redirect()
            ->route('pigeons.index')
            ->with('success', 'Pigeon created successfully.');
    }

    public function show(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        $pigeon->load([
            'sire:id,ring_number,personal_number,color,sire_name,sire_ring_number,sire_color',
            'dam:id,ring_number,personal_number,color,dam_name,dam_ring_number,dam_color',
        ]);

        // Build pedigree tree for show page
        $pedigreeData = $this->buildPedigreeTree($pigeon, 5);

        return Inertia::render('pigeons/Show', [
            'pigeon' => $this->transformPigeon($pigeon),
            'pedigree' => $pedigreeData,
        ]);
    }

    public function edit(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);
        
        $user = $request->user();
        
        // Get unique bloodlines and colors for autocomplete
        $bloodlines = Pigeon::where('user_id', $user->id)
            ->whereNotNull('bloodline')
            ->distinct()
            ->orderBy('bloodline')
            ->pluck('bloodline');
            
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        return Inertia::render('pigeons/Edit', [
            'pigeon' => $this->transformPigeon($pigeon),
            'parentOptions' => $this->parentOptions($user->id, $pigeon->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
        ]);
    }

    public function update(UpdatePigeonRequest $request, Pigeon $pigeon): RedirectResponse
    {
        $data = $request->validated();
        $data['photos'] = $data['photos'] ?? [];

        $pigeon->update($data);

        return redirect()
            ->route('pigeons.edit', $pigeon)
            ->with('success', 'Pigeon updated successfully.');
    }

    public function destroy(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $pigeon->delete();

        return redirect()
            ->route('pigeons.index')
            ->with('success', 'Pigeon removed successfully.');
    }

    public function pedigree(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        // Recursively load pedigree up to 5 generations
        $pedigreeData = $this->buildPedigreeTree($pigeon, 5);

        return Inertia::render('pigeons/Pedigree', [
            'pigeon' => $this->transformPigeon($pigeon),
            'pedigree' => $pedigreeData,
        ]);
    }

    public function printPedigree(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        // Recursively load pedigree up to 5 generations
        $pedigreeData = $this->buildPedigreeTree($pigeon, 5);

        return Inertia::render('pigeons/PrintPedigree', [
            'pigeon' => $this->transformPigeon($pigeon),
            'pedigree' => $pedigreeData,
        ]);
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }

    private function transformPigeon(Pigeon $pigeon): array
    {
        return [
            'id' => $pigeon->id,
            'name' => $pigeon->name,
            'bloodline' => $pigeon->bloodline,
            'gender' => $pigeon->gender,
            'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
            'status' => $pigeon->status,
            'pigeon_status' => $pigeon->pigeon_status,
            'race_type' => $pigeon->race_type,
            'color' => $pigeon->color,
            'ring_number' => $pigeon->ring_number,
            'personal_number' => $pigeon->personal_number,
            'remarks' => $pigeon->remarks,
            'notes' => $pigeon->notes,
            'photos' => $pigeon->photos,
            'pedigree_image' => $pigeon->pedigree_image,
            'for_sale' => $pigeon->for_sale,
            'sale_price' => $pigeon->sale_price,
            'hide_price' => $pigeon->hide_price,
            'sale_description' => $pigeon->sale_description,
            'created_at' => $pigeon->created_at,
            'updated_at' => $pigeon->updated_at,
            'sire' => $pigeon->sire ? [
                'id' => $pigeon->sire->id,
                'name' => $pigeon->sire->name,
                'ring_number' => $pigeon->sire->ring_number,
                'personal_number' => $pigeon->sire->personal_number,
                'color' => $pigeon->sire->color,
            ] : null,
            'dam' => $pigeon->dam ? [
                'id' => $pigeon->dam->id,
                'name' => $pigeon->dam->name,
                'ring_number' => $pigeon->dam->ring_number,
                'personal_number' => $pigeon->dam->personal_number,
                'color' => $pigeon->dam->color,
            ] : null,
            'sire_name' => $pigeon->sire_name,
            'sire_ring_number' => $pigeon->sire_ring_number,
            'sire_color' => $pigeon->sire_color,
            'sire_notes' => $pigeon->sire_notes,
            'dam_name' => $pigeon->dam_name,
            'dam_ring_number' => $pigeon->dam_ring_number,
            'dam_color' => $pigeon->dam_color,
            'dam_notes' => $pigeon->dam_notes,
        ];
    }

    private function parentOptions(int $userId, ?int $excludeId = null)
    {
        $sires = Pigeon::query()
            ->where('user_id', $userId)
            ->where('gender', 'male')
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
            ->orderBy('name')
            ->orderBy('ring_number')
            ->get(['id', 'name', 'ring_number', 'personal_number', 'color'])
            ->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'color' => $pigeon->color,
                'label' => trim(implode(' ', array_filter([
                    $pigeon->name,
                    $pigeon->ring_number,
                    $pigeon->personal_number,
                    $pigeon->color,
                ]))),
            ]);

        $dams = Pigeon::query()
            ->where('user_id', $userId)
            ->where('gender', 'female')
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
            ->orderBy('name')
            ->orderBy('ring_number')
            ->get(['id', 'name', 'ring_number', 'personal_number', 'color'])
            ->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'color' => $pigeon->color,
                'label' => trim(implode(' ', array_filter([
                    $pigeon->name,
                    $pigeon->ring_number,
                    $pigeon->personal_number,
                    $pigeon->color,
                ]))),
            ]);

        return [
            'sires' => $sires,
            'dams' => $dams,
        ];
    }

    private function buildPedigreeTree(?Pigeon $pigeon, int $generations): ?array
    {
        if (!$pigeon || $generations <= 0) {
            return null;
        }

        $pigeon->load(['sire', 'dam']);

        return [
            'id' => $pigeon->id,
            'name' => $pigeon->name,
            'ring_number' => $pigeon->ring_number,
            'personal_number' => $pigeon->personal_number,
            'color' => $pigeon->color,
            'gender' => $pigeon->gender,
            'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
            'label' => $pigeon->name ?: $pigeon->ring_number ?: $pigeon->personal_number ?: "Pigeon #{$pigeon->id}",
            'sire' => $this->buildPedigreeTree($pigeon->sire, $generations - 1),
            'dam' => $this->buildPedigreeTree($pigeon->dam, $generations - 1),
            'sire_name' => $pigeon->sire_name,
            'sire_ring_number' => $pigeon->sire_ring_number,
            'sire_color' => $pigeon->sire_color,
            'dam_name' => $pigeon->dam_name,
            'dam_ring_number' => $pigeon->dam_ring_number,
            'dam_color' => $pigeon->dam_color,
        ];
    }
}
