<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Http\Requests\StorePigeonRequest;
use App\Http\Requests\UpdatePigeonRequest;
use App\Models\Bloodline;
use App\Models\ColorTag;
use App\Models\Location;
use App\Models\Pigeon;
use App\Services\ImageUploadService;
use App\Services\Merits\GoldenPairs;
use App\Services\Merits\MeritSuggester;
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
        
        // Multiples of 12 so the 2- and 3-column card grids always fill complete rows
        $perPage = (int) $request->input('per_page', 12);
        $perPage = in_array($perPage, [12, 24, 48, 96], true) ? $perPage : 12;

        $query = Pigeon::query()
            ->with([
                'sire:id,ring_number,personal_number,color',
                'dam:id,ring_number,personal_number,color',
                'bloodlines',
                'colorTag:id,name,color',
                'location:id,name,type',
                'merits',
            ])
            ->where('user_id', $user->id);

        $statuses = array_values(array_intersect($this->arrayInput($request, 'status'), Pigeon::STATUSES));
        $bloodlineFilter = $this->arrayInput($request, 'bloodline');
        $locationFilter = $this->arrayInput($request, 'location');
        $meritFilter = array_values(array_intersect(
            $this->arrayInput($request, 'merit'),
            array_column(MeritType::cases(), 'value'),
        ));
        $gender = in_array($request->input('gender'), ['male', 'female'], true) ? $request->input('gender') : null;

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

        if ($gender) {
            $query->where('gender', $gender);
        }

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        // Bloodlines match if the bird carries ANY of the selected lines (primary or not).
        // Non-numeric values are legacy bloodline strings kept for old bookmarked URLs.
        if ($bloodlineFilter) {
            $ids = array_filter($bloodlineFilter, 'is_numeric');
            $names = array_diff($bloodlineFilter, $ids);
            $query->where(function ($q) use ($ids, $names) {
                if ($ids) {
                    $q->whereHas('bloodlines', fn ($bq) => $bq->whereIn('bloodlines.id', $ids));
                }
                if ($names) {
                    $q->orWhereIn('bloodline', $names);
                }
            });
        }

        // "none" matches birds without a location
        if ($locationFilter) {
            $ids = array_filter($locationFilter, 'is_numeric');
            $query->where(function ($q) use ($ids, $locationFilter) {
                if ($ids) {
                    $q->whereIn('location_id', $ids);
                }
                if (in_array('none', $locationFilter, true)) {
                    $q->orWhereNull('location_id');
                }
            });
        }

        if ($meritFilter) {
            $query->whereHas('merits', fn ($q) => $q->whereIn('type', $meritFilter));
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
            'locations' => Location::where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'meritTypes' => MeritType::options(),
            'colors' => $colors,
            'colorTags' => $colorTags,
            'parentOptions' => $this->parentOptions($user->id),
            'filters' => [
                'search' => $request->input('search'),
                'gender' => $gender,
                'status' => $statuses,
                'bloodline' => array_values($bloodlineFilter),
                'location' => array_values($locationFilter),
                'merit' => $meritFilter,
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

        $locations = Location::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('pigeons/Create', [
            'parentOptions' => $this->parentOptions($user->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'colorTags' => $colorTags,
            'locations' => $locations,
            'prefill' => $prefill,
        ]);
    }

    public function store(StorePigeonRequest $request, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Extract bloodlines data before creating pigeon
        $bloodlinesData = (array) $request->input('bloodlines', []);
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

        $pigeon = Pigeon::create($data);

        // Sync bloodlines with pivot data
        $this->syncBloodlines($pigeon, $bloodlinesData, $request->user()->id);

        // "Save & add ancestors" continues straight to the pedigree editor
        if ($request->boolean('edit_pedigree')) {
            return redirect()
                ->route('pigeons.editPedigree', $pigeon)
                ->with('success', 'Pigeon created. Now add its ancestors.');
        }

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
            'sire.colorTag:id,name,color',
            'dam.colorTag:id,name,color',
            'colorTag:id,name,color',
            'sireColorTag:id,name,color',
            'damColorTag:id,name,color',
            'pedigree',
            'merits',
            'location:id,name,type',
        ]);

        // Offspring are birds in the loft that have this pigeon linked as their sire or dam
        $children = Pigeon::query()
            ->where('user_id', $pigeon->user_id)
            ->where(fn ($query) => $query->where('sire_id', $pigeon->id)->orWhere('dam_id', $pigeon->id))
            ->with(['sire:id,name,ring_number,personal_number', 'dam:id,name,ring_number,personal_number'])
            ->orderByRaw('hatch_date IS NULL')
            ->orderByDesc('hatch_date')
            ->orderBy('ring_number')
            ->get()
            ->map(fn (Pigeon $child) => $this->childSummary($child, $pigeon));

        return Inertia::render('pigeons/Show', [
            'pigeon' => $this->transformPigeon($pigeon),
            'children' => $children,
            'meritTypes' => MeritType::options(),
            'meritLevels' => MeritLevel::options(),
            'meritSuggestions' => app(MeritSuggester::class)->forPigeons($pigeon->user_id, [$pigeon->id])[$pigeon->id] ?? [],
            // Whether this bird comes from a Golden Pair (its parents' youngsters include several winners)
            'parentPair' => $pigeon->sire_id && $pigeon->dam_id
                ? app(GoldenPairs::class)->forPair($pigeon->user_id, $pigeon->sire_id, $pigeon->dam_id)
                : null,
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

        $locations = Location::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('pigeons/Edit', [
            'pigeon' => $this->transformPigeon($pigeon),
            'parentOptions' => $this->parentOptions($user->id, $pigeon->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'colorTags' => $colorTags,
            'locations' => $locations,
        ]);
    }

    public function update(UpdatePigeonRequest $request, Pigeon $pigeon, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();

        // Extract bloodlines data before updating pigeon
        $bloodlinesData = (array) $request->input('bloodlines', []);
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
            // Only this pigeon's own images: any other URL would delete someone else's file from the shared bucket
            // Keep images uploaded in this same request
            $existingImages = $request->hasFile('pedigree_images') ? $data['pedigree_images'] : ($pigeon->pedigree_images ?? []);
            $toRemove = array_values(array_intersect((array) $request->input('remove_pedigree'), $pigeon->pedigree_images ?? []));
            if ($toRemove) {
                $imageService->deleteMultiple($toRemove);
            }
            $data['pedigree_images'] = array_values(array_diff($existingImages, $toRemove));
        }

        $pigeon->update($data);

        // Sync bloodlines with pivot data
        $this->syncBloodlines($pigeon, $bloodlinesData, $request->user()->id);

        if ($request->boolean('edit_pedigree')) {
            return redirect()
                ->route('pigeons.editPedigree', $pigeon)
                ->with('success', 'Pigeon updated successfully.');
        }

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

    /**
     * A query parameter that may be sent as a single value or an array (status=x or status[]=x&status[]=y).
     *
     * @return list<string>
     */
    private function arrayInput(Request $request, string $key): array
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('strval', array_filter((array) $value, 'is_scalar')),
            fn (string $item) => $item !== '',
        )));
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
            'photo_url' => $pigeon->photo_url,
            'pedigree_images' => $pigeon->pedigree_images,
            'for_sale' => $pigeon->for_sale,
            'sale_price' => $pigeon->sale_price,
            'hide_price' => $pigeon->hide_price,
            'sale_description' => $pigeon->sale_description,
            'created_at' => $pigeon->created_at,
            'updated_at' => $pigeon->updated_at,
            'sire' => $this->parentSummary($pigeon->sire),
            'dam' => $this->parentSummary($pigeon->dam),
            'sire_name' => $pigeon->sire_name,
            'sire_ring_number' => $pigeon->sire_ring_number,
            'sire_color' => $pigeon->sire_color,
            'sire_notes' => $pigeon->sire_notes,
            'sire_color_tag_id' => $pigeon->sire_color_tag_id,
            'sire_color_tag' => $this->colorTagSummary($pigeon, 'sireColorTag'),
            'dam_name' => $pigeon->dam_name,
            'dam_ring_number' => $pigeon->dam_ring_number,
            'dam_color' => $pigeon->dam_color,
            'dam_notes' => $pigeon->dam_notes,
            'dam_color_tag_id' => $pigeon->dam_color_tag_id,
            'dam_color_tag' => $this->colorTagSummary($pigeon, 'damColorTag'),
            'color_tag_id' => $pigeon->color_tag_id,
            'color_tag' => $this->colorTagSummary($pigeon, 'colorTag'),
            'pedigree' => $pigeon->relationLoaded('pedigree') && $pigeon->pedigree ? [
                'serial' => $pigeon->pedigree->serial,
                'is_public' => $pigeon->pedigree->is_public,
                'visibility' => $pigeon->pedigree->visibility(),
            ] : null,
            'merits' => $pigeon->relationLoaded('merits')
                ? $pigeon->merits->map->summary()->values()->all()
                : [],
            'location_id' => $pigeon->location_id,
            'location' => $pigeon->relationLoaded('location') && $pigeon->location ? [
                'id' => $pigeon->location->id,
                'name' => $pigeon->location->name,
                'type' => $pigeon->location->type,
            ] : null,
        ];
    }

    private function parentSummary(?Pigeon $parent): ?array
    {
        if (! $parent) {
            return null;
        }

        return [
            'id' => $parent->id,
            'name' => $parent->name,
            'ring_number' => $parent->ring_number,
            'personal_number' => $parent->personal_number,
            'color' => $parent->color,
            'remarks' => $parent->remarks,
            'notes' => $parent->notes,
            'color_tag' => $this->colorTagSummary($parent, 'colorTag'),
        ];
    }

    private function childSummary(Pigeon $child, Pigeon $parent): array
    {
        // The mate is whichever parent of the child is not the pigeon being viewed
        $mateSide = $child->sire_id === $parent->id ? 'dam' : 'sire';
        $mate = $child->{$mateSide};

        if ($mate) {
            $mateSummary = [
                'side' => $mateSide,
                'id' => $mate->id,
                'name' => $mate->name,
                'ring_number' => $mate->ring_number,
                'personal_number' => $mate->personal_number,
            ];
        } elseif ($child->{"{$mateSide}_name"} || $child->{"{$mateSide}_ring_number"}) {
            $mateSummary = [
                'side' => $mateSide,
                'id' => null,
                'name' => $child->{"{$mateSide}_name"},
                'ring_number' => $child->{"{$mateSide}_ring_number"},
                'personal_number' => null,
            ];
        } else {
            $mateSummary = null;
        }

        return [
            'id' => $child->id,
            'name' => $child->name,
            'ring_number' => $child->ring_number,
            'personal_number' => $child->personal_number,
            'gender' => $child->gender,
            'hatch_date' => $child->hatch_date?->format('Y-m-d'),
            'status' => $child->status,
            'color' => $child->color,
            'mate' => $mateSummary,
        ];
    }

    private function colorTagSummary(Pigeon $pigeon, string $relation): ?array
    {
        $tag = $pigeon->relationLoaded($relation) ? $pigeon->getRelation($relation) : null;

        return $tag ? [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ] : null;
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

    public function suggestRingNumber(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $prefix = strtoupper(trim($request->input('prefix', '')));

        if (strlen($prefix) < 2) {
            return response()->json(['suggestions' => []]);
        }

        // Find ring numbers starting with this prefix
        $existing = Pigeon::where('user_id', $user->id)
            ->whereRaw('UPPER(ring_number) LIKE ?', [$prefix . '%'])
            ->orderByRaw('ring_number DESC')
            ->limit(20)
            ->pluck('ring_number')
            ->map(fn ($r) => strtoupper(trim($r)))
            ->toArray();

        if (empty($existing)) {
            return response()->json(['suggestions' => []]);
        }

        // Find max numeric suffix to compute next in sequence
        $maxNumber = null;
        $numericLength = null;

        foreach ($existing as $ring) {
            $suffix = substr($ring, strlen($prefix));
            if (preg_match('/^ *(\d+) *$/', $suffix, $matches)) {
                $num = (int) $matches[1];
                if ($maxNumber === null || $num > $maxNumber) {
                    $maxNumber = $num;
                    $numericLength = strlen($matches[1]);
                }
            }
        }

        $suggestions = array_slice($existing, 0, 5);

        if ($maxNumber !== null) {
            $nextSuggestion = $prefix . str_pad($maxNumber + 1, $numericLength, '0', STR_PAD_LEFT);
            $suggestions = array_slice(array_unique(array_merge([$nextSuggestion], $suggestions)), 0, 6);
        }

        return response()->json(['suggestions' => $suggestions]);
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

        // Bloodline ids sent by the client must be the user's own
        $ownIds = Bloodline::where('user_id', $userId)
            ->whereIn('id', collect($bloodlinesData)->filter(fn ($item) => is_array($item) && isset($item['id']) && is_numeric($item['id']))->pluck('id'))
            ->pluck('id')
            ->flip();

        foreach ($bloodlinesData as $item) {
            if (! is_array($item)) {
                continue;
            }

            // Item can be: { id: number, is_primary: bool } or { name: string, is_primary: bool }
            if (isset($item['id'])) {
                if (! isset($ownIds[$item['id']])) {
                    continue;
                }
                $bloodlineId = (int) $item['id'];
            } elseif (isset($item['name']) && is_string($item['name']) && trim($item['name']) !== '') {
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
