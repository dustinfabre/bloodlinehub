<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Free and Premium. Free costs nothing; for Premium an admin sets the prices, how long one payment lasts, and the
 * length of the free trial. A change only affects new payments: each payment keeps the plan as it was.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $premium = Plan::premiumOrCreate()->loadCount('payments');

        return Inertia::render('admin/Plans', [
            'premium' => [
                'price_php' => (float) $premium->price_php > 0 ? $premium->price_php : null,
                'price_usd' => $premium->price_usd,
                'duration_months' => $premium->duration_months,
                'trial_days' => $premium->trial_days,
                'on_sale' => $premium->isOnSale(),
                'payments_count' => $premium->payments_count,
            ],
            'durations' => Plan::DURATIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price_php' => ['required', 'numeric', 'min:1', 'max:99999999'],
            // Blank: payers abroad can't buy Premium
            'price_usd' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'duration_months' => ['required', 'integer', Rule::in(Plan::DURATIONS)],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        Plan::premiumOrCreate()->update([...$validated, 'is_active' => true]);

        return back();
    }
}
