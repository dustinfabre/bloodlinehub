<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounts: who signed up, how many birds they keep, and their Premium. Premium can be set by hand here, to fix a
 * mistaken approval or to give it free (a sponsor, a club officer, a tester).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = Str::limit(trim((string) $request->query('search', '')), 100, '');
        $plan = in_array($request->query('plan'), ['premium', 'free'], true) ? $request->query('plan') : null;

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes(Str::lower($search), '%_\\').'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(email) like ?', [$like]));
            })
            ->when($plan === 'premium', fn ($query) => $query->where('premium_until', '>', now()))
            ->when($plan === 'free', fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('premium_until')->orWhere('premium_until', '<=', now())))
            ->withCount('pigeons')
            ->latest()->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'premium_until' => $user->premium_until?->toIso8601String(),
                'premium' => $user->isPremium(),
                'pigeons_count' => $user->pigeons_count,
                'created_at' => $user->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => ['search' => $search, 'plan' => $plan],
        ]);
    }

    /**
     * Set Premium by hand: until the end of a date, or off (null).
     */
    public function updatePremium(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'premium_until' => ['nullable', 'date', 'after:today'],
        ], [
            'premium_until.after' => 'Pick a date after today, or clear it to switch Premium off.',
        ]);

        $user->forceFill([
            'premium_until' => isset($validated['premium_until'])
                ? Carbon::parse($validated['premium_until'])->endOfDay()
                : null,
        ])->save();

        return back();
    }
}
