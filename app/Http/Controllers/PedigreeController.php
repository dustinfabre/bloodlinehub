<?php

namespace App\Http\Controllers;

use App\Models\Pedigree;
use App\Models\Pigeon;
use App\Services\PedigreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PedigreeController extends Controller
{
    public function __construct(private PedigreeService $pedigrees) {}

    public function show(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        return Inertia::render('pigeons/Pedigree', [
            'pigeon' => $this->summary($pigeon),
            ...$this->pedigrees->chart($pigeon),
        ]);
    }

    public function print(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        return Inertia::render('pigeons/PrintPedigree', [
            'pigeon' => $this->summary($pigeon),
            ...$this->pedigrees->chart($pigeon),
        ]);
    }

    public function updateVisibility(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $pigeon->pedigreeRecord()->update(['is_public' => $validated['is_public']]);

        return back()->with('success', $validated['is_public'] ? 'Pedigree is now public.' : 'Pedigree is now private.');
    }

    /**
     * Public pedigree page (the QR code target). Private pedigrees are only visible to their owner.
     */
    public function shared(Request $request, string $serial): Response|HttpResponse
    {
        $pedigree = Pedigree::with('pigeon')
            ->where('serial', strtoupper($serial))
            ->firstOrFail();

        $pigeon = $pedigree->pigeon;
        $isOwner = $request->user()?->id === $pigeon->user_id;

        if (! $pedigree->is_public && ! $isOwner) {
            // If the owner logs in from here, bring them straight back
            if (! $request->user()) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return Inertia::render('pedigrees/Shared', [
                'serial' => $pedigree->serial,
                'isOwner' => false,
                'tree' => null,
                'meta' => null,
            ])->toResponse($request)->setStatusCode(403);
        }

        return Inertia::render('pedigrees/Shared', [
            'serial' => $pedigree->serial,
            'isOwner' => $isOwner,
            'pigeonId' => $isOwner ? $pigeon->id : null,
            ...$this->pedigrees->chart($pigeon, public: true),
        ]);
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }

    private function summary(Pigeon $pigeon): array
    {
        return [
            'id' => $pigeon->id,
            'name' => $pigeon->name,
            'ring_number' => $pigeon->ring_number,
            'personal_number' => $pigeon->personal_number,
        ];
    }
}
