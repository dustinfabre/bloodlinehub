<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BreedingLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function location(string $name, bool $breeding = true): Location
    {
        return Location::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'manual',
            'is_breeding' => $breeding,
        ]);
    }

    private function pigeon(string $ring, string $gender, array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'ring_number' => $ring,
            'gender' => $gender,
            'status' => 'stock',
            ...$attributes,
        ]);
    }

    private function pairing(string $name, ?Location $location, string $status = 'active', array $attributes = []): Pairing
    {
        return Pairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $attributes['sire_id'] ?? $this->pigeon("S-{$name}", 'male')->id,
            'dam_id' => $attributes['dam_id'] ?? $this->pigeon("D-{$name}", 'female')->id,
            'pair_name' => $name,
            'status' => $status,
            'current_clutch_number' => 1,
            'breeding_location_id' => $location?->id,
            'started_at' => now(),
        ]);
    }

    public function test_the_breeding_page_has_a_tab_for_each_breeding_location(): void
    {
        $sectionA = $this->location('Section A');
        $sectionB = $this->location('Section B');
        $stock = $this->location('Stock loft', breeding: false);
        $this->pairing('A1', $sectionA);
        $this->pairing('A2', $sectionA, 'inactive');
        $this->pairing('B1', $sectionB);
        $this->pairing('S1', $stock);
        $this->pairing('N1', null);

        $this->actingAs($this->user)
            ->get(route('pairings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pairings/Index')
                ->where('allCount', 5)
                ->has('breedingLocations', 2)
                ->where('breedingLocations.0.name', 'Section A')
                ->where('breedingLocations.0.pairings_count', 2)
                ->where('breedingLocations.1.name', 'Section B')
                ->where('breedingLocations.1.pairings_count', 1)
                ->has('pairings.data', 5)
                ->where('filters.location', null));

        // One tab shows the pairs bred there; the counts follow the other filters
        $this->actingAs($this->user)
            ->get(route('pairings.index', ['location' => $sectionA->id, 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.location', $sectionA->id)
                ->has('pairings.data', 1)
                ->where('pairings.data.0.pair_name', 'A1')
                ->where('pairings.data.0.breeding_location.name', 'Section A')
                ->where('allCount', 4)
                ->where('breedingLocations.0.pairings_count', 1));
    }

    public function test_a_pair_created_from_a_location_tab_is_bred_there(): void
    {
        $sectionA = $this->location('Section A');
        $this->location('Section B');

        $this->actingAs($this->user)
            ->get(route('pairings.create', ['location' => $sectionA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('prefill.breeding_location_id', $sectionA->id)
                // Breeding locations are listed first
                ->where('locations.0.is_breeding', true));

        // With two breeding locations and no tab, none is chosen for the user
        $this->actingAs($this->user)
            ->get(route('pairings.create'))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.breeding_location_id', null));
    }

    public function test_the_only_breeding_location_is_the_default(): void
    {
        $this->location('Stock loft', breeding: false);
        $breeding = $this->location('Breeding loft');

        $this->actingAs($this->user)
            ->get(route('pairings.create'))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.breeding_location_id', $breeding->id));
    }

    public function test_another_users_location_is_not_prefilled(): void
    {
        $theirs = Location::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'type' => 'manual']);

        $this->actingAs($this->user)
            ->get(route('pairings.create', ['location' => $theirs->id]))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.breeding_location_id', null));
    }

    public function test_creating_a_pair_moves_the_sire_and_dam_to_its_location(): void
    {
        $stock = $this->location('Stock loft', breeding: false);
        $breeding = $this->location('Breeding loft');
        $sire = $this->pigeon('S-1', 'male', ['location_id' => $stock->id]);
        $dam = $this->pigeon('D-1', 'female');

        $this->actingAs($this->user)
            ->post(route('pairings.store'), [
                'sire_id' => $sire->id,
                'dam_id' => $dam->id,
                'breeding_location_id' => $breeding->id,
            ])
            ->assertRedirect();

        $this->assertSame($breeding->id, $sire->fresh()->location_id);
        $this->assertSame($breeding->id, $dam->fresh()->location_id);
    }

    public function test_moving_an_active_pair_moves_its_sire_and_dam(): void
    {
        $sectionA = $this->location('Section A');
        $sectionB = $this->location('Section B');
        $sire = $this->pigeon('S-1', 'male', ['location_id' => $sectionA->id]);
        $dam = $this->pigeon('D-1', 'female', ['location_id' => $sectionA->id]);
        $pairing = $this->pairing('A1', $sectionA, 'active', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);

        $this->actingAs($this->user)
            ->patch(route('pairings.update', $pairing), ['pair_name' => 'A1', 'breeding_location_id' => (string) $sectionB->id])
            ->assertRedirect();

        $this->assertSame($sectionB->id, $pairing->fresh()->breeding_location_id);
        $this->assertSame($sectionB->id, $sire->fresh()->location_id);
        $this->assertSame($sectionB->id, $dam->fresh()->location_id);
    }

    public function test_an_ended_pair_or_an_unchanged_location_leaves_the_birds_where_they_are(): void
    {
        $sectionA = $this->location('Section A');
        $sectionB = $this->location('Section B');
        $stock = $this->location('Stock loft', breeding: false);

        $sire = $this->pigeon('S-1', 'male', ['location_id' => $stock->id]);
        $dam = $this->pigeon('D-1', 'female', ['location_id' => $stock->id]);
        $ended = $this->pairing('A1', $sectionA, 'inactive', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);

        $this->actingAs($this->user)
            ->patch(route('pairings.update', $ended), ['pair_name' => 'A1', 'breeding_location_id' => $sectionB->id])
            ->assertRedirect();
        $this->assertSame($stock->id, $sire->fresh()->location_id);

        // Renaming an active pair doesn't pull back birds that were moved elsewhere by hand
        $sire2 = $this->pigeon('S-2', 'male', ['location_id' => $stock->id]);
        $dam2 = $this->pigeon('D-2', 'female', ['location_id' => $stock->id]);
        $active = $this->pairing('A2', $sectionA, 'active', ['sire_id' => $sire2->id, 'dam_id' => $dam2->id]);

        $this->actingAs($this->user)
            ->patch(route('pairings.update', $active), ['pair_name' => 'Renamed', 'breeding_location_id' => (string) $sectionA->id])
            ->assertRedirect();
        $this->assertSame($stock->id, $sire2->fresh()->location_id);
    }
}
