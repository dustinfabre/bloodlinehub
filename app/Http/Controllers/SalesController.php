<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        // Get user's pigeons that are not already in active sales
        $availablePigeons = Pigeon::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', function ($query) use ($userId) {
                $query->select('pigeon_id')
                    ->from('sales')
                    ->where('user_id', $userId)
                    ->where('status', 'active');
            })
            ->with(['sire:id,name,ring_number', 'dam:id,name,ring_number'])
            ->get()
            ->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'name' => $pigeon->name,
                'gender' => $pigeon->gender,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'color' => $pigeon->color,
                'photos' => $pigeon->photos,
                'sire' => $pigeon->sire ? [
                    'id' => $pigeon->sire->id,
                    'name' => $pigeon->sire->name,
                    'ring_number' => $pigeon->sire->ring_number,
                ] : null,
                'dam' => $pigeon->dam ? [
                    'id' => $pigeon->dam->id,
                    'name' => $pigeon->dam->name,
                    'ring_number' => $pigeon->dam->ring_number,
                ] : null,
            ]);

        // Get user's active sales
        $activeSales = Sale::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['pigeon:id,name,gender,ring_number,personal_number,color,photos'])
            ->latest()
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'price' => $sale->price,
                'hide_price' => $sale->hide_price,
                'description' => $sale->description,
                'additional_photos' => $sale->additional_photos,
                'created_at' => $sale->created_at,
                'pigeon' => [
                    'id' => $sale->pigeon->id,
                    'name' => $sale->pigeon->name,
                    'gender' => $sale->pigeon->gender,
                    'ring_number' => $sale->pigeon->ring_number,
                    'personal_number' => $sale->pigeon->personal_number,
                    'color' => $sale->pigeon->color,
                    'photos' => $sale->pigeon->photos,
                ],
            ]);

        return Inertia::render('sales/Index', [
            'availablePigeons' => $availablePigeons,
            'activeSales' => $activeSales,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pigeon_id' => 'required|exists:pigeons,id',
            'price' => 'nullable|numeric|min:0',
            'hide_price' => 'boolean',
            'description' => 'nullable|string',
            'additional_photos' => 'nullable|array',
            'additional_photos.*' => 'string',
        ]);

        $validated['user_id'] = $request->user()->id;
        
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
