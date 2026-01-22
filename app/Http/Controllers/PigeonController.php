<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePigeonRequest;
use App\Http\Requests\UpdatePigeonRequest;
use App\Models\Pigeon;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PigeonController extends Controller
{
    /**
     * Get the appropriate case-insensitive LIKE operator for the current database.
     */
    private function likeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        
        $perPage = $request->input('per_page', 12);
        $perPage = in_array($perPage, [12, 21, 52, 104]) ? $perPage : 12;

        $query = Pigeon::query()
            ->with([
                'sire:id,ring_number,personal_number,color',
                'dam:id,ring_number,personal_number,color',
            ])
            ->where('user_id', $user->id);

        // Search functionality
        if ($search = $request->input('search')) {
            $likeOp = $this->likeOperator();
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, "%{$search}%")
                    ->orWhere('ring_number', $likeOp, "%{$search}%")
                    ->orWhere('personal_number', $likeOp, "%{$search}%")
                    ->orWhere('bloodline', $likeOp, "%{$search}%")
                    ->orWhere('color', $likeOp, "%{$search}%");
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

    public function store(StorePigeonRequest $request, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $data['photo_url'] = $imageService->upload(
                $request->file('photo'),
                'pigeons/photos',
                1200,
                85
            );
        }

        // Handle pedigree images upload
        if ($request->hasFile('pedigree_images')) {
            $data['pedigree_images'] = $imageService->uploadMultiple(
                $request->file('pedigree_images'),
                'pigeons/pedigree',
                1200,
                85
            );
        }

        $pigeon = Pigeon::create($data);

        // If creating offspring from a pairing, redirect back to the pairing page
        if ($request->input('pairing_id')) {
            return redirect()
                ->route('pairings.show', $request->input('pairing_id'))
                ->with('success', 'Offspring added successfully.');
        }

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

    public function update(UpdatePigeonRequest $request, Pigeon $pigeon, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($pigeon->photo_url) {
                $imageService->delete($pigeon->photo_url);
            }
            $data['photo_url'] = $imageService->upload(
                $request->file('photo'),
                'pigeons/photos',
                1200,
                85
            );
        }

        // Handle photo removal
        if ($request->input('remove_photo')) {
            if ($pigeon->photo_url) {
                $imageService->delete($pigeon->photo_url);
            }
            $data['photo_url'] = null;
        }

        // Handle pedigree images upload
        if ($request->hasFile('pedigree_images')) {
            $newImages = $imageService->uploadMultiple(
                $request->file('pedigree_images'),
                'pigeons/pedigree',
                1200,
                85
            );
            // Merge with existing pedigree images
            $existingImages = $pigeon->pedigree_images ?? [];
            $data['pedigree_images'] = array_merge($existingImages, $newImages);
        }

        // Handle pedigree image removal
        if ($request->input('remove_pedigree')) {
            $toRemove = $request->input('remove_pedigree');
            $imageService->deleteMultiple($toRemove);
            $existingImages = $pigeon->pedigree_images ?? [];
            $data['pedigree_images'] = array_values(array_diff($existingImages, $toRemove));
        }

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
            ->with([
                'sire:id,ring_number,name',
                'dam:id,ring_number,name'
            ])
            ->orderBy('name')
            ->orderBy('ring_number')
            ->get(['id', 'name', 'ring_number', 'personal_number', 'color', 'bloodline', 'sire_id', 'dam_id', 'notes', 'remarks'])
            ->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'color' => $pigeon->color,
                'bloodline' => $pigeon->bloodline,
                'sire' => $pigeon->sire ? [
                    'ring_number' => $pigeon->sire->ring_number,
                    'name' => $pigeon->sire->name,
                ] : null,
                'dam' => $pigeon->dam ? [
                    'ring_number' => $pigeon->dam->ring_number,
                    'name' => $pigeon->dam->name,
                ] : null,
                'notes' => $pigeon->notes,
                'remarks' => $pigeon->remarks,
                'label' => trim(implode(' ', array_filter([
                    $pigeon->name,
                    $pigeon->ring_number,
                    $pigeon->personal_number,
                    $pigeon->color,
                    $pigeon->bloodline,
                    $pigeon->sire ? "S:{$pigeon->sire->ring_number}" : null,
                    $pigeon->dam ? "D:{$pigeon->dam->ring_number}" : null,
                ]))),
            ]);

        $dams = Pigeon::query()
            ->where('user_id', $userId)
            ->where('gender', 'female')
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
            ->with([
                'sire:id,ring_number,name',
                'dam:id,ring_number,name'
            ])
            ->orderBy('name')
            ->orderBy('ring_number')
            ->get(['id', 'name', 'ring_number', 'personal_number', 'color', 'bloodline', 'sire_id', 'dam_id', 'notes', 'remarks'])
            ->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'color' => $pigeon->color,
                'bloodline' => $pigeon->bloodline,
                'sire' => $pigeon->sire ? [
                    'ring_number' => $pigeon->sire->ring_number,
                    'name' => $pigeon->sire->name,
                ] : null,
                'dam' => $pigeon->dam ? [
                    'ring_number' => $pigeon->dam->ring_number,
                    'name' => $pigeon->dam->name,
                ] : null,
                'notes' => $pigeon->notes,
                'remarks' => $pigeon->remarks,
                'label' => trim(implode(' ', array_filter([
                    $pigeon->name,
                    $pigeon->ring_number,
                    $pigeon->personal_number,
                    $pigeon->color,
                    $pigeon->bloodline,
                    $pigeon->sire ? "S:{$pigeon->sire->ring_number}" : null,
                    $pigeon->dam ? "D:{$pigeon->dam->ring_number}" : null,
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
