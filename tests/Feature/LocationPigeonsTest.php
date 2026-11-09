<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\OlrRace;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocationPigeonsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function location(string $name, array $attributes = [], ?User $owner = null): Location
    {
        return Location::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => $name,
            'type' => 'manual',
            ...$attributes,
        ]);
    }

    private function pigeon(string $ring, array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'ring_number' => $ring,
            'gender' => 'male',
            'status' => 'stock',
            ...$attributes,
        ]);
    }

    public function test_the_location_page_lists_its_birds_including_gone_ones(): void
    {
        $loft = $this->location('Main loft');
        $this->pigeon('R-2', ['location_id' => $loft->id]);
        $this->pigeon('R-1', ['location_id' => $loft->id, 'status' => 'missing']);
        $this->pigeon('R-3');

        $this->actingAs($this->user)
            ->get(route('locations.show', $loft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('locations/Show')
                ->where('location.name', 'Main loft')
                ->has('pigeons', 2)
                ->where('pigeons.0.ring_number', 'R-1')
                ->where('pigeons.0.status', 'missing')
                ->where('pigeons.1.ring_number', 'R-2')
                // The picker's birds load only when it opens
                ->missing('candidates'));
    }

    public function test_the_picker_offers_birds_in_the_loft_that_are_not_here_yet(): void
    {
        $loft = $this->location('Main loft');
        $breeding = $this->location('Breeding loft');
        $this->pigeon('HERE', ['location_id' => $loft->id]);
        $this->pigeon('ELSEWHERE', ['location_id' => $breeding->id]);
        $this->pigeon('NOWHERE');
        $this->pigeon('GONE', ['status' => 'deceased']);
        $this->pigeon('NOT-MINE', [], User::factory()->create());

        $this->actingAs($this->user)
            ->get(route('locations.show', $loft))
            ->assertInertia(fn (Assert $page) => $page
                ->where('otherLocations', [['id' => $breeding->id, 'name' => 'Breeding loft']])
                ->reloadOnly('candidates', fn (Assert $reload) => $reload
                    ->has('candidates', 2)
                    ->where('candidates.0.ring_number', 'ELSEWHERE')
                    ->where('candidates.0.location_name', 'Breeding loft')
                    ->where('candidates.1.ring_number', 'NOWHERE')
                    ->where('candidates.1.location_name', null)));
    }

    public function test_another_users_location_cannot_be_opened_or_changed(): void
    {
        $theirs = $this->location('Their loft', [], User::factory()->create());
        $mine = $this->pigeon('R-1');

        $this->actingAs($this->user)->get(route('locations.show', $theirs))->assertForbidden();
        $this->actingAs($this->user)
            ->post(route('locations.pigeons.store', $theirs), ['pigeon_ids' => [$mine->id]])
            ->assertForbidden();

        $this->assertNull($mine->fresh()->location_id);
    }

    public function test_adding_birds_moves_them_here_from_any_other_location(): void
    {
        $loft = $this->location('Main loft');
        $breeding = $this->location('Breeding loft');
        $moving = $this->pigeon('R-1', ['location_id' => $breeding->id]);
        $unplaced = $this->pigeon('R-2');
        $staying = $this->pigeon('R-3', ['location_id' => $breeding->id]);

        $this->actingAs($this->user)
            ->post(route('locations.pigeons.store', $loft), ['pigeon_ids' => [$moving->id, $unplaced->id]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Added 2 pigeons to Main loft. 1 moved from another location.');

        $this->assertSame($loft->id, $moving->fresh()->location_id);
        $this->assertSame($loft->id, $unplaced->fresh()->location_id);
        $this->assertSame($breeding->id, $staying->fresh()->location_id);
    }

    public function test_other_users_birds_cannot_be_added(): void
    {
        $loft = $this->location('Main loft');
        $mine = $this->pigeon('R-1');
        $theirs = $this->pigeon('R-2', [], User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('locations.pigeons.store', $loft), ['pigeon_ids' => [$mine->id, $theirs->id]])
            ->assertSessionHasErrors('pigeon_ids');

        $this->assertNull($mine->fresh()->location_id);
        $this->assertNull($theirs->fresh()->location_id);

        $this->actingAs($this->user)
            ->post(route('locations.pigeons.store', $loft), ['pigeon_ids' => ['abc']])
            ->assertSessionHasErrors('pigeon_ids.0');
    }

    public function test_removing_birds_leaves_them_with_no_location(): void
    {
        $loft = $this->location('Main loft');
        $breeding = $this->location('Breeding loft');
        $here = $this->pigeon('R-1', ['location_id' => $loft->id]);
        $alsoHere = $this->pigeon('R-2', ['location_id' => $loft->id]);
        $elsewhere = $this->pigeon('R-3', ['location_id' => $breeding->id]);

        $this->actingAs($this->user)
            ->delete(route('locations.pigeons.destroy', $loft), ['pigeon_ids' => [$here->id, $elsewhere->id]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Removed 1 pigeon from Main loft.');

        $this->assertNull($here->fresh()->location_id);
        $this->assertSame($loft->id, $alsoHere->fresh()->location_id);
        // A bird at another location is not taken off it
        $this->assertSame($breeding->id, $elsewhere->fresh()->location_id);
    }

    public function test_a_location_can_be_marked_as_a_breeding_location(): void
    {
        $this->actingAs($this->user)
            ->post(route('locations.store'), ['name' => 'Breeding loft', 'is_breeding' => true])
            ->assertRedirect();

        $location = Location::where('name', 'Breeding loft')->firstOrFail();
        $this->assertTrue($location->is_breeding);

        $this->actingAs($this->user)
            ->patch(route('locations.update', $location), ['name' => 'Breeding loft', 'is_breeding' => false])
            ->assertRedirect();

        $this->assertFalse($location->fresh()->is_breeding);
    }

    public function test_a_one_loft_race_location_cannot_be_a_breeding_location(): void
    {
        $race = OlrRace::create(['user_id' => $this->user->id, 'name' => 'Derby']);
        $location = $this->location('Derby', ['type' => 'olr', 'olr_race_id' => $race->id]);

        $this->actingAs($this->user)
            ->patch(route('locations.update', $location), ['name' => 'Derby', 'is_breeding' => true])
            ->assertRedirect();

        $this->assertFalse($location->fresh()->is_breeding);
    }
}
