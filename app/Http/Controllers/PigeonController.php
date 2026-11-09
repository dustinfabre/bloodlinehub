<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Http\Requests\StorePigeonRequest;
use App\Http\Requests\UpdatePigeonRequest;
use App\Models\Bloodline;
use App\Models\GoogleDriveConnection;
use App\Models\Location;
use App\Models\Pigeon;
use App\Models\PigeonStatusChange;
use App\Services\GoogleDrive\GoogleDriveException;
use App\Services\ImageUploadService;
use App\Services\Merits\GoldenPairs;
use App\Services\Merits\MeritSuggester;
use App\Services\Pedigree\OutsideBirdSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    // Parent side => its pedigree path from the pigeon
    private const PARENT_SIDES = ['sire' => 'S', 'dam' => 'D'];

    /**
     * Sort options for the pigeon list (value => label). The first is the default.
     */
    private const SORTS = [
        'recent' => 'Recently added',
        'ring' => 'Ring number (A–Z)',
        'ring_desc' => 'Ring number (Z–A)',
        'name' => 'Name (A–Z)',
        'youngest' => 'Youngest first',
        'oldest' => 'Oldest first',
        'merits' => 'Best merits first',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $sort = array_key_exists((string) $request->input('sort'), self::SORTS) ? (string) $request->input('sort') : 'recent';
        
        // Multiples of 12 so the 2- and 3-column card grids always fill complete rows
        $perPage = (int) $request->input('per_page', 12);
        $perPage = in_array($perPage, [12, 24, 48, 96], true) ? $perPage : 12;

        $query = Pigeon::query()
            ->with([
                'sire:id,ring_number,personal_number,color',
                'dam:id,ring_number,personal_number,color',
                'bloodlines',
                'location:id,name,type',
                'merits',
                // For "Missing since …" on the cards
                'latestStatusChange',
            ])
            ->where('user_id', $user->id);

        // No status in the URL lists the birds in the loft; "all" lists every bird, gone ones included
        $statusInput = $this->arrayInput($request, 'status');
        $statuses = match (true) {
            $statusInput === [] => Pigeon::IN_LOFT_STATUSES,
            in_array('all', $statusInput, true) => [],
            default => array_values(array_intersect($statusInput, Pigeon::STATUSES)),
        };
        $bloodlineFilter = $this->arrayInput($request, 'bloodline');
        $locationFilter = $this->arrayInput($request, 'location');
        $meritFilter = array_values(array_intersect(
            $this->arrayInput($request, 'merit'),
            array_column(MeritType::cases(), 'value'),
        ));
        // "none" matches birds without a gender (it's optional); a single ?gender=male still works
        $genders = array_values(array_intersect($this->arrayInput($request, 'gender'), ['male', 'female', 'none']));

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

        if ($genders) {
            $known = array_values(array_diff($genders, ['none']));
            $query->where(function ($q) use ($known, $genders) {
                if ($known) {
                    $q->whereIn('gender', $known);
                }
                if (in_array('none', $genders, true)) {
                    $q->orWhereNull('gender')->orWhere('gender', '');
                }
            });
        }

        if ($statuses === Pigeon::IN_LOFT_STATUSES) {
            $query->inLoft();
        } elseif ($statuses) {
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

        $pigeons = $this->sort($query, $sort)
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

        return Inertia::render('pigeons/Index', [
            'pigeons' => $pigeons,
            'bloodlines' => $bloodlines,
            'locations' => Location::where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'meritTypes' => MeritType::options(),
            'sorts' => collect(self::SORTS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'colors' => $colors,
            'parentOptions' => $this->parentOptions($user->id),
            'inLoftStatuses' => Pigeon::IN_LOFT_STATUSES,
            'filters' => [
                'search' => $request->input('search'),
                'gender' => $genders,
                'status' => $statuses,
                'bloodline' => array_values($bloodlineFilter),
                'location' => array_values($locationFilter),
                'merit' => $meritFilter,
                'per_page' => $perPage,
                'sort' => $sort,
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

        // Get pre-filled data from query params (from pairing)
        $prefill = $request->only(['sire_id', 'dam_id', 'pairing_id', 'clutch_id', 'hatch_date']);

        $locations = Location::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('pigeons/Create', [
            'parentOptions' => $this->parentOptions($user->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'locations' => $locations,
            'prefill' => $prefill,
            'driveConnected' => GoogleDriveConnection::where('user_id', $user->id)->exists(),
        ]);
    }

    public function store(StorePigeonRequest $request, ImageUploadService $imageService): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Extract bloodlines data before creating pigeon
        $bloodlinesData = (array) $request->input('bloodlines', []);
        unset($data['bloodlines']);

        // Photos go to the user's own Google Drive
        if ($request->hasFile('photo')) {
            $data['photo_url'] = $this->toDrive('photo', fn () => $imageService->upload(
                $request->file('photo'),
                $request->user(),
                "{$data['ring_number']} photo",
            ));
        }

        if ($request->hasFile('pedigree_images')) {
            $data['pedigree_images'] = $this->toDrive('pedigree_images', fn () => $imageService->uploadMultiple(
                $request->file('pedigree_images'),
                $request->user(),
                "{$data['ring_number']} pedigree",
            ));
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
            'sire',
            'dam',
            'pedigree',
            'merits',
            'location:id,name,type',
            'latestStatusChange',
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
            // When the bird went missing, came back, etc., most recent first
            'statusHistory' => $pigeon->statusChanges()
                ->with('loftCheck:id,label')
                ->limit(50)
                ->get()
                ->map(fn (PigeonStatusChange $change) => [
                    'id' => $change->id,
                    'from_status' => $change->from_status,
                    'to_status' => $change->to_status,
                    'changed_at' => $change->changed_at->toIso8601String(),
                    'loft_check' => $change->loftCheck ? ['id' => $change->loftCheck->id, 'label' => $change->loftCheck->label] : null,
                ]),
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

    public function edit(Request $request, Pigeon $pigeon, OutsideBirdSync $outsideBirds): Response
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

        $locations = Location::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('pigeons/Edit', [
            'pigeon' => $this->transformPigeon($pigeon),
            'parentOptions' => $this->parentOptions($user->id, $pigeon->id),
            'bloodlines' => $bloodlines,
            'colors' => $colors,
            'locations' => $locations,
            'driveConnected' => GoogleDriveConnection::where('user_id', $user->id)->exists(),
            // Other pedigrees that record the same outside sire or dam (matched by ring number)
            'outsideLinks' => collect(self::PARENT_SIDES)->map(fn (string $letter, string $side) => $pigeon->hasExternalParent($side)
                ? $outsideBirds->otherLabels($user->id, $pigeon->{"{$side}_ring_number"}, $pigeon->id, $letter)
                : []),
            // Which other pedigrees the last save also updated
            'syncReport' => $request->session()->get('pedigreeSync'),
        ]);
    }

    public function update(UpdatePigeonRequest $request, Pigeon $pigeon, ImageUploadService $imageService, OutsideBirdSync $outsideBirds): RedirectResponse
    {
        $data = $request->validated();
        // Outside parents as they were, so changes to them can be carried over to their other pedigree records
        $outsideBefore = collect(self::PARENT_SIDES)->map(fn (string $letter) => $outsideBirds->detailsAt($pigeon, $letter));
        $user = $request->user();
        $label = $data['ring_number'] ?? $pigeon->ring_number;

        // Extract bloodlines data before updating pigeon
        $bloodlinesData = (array) $request->input('bloodlines', []);
        unset($data['bloodlines']);

        // Images replaced or removed here leave the app only once the pigeon is saved, so a failed upload never
        // costs the user the photo they had
        $discard = [];

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $this->toDrive('photo', fn () => $imageService->upload(
                $request->file('photo'),
                $user,
                "{$label} photo",
            ));
            $discard[] = $pigeon->photo_url;
        } elseif ($request->boolean('remove_photo')) {
            $data['photo_url'] = null;
            $discard[] = $pigeon->photo_url;
        }

        if ($request->hasFile('pedigree_images')) {
            $newImages = $this->toDrive('pedigree_images', fn () => $imageService->uploadMultiple(
                $request->file('pedigree_images'),
                $user,
                "{$label} pedigree",
            ));
            $data['pedigree_images'] = array_merge($pigeon->pedigree_images ?? [], $newImages);
        }

        if ($request->input('remove_pedigree')) {
            // Only this pigeon's own images: any other URL could be someone else's file
            // Keep images uploaded in this same request
            $existingImages = $request->hasFile('pedigree_images') ? $data['pedigree_images'] : ($pigeon->pedigree_images ?? []);
            $toRemove = array_values(array_intersect((array) $request->input('remove_pedigree'), $pigeon->pedigree_images ?? []));
            $data['pedigree_images'] = array_values(array_diff($existingImages, $toRemove));
            array_push($discard, ...$toRemove);
        }

        $pigeon->update($data);

        if ($discard = array_values(array_filter($discard))) {
            $imageService->deleteMultiple($discard, $user);
        }

        // Sync bloodlines with pivot data
        $this->syncBloodlines($pigeon, $bloodlinesData, $request->user()->id);

        // An outside sire or dam is the same bird wherever its ring number appears: update its other records too
        $syncedTo = [];
        foreach (self::PARENT_SIDES as $side => $letter) {
            $after = $outsideBirds->detailsAt($pigeon, $letter);

            if ($outsideBefore[$side] && $after && $request->boolean("sync_{$side}", true)) {
                array_push($syncedTo, ...$outsideBirds->pushEdit($pigeon, $letter, $outsideBefore[$side], $after)['updated']);
            }
        }

        $redirect = $request->boolean('edit_pedigree')
            ? redirect()->route('pigeons.editPedigree', $pigeon)
            : redirect()->route('pigeons.edit', $pigeon);

        if ($syncedTo) {
            $redirect->with('pedigreeSync', ['updated' => array_values(array_unique($syncedTo)), 'skipped' => []]);
        }

        return $redirect->with('success', 'Pigeon updated successfully.');
    }

    /**
     * Change only the status, e.g. from the status menu on a pigeon card, without going through the edit form.
     */
    public function updateStatus(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Pigeon::STATUSES)],
        ]);

        $pigeon->update(['status' => $validated['status']]);

        return back();
    }

    public function destroy(Request $request, Pigeon $pigeon, ImageUploadService $imageService): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $images = array_values(array_filter([$pigeon->photo_url, ...($pigeon->pedigree_images ?? [])]));

        $pigeon->delete();

        // Stop serving its images and move them to the Drive trash, where the user can still restore them
        $imageService->deleteMultiple($images, $request->user());

        return redirect()
            ->route('pigeons.index')
            ->with('success', 'Pigeon removed successfully.');
    }

    /**
     * Run a Google Drive upload, turning a Drive failure into a form error on the given field.
     *
     * @template T
     *
     * @param  callable(): T  $upload
     * @return T
     */
    private function toDrive(string $field, callable $upload): mixed
    {
        try {
            return $upload();
        } catch (GoogleDriveException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
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

    /**
     * Order the pigeon list. Birds missing the sorted value (no name, no hatch date) always go last, and the id
     * breaks ties so pages never repeat or skip a bird.
     */
    private function sort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'ring' => $query->orderByRaw('ring_number IS NULL')->orderByRaw('lower(ring_number) asc')->orderBy('id'),
            'ring_desc' => $query->orderByRaw('ring_number IS NULL')->orderByRaw('lower(ring_number) desc')->orderByDesc('id'),
            'name' => $query->orderByRaw("name IS NULL OR name = ''")->orderByRaw('lower(name) asc')->orderByRaw('lower(ring_number) asc')->orderBy('id'),
            'youngest' => $query->orderByRaw('hatch_date IS NULL')->orderByDesc('hatch_date')->orderByDesc('id'),
            'oldest' => $query->orderByRaw('hatch_date IS NULL')->orderBy('hatch_date')->orderBy('id'),
            // Sum of merit weights (MeritType::weight()), the same score Lineage uses to rank proven birds
            'merits' => $query->orderByRaw('('.$this->meritScoreSql().') desc')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private function meritScoreSql(): string
    {
        // Built only from the enum's own values and weights, never from request input
        $weights = collect(MeritType::cases())
            ->map(fn (MeritType $type) => "when '{$type->value}' then {$type->weight()}")
            ->implode(' ');

        return "select coalesce(sum(case pigeon_merits.type {$weights} else 0 end), 0) from pigeon_merits where pigeon_merits.pigeon_id = pigeons.id";
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
            // When the bird got its current status, if recorded (e.g. "Missing since …")
            'status_since' => $pigeon->relationLoaded('latestStatusChange') ? $pigeon->statusSince()?->toIso8601String() : null,
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
            'dam_name' => $pigeon->dam_name,
            'dam_ring_number' => $pigeon->dam_ring_number,
            'dam_color' => $pigeon->dam_color,
            'dam_notes' => $pigeon->dam_notes,
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

    // The user's cocks and hens for the sire/dam searches, in the standard pigeon search format (see CLAUDE.md)
    private function parentOptions(int $userId, ?int $excludeId = null)
    {
        $options = fn (string $gender) => Pigeon::query()
            ->where('user_id', $userId)
            ->where('gender', $gender)
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
            ->with(Pigeon::SEARCH_RELATIONS)
            ->orderBy('name')
            ->orderBy('ring_number')
            ->get(Pigeon::SEARCH_COLUMNS)
            ->map->searchSummary();

        return [
            'sires' => $options('male'),
            'dams' => $options('female'),
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
