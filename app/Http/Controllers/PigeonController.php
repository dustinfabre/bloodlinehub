<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePigeonRequest;
use App\Http\Requests\UpdatePigeonRequest;
use App\Models\Bloodline;
use App\Models\ColorTag;
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
                'bloodlines',
                'colorTag:id,name,color',
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
                    ->orWhere('color', $likeOp, "%{$search}%")
                    // Also search in the new bloodlines relationship
                    ->orWhereHas('bloodlines', function ($bq) use ($search, $likeOp) {
                        $bq->where('name', $likeOp, "%{$search}%");
                    });
            });
        }

        // Filter by gender
        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }

        // Filter by status (can be multiple)
        if ($status = $request->input('status')) {
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        // Filter by bloodline (supports new bloodline IDs)
        if ($bloodline = $request->input('bloodline')) {
            if (is_numeric($bloodline)) {
                // Filter by bloodline ID (new system)
                $query->whereHas('bloodlines', fn ($q) => $q->where('bloodlines.id', $bloodline));
            } else {
                // Filter by legacy bloodline string for backward compatibility
                $query->where('bloodline', $bloodline);
            }
        }

        $pigeons = $query->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Pigeon $pigeon) => $this->transformPigeon($pigeon));

        // Get bloodlines from the bloodlines table for filter
        $bloodlines = Bloodline::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get unique colors for filter
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        // Get color tags for the user
        $colorTags = ColorTag::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('pigeons/Index', [
            'pigeons' => $pigeons,
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'colorTags' => $colorTags,
            'filters' => [
                'search' => $request->input('search'),
                'gender' => $request->input('gender'),
                'status' => $request->input('status'),
                'bloodline' => $request->input('bloodline'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        
        // Get unique bloodlines from the bloodlines table for the user
        $bloodlines = Bloodline::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        // Get color tags for the user
        $colorTags = ColorTag::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        // Get pre-filled data from query params (from pairing)
        $prefill = $request->only(['sire_id', 'dam_id', 'pairing_id', 'clutch_id', 'hatch_date']);

        return Inertia::render('pigeons/Create', [
            'parentOptions' => $this->parentOptions($user->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'colorTags' => $colorTags,
            'prefill' => $prefill,
        ]);
    }

    public function store(StorePigeonRequest $request, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Extract bloodlines data before creating pigeon
        $bloodlinesData = $request->input('bloodlines', []);
        unset($data['bloodlines']);

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
        \Log::info('Creating pigeon with data: ', $data);

        $pigeon = Pigeon::create($data);

        // Sync bloodlines with pivot data
        $this->syncBloodlines($pigeon, $bloodlinesData, $request->user()->id);

        \Log::info('Created pigeon: ', $pigeon->toArray());

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
        
        // Load pigeon with bloodlines relationship
        $pigeon->load('bloodlines');
        
        // Get unique bloodlines from the bloodlines table for the user
        $bloodlines = Bloodline::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        $colors = Pigeon::where('user_id', $user->id)
            ->whereNotNull('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');

        $colorTags = ColorTag::where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('pigeons/Edit', [
            'pigeon' => $this->transformPigeon($pigeon),
            'parentOptions' => $this->parentOptions($user->id, $pigeon->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'colorTags' => $colorTags,
        ]);
    }

    public function update(UpdatePigeonRequest $request, Pigeon $pigeon, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();

        // Extract bloodlines data before updating pigeon
        $bloodlinesData = $request->input('bloodlines', []);
        unset($data['bloodlines']);

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

        // Sync bloodlines with pivot data
        $this->syncBloodlines($pigeon, $bloodlinesData, $request->user()->id);

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
        // Transform bloodlines with pivot data
        $bloodlinesArray = $pigeon->relationLoaded('bloodlines') 
            ? $pigeon->bloodlines->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) $b->pivot->is_primary,
            ])->toArray() 
            : [];

        // Get primary bloodline name for display (prefer new system, fallback to legacy column)
        $primaryBloodline = collect($bloodlinesArray)->firstWhere('is_primary', true);
        $primaryBloodlineName = $primaryBloodline['name'] ?? $pigeon->bloodline;

        return [
            'id' => $pigeon->id,
            'name' => $pigeon->name,
            'bloodline' => $primaryBloodlineName, // For backward compatibility
            'bloodlines' => $bloodlinesArray, // New multi-bloodline array
            'gender' => $pigeon->gender,
            'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
            'status' => $pigeon->status,
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
            'color_tag_id' => $pigeon->color_tag_id,
            'color_tag' => $pigeon->relationLoaded('colorTag') && $pigeon->colorTag ? [
                'id' => $pigeon->colorTag->id,
                'name' => $pigeon->colorTag->name,
                'color' => $pigeon->colorTag->color,
            ] : null,
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

    /**
     * Check for ring number duplicates (exact and similar matches).
     */
    public function checkRingNumber(Request $request)
    {
        $user = $request->user();
        $ringNumber = strtoupper(trim($request->input('ring_number', '')));
        $excludeId = $request->input('exclude_id');

        if (empty($ringNumber)) {
            return response()->json([
                'exact_match' => null,
                'similar_matches' => [],
            ]);
        }

        // Check for exact match
        $exactMatch = Pigeon::where('user_id', $user->id)
            ->whereRaw('UPPER(ring_number) = ?', [$ringNumber])
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->first(['id', 'ring_number', 'personal_number', 'name', 'gender', 'status', 'bloodline', 'photo_url']);

        // Normalize for fuzzy matching - remove spaces, dashes, slashes
        $normalized = preg_replace('/[\s\-\/]/', '', $ringNumber);
        
        // Extract numeric part for partial matching
        preg_match('/\d+/', $normalized, $numericMatches);
        $numericPart = $numericMatches[0] ?? null;

        // Find similar matches using different strategies
        $similarQuery = Pigeon::where('user_id', $user->id)
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId));

        if ($exactMatch) {
            $similarQuery->where('id', '<>', $exactMatch->id);
        }

        // Build the similar matching query
        $similarMatches = $similarQuery->where(function ($query) use ($normalized, $numericPart, $ringNumber) {
            // Strategy 1: Normalized ring contains our normalized input (or vice versa)
            $query->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(ring_number), ' ', ''), '-', ''), '/', '') LIKE ?", ["%{$normalized}%"]);
            
            // Strategy 2: Our normalized input contains their normalized ring
            $query->orWhereRaw("? LIKE CONCAT('%', REPLACE(REPLACE(REPLACE(UPPER(ring_number), ' ', ''), '-', ''), '/', ''), '%')", [$normalized]);

            // Strategy 3: If we have a numeric part, check if it matches
            if ($numericPart && strlen($numericPart) >= 4) {
                $query->orWhereRaw("ring_number LIKE ?", ["%{$numericPart}%"]);
            }
        })
        ->limit(5)
        ->get(['id', 'ring_number', 'personal_number', 'name', 'gender', 'status', 'bloodline', 'photo_url']);

        // Calculate similarity scores and filter
        $similarWithScores = $similarMatches->map(function ($pigeon) use ($normalized) {
            $pigeonNormalized = preg_replace('/[\s\-\/]/', '', strtoupper($pigeon->ring_number));
            
            // Calculate Levenshtein similarity
            $maxLen = max(strlen($normalized), strlen($pigeonNormalized));
            $distance = levenshtein($normalized, $pigeonNormalized);
            $similarity = $maxLen > 0 ? (1 - ($distance / $maxLen)) * 100 : 0;

            // Boost score if one contains the other
            if (str_contains($pigeonNormalized, $normalized) || str_contains($normalized, $pigeonNormalized)) {
                $similarity = max($similarity, 70);
            }

            return [
                'pigeon' => [
                    'id' => $pigeon->id,
                    'ring_number' => $pigeon->ring_number,
                    'personal_number' => $pigeon->personal_number,
                    'name' => $pigeon->name,
                    'gender' => $pigeon->gender,
                    'status' => $pigeon->status,
                    'bloodline' => $pigeon->bloodline,
                    'photo' => $pigeon->photo_url,
                ],
                'similarity' => round($similarity),
            ];
        })
        ->filter(fn ($item) => $item['similarity'] >= 50)
        ->sortByDesc('similarity')
        ->values()
        ->take(3);

        return response()->json([
            'exact_match' => $exactMatch ? [
                'id' => $exactMatch->id,
                'ring_number' => $exactMatch->ring_number,
                'personal_number' => $exactMatch->personal_number,
                'name' => $exactMatch->name,
                'gender' => $exactMatch->gender,
                'status' => $exactMatch->status,
                'bloodline' => $exactMatch->bloodline,
                'photo' => $exactMatch->photo_url,
            ] : null,
            'similar_matches' => $similarWithScores->toArray(),
        ]);
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

    /**
     * Sync bloodlines for a pigeon.
     * 
     * @param Pigeon $pigeon
     * @param array $bloodlinesData Array of bloodline objects with id and is_primary
     * @param int $userId User ID for creating new bloodlines
     */
    private function syncBloodlines(Pigeon $pigeon, array $bloodlinesData, int $userId): void
    {
        if (empty($bloodlinesData)) {
            $pigeon->bloodlines()->detach();
            // Keep the legacy bloodline column as null
            $pigeon->update(['bloodline' => null]);
            return;
        }

        $syncData = [];
        $primaryBloodlineName = null;

        foreach ($bloodlinesData as $item) {
            // Item can be: { id: number, is_primary: bool } or { name: string, is_primary: bool }
            if (isset($item['id'])) {
                $bloodlineId = $item['id'];
            } elseif (isset($item['name'])) {
                // Create or find bloodline by name
                $bloodline = Bloodline::firstOrCreate(
                    ['user_id' => $userId, 'name' => strtoupper($item['name'])],
                );
                $bloodlineId = $bloodline->id;
            } else {
                continue;
            }

            $isPrimary = !empty($item['is_primary']);
            $syncData[$bloodlineId] = ['is_primary' => $isPrimary];

            if ($isPrimary) {
                $bloodline = Bloodline::find($bloodlineId);
                $primaryBloodlineName = $bloodline?->name;
            }
        }

        $pigeon->bloodlines()->sync($syncData);

        // Update legacy bloodline column with primary bloodline name for backward compatibility
        $pigeon->update(['bloodline' => $primaryBloodlineName]);
    }
}
