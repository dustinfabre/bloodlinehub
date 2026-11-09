<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pickers for breeding, sales and race entries only offer birds still in the loft:
 * deceased and missing birds are left out.
 */
class InLoftPickersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function pigeon(string $ring, string $status, ?string $gender = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'ring_number' => $ring,
            'status' => $status,
            'gender' => $gender,
        ]);
    }

    /**
     * One bird per status, named after it (e.g. "C-DECEASED" for a deceased cock).
     */
    private function birdOfEveryStatus(string $prefix, ?string $gender = null): void
    {
        foreach (Pigeon::STATUSES as $status) {
            $this->pigeon($prefix.strtoupper($status), $status, $gender);
        }
    }

    /**
     * @param  iterable<array{ring_number: ?string}>  $birds
     * @return list<string>
     */
    private function rings(iterable $birds): array
    {
        return collect($birds)->pluck('ring_number')->sort()->values()->all();
    }

    public function test_the_scope_keeps_birds_in_the_loft(): void
    {
        $this->birdOfEveryStatus('B-');

        $this->assertSame(
            ['B-BREEDING', 'B-INJURED', 'B-RACING', 'B-STOCK'],
            Pigeon::inLoft()->orderBy('ring_number')->pluck('ring_number')->all(),
        );
    }

    public function test_create_pairing_only_offers_birds_in_the_loft(): void
    {
        $this->birdOfEveryStatus('C-', 'male');
        $this->birdOfEveryStatus('H-', 'female');

        $this->actingAs($this->user)
            ->get(route('pairings.create'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $props = $page->toArray()['props'];
                $this->assertSame(['C-BREEDING', 'C-INJURED', 'C-RACING', 'C-STOCK'], $this->rings($props['sires']));
                $this->assertSame(['H-BREEDING', 'H-INJURED', 'H-RACING', 'H-STOCK'], $this->rings($props['dams']));
            });
    }

    public function test_edit_pairing_still_shows_its_own_birds_after_they_are_gone(): void
    {
        $sire = $this->pigeon('OLD-COCK', 'deceased', 'male');
        $dam = $this->pigeon('OLD-HEN', 'missing', 'female');
        $this->pigeon('OTHER-GONE', 'missing', 'male');
        $this->pigeon('OTHER-HERE', 'stock', 'male');
        $pairing = Pairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->user)
            ->get(route('pairings.edit', $pairing))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $props = $page->toArray()['props'];
                $this->assertSame(['OLD-COCK', 'OTHER-HERE'], $this->rings($props['sires']));
                $this->assertSame(['OLD-HEN'], $this->rings($props['dams']));
            });
    }

    public function test_sales_only_offer_birds_in_the_loft(): void
    {
        $this->birdOfEveryStatus('S-');

        $this->actingAs($this->user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSame(
                ['S-BREEDING', 'S-INJURED', 'S-RACING', 'S-STOCK'],
                $this->rings($page->toArray()['props']['availablePigeons']),
            ));
    }

    public function test_club_events_only_offer_team_birds_still_in_the_loft(): void
    {
        $club = Club::create(['user_id' => $this->user->id, 'name' => 'Club']);
        $season = $club->seasons()->create(['name' => 'Season', 'year' => 2026, 'status' => 'active']);
        $event = $season->events()->create(['name' => 'Derby']);
        $season->entries()->attach([
            $this->pigeon('TEAM-RACING', 'racing')->id,
            $this->pigeon('TEAM-MISSING', 'missing')->id,
            $this->pigeon('TEAM-DECEASED', 'deceased')->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('clubs.seasons.events.show', [$club, $season, $event]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSame(
                ['TEAM-RACING'],
                $this->rings($page->toArray()['props']['availablePigeons']),
            ));
    }
}
