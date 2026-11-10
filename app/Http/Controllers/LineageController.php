<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Services\Lineage\LineageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Family search: a bird's descendants (children, grandchildren, greats...), its siblings and mates,
 * or every bird that descends from two given birds (the offspring of a pair and their descendants).
 */
class LineageController extends Controller
{
    public function __construct(private LineageService $lineage) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $mode = $request->input('mode') === 'pair' ? 'pair' : 'bird';
        $graph = $this->lineage->graph($user->id);

        $props = [
            'mode' => $mode,
            'generations' => LineageService::DESCENDANT_GENERATIONS,
            'bird' => null,
            'family' => null,
            'a' => null,
            'b' => null,
            'shared' => null,
        ];

        if ($mode === 'bird' && ($key = $graph->loftBird($request->input('pigeon')))) {
            ['bird' => $props['bird'], 'family' => $props['family']] = $this->lineage->family($graph, $key);
        }

        if ($mode === 'pair') {
            $a = $graph->loftBird($request->input('a'));
            $b = $graph->loftBird($request->input('b'));
            $summaries = $this->lineage->summaries($graph, array_filter([$a, $b]));
            $props['a'] = $a ? $summaries[$a] : null;
            $props['b'] = $b ? $summaries[$b] : null;

            if ($a && $b && $a !== $b) {
                $props['shared'] = $this->lineage->sharedReport($graph, $a, $b);
            }
        }

        return Inertia::render('lineage/Index', $props);
    }

    /**
     * Bird picker search (JSON). Optional gender narrows to that gender plus birds with no gender recorded;
     * in_loft leaves out deceased and missing birds (e.g. when choosing birds to pair).
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $gender = in_array($request->input('gender'), ['male', 'female'], true) ? $request->input('gender') : null;
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $pigeons = Pigeon::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('in_loft'), fn ($q) => $q->inLoft())
            ->when($gender, fn ($q) => $q->where(fn ($g) => $g->where('gender', $gender)->orWhereNull('gender')))
            ->when($term !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('ring_number', $likeOp, "%{$term}%")
                ->orWhere('name', $likeOp, "%{$term}%")
                ->orWhere('personal_number', $likeOp, "%{$term}%")
                ->orWhere('bloodline', $likeOp, "%{$term}%")
            ))
            ->orderByRaw('ring_number IS NULL')
            ->orderBy('ring_number')
            ->limit(20)
            ->with(Pigeon::SEARCH_RELATIONS)
            ->get([...Pigeon::SEARCH_COLUMNS, 'status']);

        // The standard pigeon search format (see CLAUDE.md); only the user's own birds, so notes are fine
        return response()->json(['pigeons' => $pigeons->map(fn (Pigeon $pigeon) => [
            ...$pigeon->searchSummary(),
            'status' => $pigeon->status,
        ])]);
    }
}
