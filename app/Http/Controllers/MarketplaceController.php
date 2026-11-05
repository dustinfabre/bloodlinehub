<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public marketplace: active sale listings, visible without logging in.
 *
 * Only what the seller chose to publish is shown: no account email, no internal ids, and no price when the
 * seller hid it.
 */
class MarketplaceController extends Controller
{
    public function index(): Response
    {
        $sales = Sale::query()
            ->where('status', 'active')
            // A listing must be for the seller's own bird (older rows could point at someone else's)
            ->whereHas('pigeon', fn ($query) => $query->whereColumn('pigeons.user_id', 'sales.user_id'))
            ->with([
                'pigeon:id,name,gender,hatch_date,color,ring_number,personal_number,photo_url,sire_id,dam_id',
                'pigeon.sire:id,name,ring_number',
                'pigeon.dam:id,name,ring_number',
                'user:id,name',
            ])
            ->latest()
            ->paginate(24)
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'price' => $sale->hide_price ? null : $sale->price,
                'hide_price' => $sale->hide_price,
                'description' => $sale->description,
                'contact' => $sale->contact,
                'additional_photos' => array_values(array_filter(
                    $sale->additional_photos ?? [],
                    fn ($url) => is_string($url) && preg_match('#^https?://#i', $url),
                )),
                'listed_at' => $sale->created_at?->toDateString(),
                'pigeon' => [
                    'name' => $sale->pigeon->name,
                    'gender' => $sale->pigeon->gender,
                    'hatch_date' => $sale->pigeon->hatch_date?->format('Y-m-d'),
                    'color' => $sale->pigeon->color,
                    'ring_number' => $sale->pigeon->ring_number,
                    'personal_number' => $sale->pigeon->personal_number,
                    'photo_url' => $sale->pigeon->photo_url,
                    'sire' => $sale->pigeon->sire ? [
                        'name' => $sale->pigeon->sire->name,
                        'ring_number' => $sale->pigeon->sire->ring_number,
                    ] : null,
                    'dam' => $sale->pigeon->dam ? [
                        'name' => $sale->pigeon->dam->name,
                        'ring_number' => $sale->pigeon->dam->ring_number,
                    ] : null,
                ],
                'seller' => $sale->user->name,
            ]);

        return Inertia::render('marketplace/Index', [
            'sales' => $sales,
        ]);
    }
}
