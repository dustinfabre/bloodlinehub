<?php

namespace Tests\Feature;

use App\Models\Pigeon;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public marketplace sits behind config('features.marketplace'): off by default it is a "coming soon" page
 * that loads no listings.
 */
class MarketplaceComingSoonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $seller = User::factory()->create(['name' => 'Seller Loft']);
        $pigeon = Pigeon::create(['user_id' => $seller->id, 'status' => 'stock', 'ring_number' => 'PHA-2026-LISTED']);
        Sale::create(['user_id' => $seller->id, 'pigeon_id' => $pigeon->id, 'price' => 250, 'contact' => '0917 123 4567']);
    }

    public function test_marketplace_is_off_by_default_and_shows_coming_soon(): void
    {
        $this->assertFalse(config('features.marketplace'));

        $response = $this->get(route('marketplace.index'))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/ComingSoon')
            ->missing('sales')
            ->where('features.marketplace', false));

        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('PHA-2026-LISTED', $json);
        $this->assertStringNotContainsString('0917 123 4567', $json);
        $this->assertStringNotContainsString('Seller Loft', $json);
    }

    public function test_marketplace_shows_listings_when_the_flag_is_on(): void
    {
        config(['features.marketplace' => true]);

        $this->get(route('marketplace.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketplace/Index')
                ->has('sales.data', 1)
                ->where('sales.data.0.pigeon.ring_number', 'PHA-2026-LISTED')
                ->where('features.marketplace', true));
    }
}
