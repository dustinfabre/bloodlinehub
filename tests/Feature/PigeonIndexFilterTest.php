<?php

namespace Tests\Feature;

use App\Models\Bloodline;
use App\Models\Location;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PigeonIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function pigeon(string $ring, array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ringsFor(array $query): array
    {
        $rings = [];

        $this->actingAs($this->user)
            ->get(route('pigeons.index', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rings) {
                $rings = collect($page->toArray()['props']['pigeons']['data'])->pluck('ring_number')->sort()->values()->all();
            });

        return $rings;
    }

    public function test_status_filter_accepts_multiple_values(): void
    {
        $this->pigeon('R-1', ['status' => 'racing']);
        $this->pigeon('R-2', ['status' => 'breeding']);
        $this->pigeon('R-3', ['status' => 'stock']);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['status' => ['racing', 'breeding']]));
        // A single value still works for old links
        $this->assertSame(['R-3'], $this->ringsFor(['status' => 'stock']));
    }

    public function test_unknown_status_values_are_ignored(): void
    {
        $this->pigeon('R-1', ['status' => 'racing']);
        $this->pigeon('R-2', ['status' => 'stock']);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['status' => ['bogus']]));
    }

    public function test_bloodline_filter_matches_any_selected_line(): void
    {
        $janssen = Bloodline::create(['user_id' => $this->user->id, 'name' => 'JANSSEN']);
        $sion = Bloodline::create(['user_id' => $this->user->id, 'name' => 'SION']);
        $other = Bloodline::create(['user_id' => $this->user->id, 'name' => 'OTHER']);

        $this->pigeon('R-1')->bloodlines()->attach($janssen->id, ['is_primary' => true]);
        // Secondary lines count too
        $this->pigeon('R-2')->bloodlines()->attach([$other->id => ['is_primary' => true], $sion->id => ['is_primary' => false]]);
        $this->pigeon('R-3')->bloodlines()->attach($other->id, ['is_primary' => true]);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['bloodline' => [$janssen->id, $sion->id]]));
    }

    public function test_location_filter_supports_ids_and_no_location(): void
    {
        $lofts = Location::create(['user_id' => $this->user->id, 'name' => 'Main loft', 'type' => 'manual']);
        $breeding = Location::create(['user_id' => $this->user->id, 'name' => 'Breeding loft', 'type' => 'manual']);

        $this->pigeon('R-1', ['location_id' => $lofts->id]);
        $this->pigeon('R-2', ['location_id' => $breeding->id]);
        $this->pigeon('R-3');

        $this->assertSame(['R-1'], $this->ringsFor(['location' => [$lofts->id]]));
        $this->assertSame(['R-1', 'R-3'], $this->ringsFor(['location' => [$lofts->id, 'none']]));
    }

    public function test_filters_combine_and_are_echoed_back_as_arrays(): void
    {
        $loft = Location::create(['user_id' => $this->user->id, 'name' => 'Main loft', 'type' => 'manual']);
        $this->pigeon('R-1', ['status' => 'racing', 'gender' => 'male', 'location_id' => $loft->id]);
        $this->pigeon('R-2', ['status' => 'racing', 'gender' => 'female', 'location_id' => $loft->id]);
        $this->pigeon('R-3', ['status' => 'stock', 'gender' => 'male', 'location_id' => $loft->id]);

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['status' => ['racing'], 'gender' => 'male', 'location' => [$loft->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Index')
                ->has('pigeons.data', 1)
                ->where('pigeons.data.0.ring_number', 'R-1')
                ->where('pigeons.data.0.location.name', 'Main loft')
                ->where('filters.status', ['racing'])
                ->where('filters.location', [(string) $loft->id])
                ->where('filters.bloodline', [])
                ->has('locations', 1)
            );
    }

    public function test_per_page_accepts_supported_sizes_only(): void
    {
        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['per_page' => 24]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 24));

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['per_page' => 1000]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 12));
    }
}
