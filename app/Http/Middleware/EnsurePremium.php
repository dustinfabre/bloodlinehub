<?php

namespace App\Http\Middleware;

use App\Support\PremiumFeature;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premium-only routes: `->middleware('premium:loft-check')`. Opening a page shows what the feature does and how to
 * get Premium; anything else (saving, JSON) is refused with a 403.
 */
class EnsurePremium
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($request->user()?->hasPremium()) {
            return $next($request);
        }

        $feature = PremiumFeature::from($feature);

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return Inertia::render('premium/Required', ['feature' => $feature->toArray()])
                ->toResponse($request)
                ->setStatusCode(403);
        }

        abort(403, $feature->title().' is a Premium feature.');
    }
}
