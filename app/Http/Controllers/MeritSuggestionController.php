<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\Merits\MeritSuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Add or dismiss merits suggested from race results and offspring.
 *
 * The client only sends suggestion keys ("{pigeon id}:{type}:{source}"); the suggestions are worked out again
 * here, so only merits the rules actually suggest can be added.
 */
class MeritSuggestionController extends Controller
{
    public function __construct(private MeritSuggester $suggester) {}

    public function accept(Request $request): RedirectResponse
    {
        $suggestions = $this->requested($request);

        foreach ($suggestions as $suggestion) {
            $merit = $suggestion['merit'];

            PigeonMerit::create([
                'pigeon_id' => $suggestion['pigeon_id'],
                'type' => $merit['type'],
                'level' => $merit['level'],
                'year' => $merit['year'],
                'detail' => $merit['detail'],
                'source' => $suggestion['source'],
            ]);
        }

        $count = count($suggestions);

        return back()->with('success', $count === 1 ? 'Merit added.' : "{$count} merits added.");
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $now = now();

        DB::table('merit_suggestion_dismissals')->insertOrIgnore(array_map(fn ($suggestion) => [
            'pigeon_id' => $suggestion['pigeon_id'],
            'suggestion' => $suggestion['suggestion'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->requested($request)));

        return back()->with('success', 'Suggestion dismissed.');
    }

    /**
     * The open suggestions matching the requested keys, for the user's own birds only.
     *
     * @return list<array<string, mixed>>
     */
    private function requested(Request $request): array
    {
        $validated = $request->validate([
            'keys' => ['required', 'array', 'max:500'],
            'keys.*' => ['required', 'string', 'max:160', 'regex:/^\d+:[a-z_]+:[a-z_]+(:\d+)?$/'],
        ]);

        $keys = array_flip($validated['keys']);
        $pigeonIds = Pigeon::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', array_map(fn (string $key) => (int) strtok($key, ':'), $validated['keys']))
            ->pluck('id')
            ->all();

        return collect($this->suggester->forPigeons($request->user()->id, $pigeonIds))
            ->flatten(1)
            ->filter(fn ($suggestion) => isset($keys[$suggestion['key']]))
            ->values()
            ->all();
    }
}
