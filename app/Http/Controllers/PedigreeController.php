<?php

namespace App\Http\Controllers;

use App\Models\Pedigree;
use App\Models\Pigeon;
use App\Services\PedigreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PedigreeController extends Controller
{
    // How long a share password works, in days (null = until it is changed or removed)
    private const PASSWORD_DURATIONS = [
        'never' => null,
        '1d' => 1,
        '7d' => 7,
        '30d' => 30,
    ];

    public function __construct(private PedigreeService $pedigrees) {}

    public function show(Request $request, Pigeon $pigeon): Response
    {
        $this->ensureOwner($request, $pigeon);

        return Inertia::render('pigeons/Pedigree', [
            'pigeon' => $this->summary($pigeon),
            ...$this->pedigrees->chart($pigeon),
            'sharing' => $this->sharing($pigeon->pedigreeRecord()),
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

        $pedigree = $pigeon->pedigreeRecord();

        $validated = $request->validate([
            'visibility' => ['required', Rule::in(['private', 'password', 'public'])],
            // Leaving the password blank keeps the current one (e.g. when only changing how long it lasts)
            'password' => [
                Rule::requiredIf(fn () => $request->input('visibility') === 'password' && $pedigree->password === null),
                'nullable',
                'string',
                'min:8',
                'max:64',
            ],
            'expires' => ['required_if:visibility,password', 'nullable', Rule::in([...array_keys(self::PASSWORD_DURATIONS), 'custom'])],
            'expires_at' => ['required_if:expires,custom', 'nullable', 'date', 'after:now'],
        ], [
            'expires_at.after' => 'Choose a date in the future.',
        ]);

        if ($validated['visibility'] !== 'password') {
            $pedigree->update([
                'is_public' => $validated['visibility'] === 'public',
                'password' => null,
                'password_expires_at' => null,
            ]);

            return back()->with('success', $validated['visibility'] === 'public' ? 'Pedigree is now public.' : 'Pedigree is now private.');
        }

        $expires = $validated['expires'];

        $pedigree->update([
            'is_public' => false,
            'password_expires_at' => match ($expires) {
                'custom' => Carbon::parse($validated['expires_at']),
                default => self::PASSWORD_DURATIONS[$expires] ? now()->addDays(self::PASSWORD_DURATIONS[$expires]) : null,
            },
            ...filled($validated['password'] ?? null) ? ['password' => $validated['password']] : [],
        ]);

        return back()->with('success', 'Password protection is on.');
    }

    /**
     * Shared pedigree page (the QR code target). Private pedigrees are only visible to their owner;
     * password-protected ones also to visitors who have entered the password.
     */
    public function shared(Request $request, string $serial): Response|HttpResponse
    {
        $pedigree = $this->findBySerial($serial);
        $pigeon = $pedigree->pigeon;
        $isOwner = $request->user()?->id === $pigeon->user_id;
        $unlocked = $pedigree->grantsAccess($request->session()->get($this->accessKey($pedigree)));

        if (! $pedigree->is_public && ! $isOwner && ! $unlocked) {
            // If the owner logs in from here, bring them straight back
            if (! $request->user()) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return Inertia::render('pedigrees/Shared', [
                'serial' => $pedigree->serial,
                'isOwner' => false,
                'locked' => $pedigree->hasActivePassword() ? 'password' : 'private',
                'tree' => null,
                'meta' => null,
            ])->toResponse($request)->setStatusCode(403);
        }

        return Inertia::render('pedigrees/Shared', [
            'serial' => $pedigree->serial,
            'isOwner' => $isOwner,
            'pigeonId' => $isOwner ? $pigeon->id : null,
            'locked' => null,
            // Visitors who unlocked with a timed password see how long their access lasts
            'accessExpiresAt' => ! $isOwner && ! $pedigree->is_public
                ? $pedigree->password_expires_at?->toIso8601String()
                : null,
            ...$this->pedigrees->chart($pigeon, public: true),
        ]);
    }

    public function unlock(Request $request, string $serial): RedirectResponse
    {
        $pedigree = $this->findBySerial($serial);

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:64'],
        ]);

        // Wrong guesses are also limited per pedigree, whatever address they come from (the route limit is per visitor)
        $limits = $this->unlockLimits($pedigree);

        foreach ($limits as [$key, $maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                throw ValidationException::withMessages([
                    'password' => "Too many wrong passwords for this pedigree. Try again in {$minutes} ".($minutes === 1 ? 'minute.' : 'minutes.'),
                ]);
            }
        }

        if (! $pedigree->hasActivePassword() || ! Hash::check($validated['password'], $pedigree->password)) {
            foreach ($limits as [$key, , $decaySeconds]) {
                RateLimiter::hit($key, $decaySeconds);
            }

            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        $request->session()->put($this->accessKey($pedigree), $pedigree->accessToken());

        return redirect()->route('pedigrees.shared', $pedigree->serial);
    }

    /**
     * Failed-attempt windows for one pedigree: 10 per 15 minutes and 50 per day.
     *
     * @return list<array{0: string, 1: int, 2: int}> [limiter key, max failed attempts, window in seconds]
     */
    private function unlockLimits(Pedigree $pedigree): array
    {
        return [
            ["pedigree-unlock:{$pedigree->id}:15m", 10, 15 * 60],
            ["pedigree-unlock:{$pedigree->id}:day", 50, 24 * 60 * 60],
        ];
    }

    private function findBySerial(string $serial): Pedigree
    {
        return Pedigree::with('pigeon')
            ->where('serial', strtoupper($serial))
            ->firstOrFail();
    }

    private function accessKey(Pedigree $pedigree): string
    {
        return "pedigree_access.{$pedigree->id}";
    }

    private function sharing(Pedigree $pedigree): array
    {
        return [
            'visibility' => $pedigree->visibility(),
            'has_password' => $pedigree->password !== null,
            'password_expires_at' => $pedigree->password_expires_at?->toIso8601String(),
            'password_expired' => $pedigree->password !== null && ! $pedigree->hasActivePassword(),
        ];
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
