<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Models\Bloodline;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\Pedigree\OutsideBirdSync;
use App\Services\Pedigree\PedigreeShowcase;
use App\Services\PedigreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pedigree editor for ancestors that are not birds in the loft.
 *
 * Each external ancestor is stored on its nearest loft bird (the "anchor") with a path relative to it, so every
 * offspring of that bird inherits it. The anchor's own parents live on the pigeons table (sire_name, dam_name, ...);
 * deeper ancestors live in pedigree_ancestors.
 *
 * The same outside bird can be recorded in several pedigrees (e.g. after copying a side). Unless the request opts out
 * with sync=false, saving or removing it also updates its other records, matched by ring number (OutsideBirdSync).
 */
class PedigreeAncestorController extends Controller
{
    private const PARENT_FIELDS = [
        'name' => 'name',
        'ring_number' => 'ring_number',
        'color' => 'color',
        'bloodline' => 'bloodline',
        'remarks' => 'notes',
        'merits' => 'merits',
    ];

    public function __construct(
        private PedigreeService $pedigrees,
        private OutsideBirdSync $sync,
        private PedigreeShowcase $showcase,
    ) {}

    public function edit(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        $tree = $this->pedigrees->tree($pigeon);

        return Inertia::render('pigeons/EditPedigree', [
            'pigeon' => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
            ],
            'tree' => $tree,
            // Every record of each outside bird on this chart, so the editor can say where a change also applies
            'links' => $this->sync->recordsFor($request->user()->id, $this->outsideRings($tree)),
            // What the last save or removal also changed in other pedigrees
            'syncReport' => $request->session()->get('pedigreeSync'),
            // Plumage colors already used in the loft, as suggestions
            'colors' => Pigeon::where('user_id', $request->user()->id)
                ->whereNotNull('color')
                ->distinct()
                ->orderBy('color')
                ->pluck('color'),
            // The loft's bloodlines, as suggestions
            'bloodlines' => Bloodline::where('user_id', $request->user()->id)->orderBy('name')->pluck('name'),
            'meritTypes' => MeritType::options(),
            'meritLevels' => MeritLevel::options(),
            // Related loft birds not on the chart that can be shown below it as notable relatives
            'notableRelatives' => $this->showcase->candidates($pigeon)
                ->map(fn (array $candidate) => [
                    ...$candidate['pigeon']->searchSummary(),
                    ...$this->showcase->notable($candidate['pigeon'], $candidate['relation'], public: false),
                    'featured' => in_array($candidate['pigeon']->id, $pigeon->featured_relatives ?? [], true),
                ])
                ->values(),
        ]);
    }

    /**
     * Choose which related loft birds are shown below the pedigree as notable relatives.
     */
    public function featureRelatives(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            'pigeon_ids' => ['present', 'array', 'max:500'],
            'pigeon_ids.*' => ['integer'],
        ]);

        // Only birds that are related and off the chart, in the order they are listed
        $featured = $this->showcase->candidates($pigeon)
            ->map(fn (array $candidate) => $candidate['pigeon']->id)
            ->intersect($validated['pigeon_ids'])
            ->values()
            ->all();

        $pigeon->update(['featured_relatives' => $featured ?: null]);

        return back()->with('success', 'Notable relatives saved.');
    }

    public function update(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            ...$this->slotRules($request),
            'ring_number' => ['nullable', 'required_without:name', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'bloodline' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            ...PigeonMerit::listRules('merits'),
            'sync' => ['sometimes', 'boolean'],
        ], [
            'ring_number.required_without' => 'Enter a ring number or a name.',
        ]);

        [$anchor, $path] = $this->slot($request, $validated);
        $details = collect($validated)->only(array_keys(self::PARENT_FIELDS))->all();
        $details['merits'] = PigeonMerit::cleanList($validated['merits'] ?? null);
        $before = $this->sync->detailsAt($anchor, $path);

        if (strlen($path) === 1) {
            $side = $this->side($path);
            $anchor->update(collect(self::PARENT_FIELDS)
                ->mapWithKeys(fn (string $column, string $field) => ["{$side}_{$column}" => $details[$field] ?? null])
                ->all());
        } else {
            $anchor->pedigreeAncestors()->updateOrCreate(['path' => $path], $details);
        }

        if ($request->boolean('sync', true)) {
            $report = $before
                ? $this->sync->pushEdit($anchor, $path, $before, $details)
                : $this->sync->pushAddition($anchor, $path, $details);
        }

        return $this->back($report ?? null, 'Ancestor saved.');
    }

    public function destroy(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([...$this->slotRules($request), 'sync' => ['sometimes', 'boolean']]);
        [$anchor, $path] = $this->slot($request, $validated);
        $removed = $this->sync->detailsAt($anchor, $path);

        if (strlen($path) === 1) {
            $side = $this->side($path);
            $anchor->update(collect(self::PARENT_FIELDS)
                ->mapWithKeys(fn (string $column) => ["{$side}_{$column}" => null])
                ->all());
        }

        // Ancestors further back hang off this one, so they go too
        $anchor->pedigreeAncestors()->where('path', 'like', $path.'%')->delete();

        if ($request->boolean('sync', true)) {
            $report = $this->sync->pushRemoval($anchor, $path, $removed['ring_number'] ?? null);
        }

        return $this->back($report ?? null, 'Ancestor removed.');
    }

    /**
     * @param  array{updated: list<string>, skipped: list<string>}|null  $report
     */
    private function back(?array $report, string $message): RedirectResponse
    {
        $response = back()->with('success', $message);

        return $report && ($report['updated'] || $report['skipped'])
            ? $response->with('pedigreeSync', $report)
            : $response;
    }

    /**
     * Ring numbers of the outside birds on a chart.
     *
     * @return list<string>
     */
    private function outsideRings(?array $node): array
    {
        if (! $node) {
            return [];
        }

        return [
            ...($node['external'] && $node['ring_number'] ? [$node['ring_number']] : []),
            ...$this->outsideRings($node['sire']),
            ...$this->outsideRings($node['dam']),
        ];
    }

    private function slotRules(Request $request): array
    {
        return [
            'anchor_id' => [
                'required',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $request->user()->id),
            ],
            'path' => ['required', 'string', 'regex:/^[SD]{1,4}$/'],
        ];
    }

    /**
     * Resolve the anchor bird and check the slot can hold an external ancestor.
     *
     * @return array{0: Pigeon, 1: string}
     */
    private function slot(Request $request, array $validated): array
    {
        $anchor = Pigeon::findOrFail($validated['anchor_id']);
        $path = $validated['path'];
        $side = $this->side($path);

        if ($anchor->{"{$side}_id"}) {
            throw ValidationException::withMessages([
                'path' => 'This bird is in your loft. Edit its own record instead.',
            ]);
        }

        // An ancestor needs the bird in front of it (its offspring in the tree) to be known first
        $offspringPath = substr($path, 0, -1);
        $offspringKnown = match (strlen($offspringPath)) {
            0 => true,
            1 => $anchor->hasExternalParent($side),
            default => $anchor->pedigreeAncestors()->where('path', $offspringPath)->exists(),
        };

        if (! $offspringKnown) {
            throw ValidationException::withMessages([
                'path' => 'Add the bird in front of this one first.',
            ]);
        }

        return [$anchor, $path];
    }

    private function side(string $path): string
    {
        return $path[0] === 'S' ? 'sire' : 'dam';
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
