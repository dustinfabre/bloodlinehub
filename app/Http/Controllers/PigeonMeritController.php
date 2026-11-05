<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PigeonMeritController extends Controller
{
    public function store(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(MeritType::class)],
            'level' => ['nullable', Rule::enum(MeritLevel::class)],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'detail' => ['nullable', 'string', 'max:255'],
        ]);

        $pigeon->merits()->create($validated);

        return back()->with('success', 'Merit added.');
    }

    public function destroy(Request $request, Pigeon $pigeon, PigeonMerit $merit): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        if ($merit->pigeon_id !== $pigeon->id) {
            abort(404);
        }

        $merit->delete();

        return back()->with('success', 'Merit removed.');
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
