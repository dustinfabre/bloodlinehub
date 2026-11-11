<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Premium plans and their prices. A price change only affects new payments: each payment keeps the plan's name and
 * length as they were when it was made.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Plans', [
            'plans' => Plan::ordered()->withCount('payments')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));

        return back();
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'price_php' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'price_usd' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
