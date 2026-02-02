<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function index(): Response
    {
        $sales = Sale::query()
            ->where('status', 'active')
            ->with(['pigeon:id,name,gender,hatch_date,color,ring_number,personal_number,photos,pedigree_image', 'pigeon.sire:id,name,ring_number', 'pigeon.dam:id,name,ring_number', 'user:id,name,email'])
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
                    'hatch_date' => $sale->pigeon->hatch_date?->format('Y-m-d'),
                    'color' => $sale->pigeon->color,
                    'ring_number' => $sale->pigeon->ring_number,
                    'personal_number' => $sale->pigeon->personal_number,
                    'photos' => $sale->pigeon->photos,
                    'pedigree_image' => $sale->pigeon->pedigree_image,
                    'sire' => $sale->pigeon->sire ? [
                        'id' => $sale->pigeon->sire->id,
                        'name' => $sale->pigeon->sire->name,
                        'ring_number' => $sale->pigeon->sire->ring_number,
                    ] : null,
                    'dam' => $sale->pigeon->dam ? [
                        'id' => $sale->pigeon->dam->id,
                        'name' => $sale->pigeon->dam->name,
                        'ring_number' => $sale->pigeon->dam->ring_number,
                    ] : null,
                ],
                'owner' => [
                    'id' => $sale->user->id,
                    'name' => $sale->user->name,
                    'email' => $sale->user->email,
                ],
            ]);

        return Inertia::render('marketplace/Index', [
            'sales' => $sales,
        ]);
    }
}
