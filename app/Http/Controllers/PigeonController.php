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

        $pigeons = Pigeon::query()
            ->with([
                'sire:id,ring_number,personal_number,color',
                'dam:id,ring_number,personal_number,color',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10)
            ->through(fn (Pigeon $pigeon) => $this->transformPigeon($pigeon));

        return Inertia::render('pigeons/Index', [
            'pigeons' => $pigeons,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('pigeons/Create', [
            'parentOptions' => $this->parentOptions($request->user()->id),
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

        return Inertia::render('pigeons/Edit', [
            'pigeon' => $this->transformPigeon($pigeon),
            'parentOptions' => $this->parentOptions($request->user()->id, $pigeon->id),
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
            ->whereIn('pigeon_status', ['breeding', 'racing'])
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
            ->whereIn('pigeon_status', ['breeding', 'racing'])
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
