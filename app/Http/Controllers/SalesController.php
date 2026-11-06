<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        // The user's birds in the loft that are not already in active sales, in the standard pigeon search format (see CLAUDE.md)
        $availablePigeons = Pigeon::query()
            ->where('user_id', $userId)
            ->inLoft()
            ->whereNotIn('id', function ($query) use ($userId) {
                $query->select('pigeon_id')
                    ->from('sales')
                    ->where('user_id', $userId)
                    ->where('status', 'active');
            })
            ->with(Pigeon::SEARCH_RELATIONS)
            ->get(Pigeon::SEARCH_COLUMNS)
            ->map->searchSummary();

        // Get user's active sales
        $activeSales = Sale::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['pigeon:id,name,gender,ring_number,personal_number,color,photo_url'])
            ->latest()
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'price' => $sale->price,
                'hide_price' => $sale->hide_price,
                'description' => $sale->description,
                'additional_photos' => $sale->additional_photos,
                'contact' => $sale->contact,
                'created_at' => $sale->created_at,
                'pigeon' => [
                    'id' => $sale->pigeon->id,
                    'name' => $sale->pigeon->name,
                    'gender' => $sale->pigeon->gender,
                    'ring_number' => $sale->pigeon->ring_number,
                    'personal_number' => $sale->pigeon->personal_number,
                    'color' => $sale->pigeon->color,
                    'photo_url' => $sale->pigeon->photo_url,
                ],
            ]);

        return Inertia::render('sales/Index', [
            'availablePigeons' => $availablePigeons,
            'activeSales' => $activeSales,
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            // One of the user's own birds, not already listed
            'pigeon_id' => [
                'required',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $userId),
                Rule::unique('sales', 'pigeon_id')->where('user_id', $userId)->where('status', 'active'),
            ],
            'price' => 'nullable|numeric|min:0|max:99999999',
            'hide_price' => 'boolean',
            'description' => 'nullable|string|max:5000',
            'contact' => 'nullable|string|max:255',
            // Shown as images on the public marketplace, so web addresses only
            'additional_photos' => 'nullable|array|max:6',
            'additional_photos.*' => 'url:http,https|max:2048',
        ], [
            'pigeon_id.unique' => 'This pigeon is already listed for sale.',
            'additional_photos.*.url' => 'Each photo must be a web address starting with http:// or https://.',
        ]);

        $validated['user_id'] = $userId;

        // Explicitly cast boolean field for PostgreSQL
        $validated['hide_price'] = (bool) ($validated['hide_price'] ?? false);

        Sale::create($validated);

        return redirect()->route('sales.index')->with('success', 'Pigeon added to sales');
    }

    public function destroy(Request $request, Sale $sale)
    {
        if ($sale->user_id !== $request->user()->id) {
            abort(403);
        }

        $sale->delete();

        return redirect()->route('sales.index')->with('success', 'Sale listing removed');
    }
}
