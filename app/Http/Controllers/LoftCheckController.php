<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LoftCheck;
use App\Models\Pigeon;
use App\Models\PigeonStatusChange;
use App\Services\LoftCheck\LoftCheckScope;
use App\Services\Pedigree\GoneBirdPedigrees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loft checks: count the birds in part of the loft (e.g. after a training toss) by ticking each bird seen, then
 * mark the ones not seen as missing. Each check is kept, so the history shows when a bird went missing.
 */
class LoftCheckController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $checks = LoftCheck::where('user_id', $user->id)
            ->latest('id')
            ->with(['missingPigeons' => fn ($query) => $query
                ->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.status')
                ->orderBy('pigeons.ring_number')])
            ->paginate(20)
            ->through(fn (LoftCheck $check) => [
                ...$this->summary($check),
                'missing' => $check->missingPigeons->map(fn (Pigeon $pigeon) => [
                    'id' => $pigeon->id,
                    'name' => $pigeon->name,
                    'ring_number' => $pigeon->ring_number,
                    'personal_number' => $pigeon->personal_number,
                    // Where the bird is now: still missing, or back in the loft
                    'status' => $pigeon->status,
                ])->values(),
            ]);

        return Inertia::render('loft-checks/Index', [
            'checks' => $checks,
            'missingNow' => Pigeon::where('user_id', $user->id)->where('status', 'missing')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $scope = LoftCheckScope::fromInput($user, $request->query());

        // The last count of this same group, to compare with
        $lastCheck = LoftCheck::where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->first(fn (LoftCheck $check) => $check->scope == $scope->toArray());

        return Inertia::render('loft-checks/Create', [
            'scope' => $scope->toArray(),
            'label' => $scope->label(),
            'locations' => Location::where('user_id', $user->id)
                ->where('type', 'manual')
                ->orderBy('name')
                ->get(['id', 'name']),
            'seriesOptions' => $scope->seriesOptions(),
            'inLoftStatuses' => Pigeon::IN_LOFT_STATUSES,
            // The birds to tick, in the standard pigeon search format (see CLAUDE.md) plus their place in the series
            'birds' => $scope->birds()->map(fn (Pigeon $pigeon) => LoftCheckScope::boardItem($pigeon))->values(),
            'missingBirds' => $scope->missingBirds()->load('latestStatusChange')->map(fn (Pigeon $pigeon) => [
                ...LoftCheckScope::boardItem($pigeon),
                'status_since' => $pigeon->statusSince()?->toIso8601String(),
            ])->values(),
            'lastCheck' => $lastCheck ? $this->summary($lastCheck) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(Pigeon::IN_LOFT_STATUSES)],
            'present' => ['nullable', 'array'],
            'present.*' => ['integer'],
            'missing' => ['nullable', 'array'],
            'missing.*' => ['integer'],
            'returned' => ['nullable', 'array'],
            'returned.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = LoftCheckScope::fromInput($user, $validated, strict: true);
        $birds = $scope->birds();
        $missingBirds = $scope->missingBirds();

        // Only birds of this group count; anything else (e.g. a bird changed in another tab meanwhile) is ignored
        // (only() on models matches ids but doesn't keep the keys, hence modelKeys())
        $present = collect($birds->only($validated['present'] ?? [])->modelKeys());
        $missing = collect($birds->only($validated['missing'] ?? [])->modelKeys())->diff($present)->values();
        $unchecked = $birds->keys()->diff($present)->diff($missing)->values();
        $returned = collect($missingBirds->only($validated['returned'] ?? [])->modelKeys());

        if ($birds->isEmpty() && $returned->isEmpty()) {
            throw ValidationException::withMessages(['present' => 'There are no birds to count in this group.']);
        }

        $check = DB::transaction(function () use ($user, $scope, $validated, $birds, $missingBirds, $present, $missing, $unchecked, $returned) {
            $check = LoftCheck::create([
                'user_id' => $user->id,
                'label' => $scope->label(),
                'scope' => $scope->toArray(),
                'note' => $validated['note'] ?? null,
                'expected_count' => $birds->count(),
                'present_count' => $present->count(),
                'missing_count' => $missing->count(),
                'unchecked_count' => $unchecked->count(),
                'returned_count' => $returned->count(),
            ]);

            $results = [
                LoftCheck::PRESENT => $present,
                LoftCheck::MISSING => $missing,
                LoftCheck::UNCHECKED => $unchecked,
                LoftCheck::RETURNED => $returned,
            ];

            $rows = [];
            foreach ($results as $result => $ids) {
                foreach ($ids as $id) {
                    $rows[$id] = ['result' => $result];
                }
            }
            $check->pigeons()->attach($rows);

            $changes = [];

            if ($missing->isNotEmpty()) {
                // One query for every bird; the status history is written below, with this check on it
                Pigeon::whereIn('id', $missing)->update(['status' => 'missing']);

                foreach ($missing as $id) {
                    $changes[] = ['pigeon_id' => $id, 'from_status' => $birds[$id]->status, 'to_status' => 'missing'];
                }
            }

            foreach ($returned as $id) {
                $back = $this->statusBeforeMissing($id);
                Pigeon::whereKey($id)->update(['status' => $back]);
                $changes[] = ['pigeon_id' => $id, 'from_status' => $missingBirds[$id]->status, 'to_status' => $back];
            }

            $now = now();
            PigeonStatusChange::insert(array_map(
                fn (array $change) => [...$change, 'loft_check_id' => $check->id, 'changed_at' => $now],
                $changes,
            ));

            return $check;
        });

        GoneBirdPedigrees::promptFor($missing);

        return redirect()
            ->route('loft-checks.show', $check)
            ->with('success', 'Loft check saved.');
    }

    public function show(Request $request, LoftCheck $loftCheck): Response
    {
        $this->ensureOwner($request, $loftCheck);

        $columns = array_map(fn (string $column) => "pigeons.{$column}", [...Pigeon::SEARCH_COLUMNS, 'status']);

        $pigeons = $loftCheck->pigeons()
            ->with(Pigeon::SEARCH_RELATIONS)
            ->orderBy('pigeons.ring_number')
            ->orderBy('pigeons.personal_number')
            ->get($columns)
            ->map(fn (Pigeon $pigeon) => [
                ...$pigeon->searchSummary(),
                'status' => $pigeon->status,
                'result' => $pigeon->pivot->result,
            ])
            ->groupBy('result');

        return Inertia::render('loft-checks/Show', [
            'check' => $this->summary($loftCheck),
            'present' => $pigeons->get(LoftCheck::PRESENT, collect())->values(),
            'missing' => $pigeons->get(LoftCheck::MISSING, collect())->values(),
            'unchecked' => $pigeons->get(LoftCheck::UNCHECKED, collect())->values(),
            'returned' => $pigeons->get(LoftCheck::RETURNED, collect())->values(),
        ]);
    }

    public function destroy(Request $request, LoftCheck $loftCheck): RedirectResponse
    {
        $this->ensureOwner($request, $loftCheck);

        // The statuses it changed stay as they are; their history entries just lose the link to this check
        $loftCheck->delete();

        return redirect()
            ->route('loft-checks.index')
            ->with('success', 'Loft check deleted.');
    }

    private function summary(LoftCheck $check): array
    {
        return [
            'id' => $check->id,
            'label' => $check->label,
            'note' => $check->note,
            'scope' => $check->scope,
            'expected_count' => $check->expected_count,
            'present_count' => $check->present_count,
            'missing_count' => $check->missing_count,
            'unchecked_count' => $check->unchecked_count,
            'returned_count' => $check->returned_count,
            'created_at' => $check->created_at->toIso8601String(),
        ];
    }

    // The status a missing bird had before it went missing, to give back when it returns
    private function statusBeforeMissing(int $pigeonId): string
    {
        $before = PigeonStatusChange::where('pigeon_id', $pigeonId)
            ->where('to_status', 'missing')
            ->latest('id')
            ->value('from_status');

        return in_array($before, Pigeon::IN_LOFT_STATUSES, true) ? $before : 'stock';
    }

    private function ensureOwner(Request $request, LoftCheck $check): void
    {
        abort_if($check->user_id !== $request->user()->id, 404);
    }
}
