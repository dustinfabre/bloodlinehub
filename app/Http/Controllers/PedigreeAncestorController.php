<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Models\ColorTag;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
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
 */
class PedigreeAncestorController extends Controller
{
    private const PARENT_FIELDS = [
        'name' => 'name',
        'ring_number' => 'ring_number',
        'color' => 'color',
        'remarks' => 'notes',
        'color_tag_id' => 'color_tag_id',
        'merits' => 'merits',
    ];

    public function __construct(private PedigreeService $pedigrees) {}

    public function edit(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        return Inertia::render('pigeons/EditPedigree', [
            'pigeon' => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
            ],
            'tree' => $this->pedigrees->tree($pigeon),
            'colorTags' => ColorTag::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'color']),
            // Plumage colors already used in the loft, as suggestions
            'colors' => Pigeon::where('user_id', $request->user()->id)
                ->whereNotNull('color')
                ->distinct()
                ->orderBy('color')
                ->pluck('color'),
            'meritTypes' => MeritType::options(),
            'meritLevels' => MeritLevel::options(),
        ]);
    }

    public function update(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            ...$this->slotRules($request),
            'ring_number' => ['nullable', 'required_without:name', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'color_tag_id' => [
                'nullable',
                'integer',
                Rule::exists('color_tags', 'id')->where('user_id', $request->user()->id),
            ],
            ...PigeonMerit::listRules('merits'),
        ], [
            'ring_number.required_without' => 'Enter a ring number or a name.',
        ]);

        [$anchor, $path] = $this->slot($request, $validated);
        $details = collect($validated)->only(array_keys(self::PARENT_FIELDS))->all();
        $details['merits'] = PigeonMerit::cleanList($validated['merits'] ?? null);

        if (strlen($path) === 1) {
            $side = $this->side($path);
            $anchor->update(collect(self::PARENT_FIELDS)
                ->mapWithKeys(fn (string $column, string $field) => ["{$side}_{$column}" => $details[$field] ?? null])
                ->all());
        } else {
            $anchor->pedigreeAncestors()->updateOrCreate(['path' => $path], $details);
        }

        return back()->with('success', 'Ancestor saved.');
    }

    public function destroy(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        [$anchor, $path] = $this->slot($request, $request->validate($this->slotRules($request)));

        if (strlen($path) === 1) {
            $side = $this->side($path);
            $anchor->update(collect(self::PARENT_FIELDS)
                ->mapWithKeys(fn (string $column) => ["{$side}_{$column}" => null])
                ->all());
        }

        // Ancestors further back hang off this one, so they go too
        $anchor->pedigreeAncestors()->where('path', 'like', $path.'%')->delete();

        return back()->with('success', 'Ancestor removed.');
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
