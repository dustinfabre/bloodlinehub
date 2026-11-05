<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\OlrRace;
use App\Models\OlrSeason;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nested OLR and club routes: a season, race, event or pigeon in the URL must belong to the race/club/season
 * before it. Using your own race or club with another user's season (or bird) used to reach their data.
 */
class NestedRouteScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected User $victim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create();
        $this->victim = User::factory()->create();
    }

    private function pigeon(User $owner, string $ring): Pigeon
    {
        return Pigeon::create(['user_id' => $owner->id, 'status' => 'racing', 'ring_number' => $ring]);
    }

    /**
     * @return array{0: OlrRace, 1: OlrSeason}
     */
    private function olr(User $owner): array
    {
        $race = OlrRace::create(['user_id' => $owner->id, 'name' => "OLR of {$owner->id}"]);

        return [$race, $race->seasons()->create(['name' => 'Season', 'year' => 2025, 'status' => 'active'])];
    }

    /**
     * @return array{0: Club, 1: ClubSeason}
     */
    private function club(User $owner): array
    {
        $club = Club::create(['user_id' => $owner->id, 'name' => "Club of {$owner->id}"]);

        return [$club, $club->seasons()->create(['name' => 'Season', 'year' => 2025, 'status' => 'active'])];
    }

    public function test_another_users_olr_season_cannot_be_reached_through_my_race(): void
    {
        [$myRace] = $this->olr($this->me);
        [, $theirSeason] = $this->olr($this->victim);
        $theirBird = $this->pigeon($this->victim, 'THEIRS');
        $theirSeason->entries()->attach($theirBird->id, ['notes' => 'private']);

        $this->actingAs($this->me);

        $this->get(route('olr-races.seasons.show', [$myRace, $theirSeason]))->assertNotFound();
        $this->get(route('olr-races.seasons.edit', [$myRace, $theirSeason]))->assertNotFound();
        $this->patch(route('olr-races.seasons.update', [$myRace, $theirSeason]), [
            'name' => 'Hacked', 'year' => 2025, 'status' => 'cancelled',
        ])->assertNotFound();
        $this->patch(route('olr-races.seasons.entries.update', [$myRace, $theirSeason, $theirBird]), ['status' => 'deceased'])
            ->assertNotFound();
        $this->delete(route('olr-races.seasons.entries.destroy', [$myRace, $theirSeason, $theirBird]))->assertNotFound();
        $this->post(route('olr-races.seasons.races.store', [$myRace, $theirSeason]), ['name' => 'X', 'distance_unit' => 'km'])
            ->assertNotFound();
        $this->delete(route('olr-races.seasons.destroy', [$myRace, $theirSeason]))->assertNotFound();

        $this->assertSame('Season', $theirSeason->fresh()->name);
        $this->assertSame('racing', $theirBird->fresh()->status);
        $this->assertSame(1, $theirSeason->entries()->count());
        $this->assertSame(0, $theirSeason->races()->count());
    }

    public function test_another_users_bird_cannot_be_changed_through_my_season(): void
    {
        [$myRace, $mySeason] = $this->olr($this->me);
        [$myClub, $myClubSeason] = $this->club($this->me);
        $theirBird = $this->pigeon($this->victim, 'THEIRS');

        $this->actingAs($this->me);

        $this->patch(route('olr-races.seasons.entries.update', [$myRace, $mySeason, $theirBird]), ['status' => 'deceased'])
            ->assertNotFound();
        $this->patch(route('clubs.seasons.entries.update', [$myClub, $myClubSeason, $theirBird]), ['status' => 'missing'])
            ->assertNotFound();

        $this->assertSame('racing', $theirBird->fresh()->status);
    }

    public function test_a_race_from_another_season_cannot_be_reached(): void
    {
        [$myRace, $mySeason] = $this->olr($this->me);
        [, $theirSeason] = $this->olr($this->victim);
        $theirLap = $theirSeason->races()->create(['name' => 'Their lap', 'distance_unit' => 'km']);
        $otherSeasonOfMine = $myRace->seasons()->create(['name' => 'Other', 'year' => 2024, 'status' => 'active']);
        $myOtherLap = $otherSeasonOfMine->races()->create(['name' => 'My lap', 'distance_unit' => 'km']);

        $this->actingAs($this->me);

        $this->get(route('olr-races.seasons.races.show', [$myRace, $mySeason, $theirLap]))->assertNotFound();
        $this->delete(route('olr-races.seasons.races.destroy', [$myRace, $mySeason, $theirLap]))->assertNotFound();
        // Even my own race, under the wrong season of mine
        $this->get(route('olr-races.seasons.races.show', [$myRace, $mySeason, $myOtherLap]))->assertNotFound();
        $this->get(route('olr-races.seasons.races.show', [$myRace, $otherSeasonOfMine, $myOtherLap]))->assertOk();

        $this->assertNotNull($theirLap->fresh());
    }

    public function test_results_are_scoped_to_the_birds_in_that_race(): void
    {
        [$myRace, $mySeason] = $this->olr($this->me);
        $lap = $mySeason->races()->create(['name' => 'Lap 1', 'distance_unit' => 'km']);
        $flown = $this->pigeon($this->me, 'FLOWN');
        $notFlown = $this->pigeon($this->me, 'NOT-FLOWN');
        $mySeason->entries()->attach([$flown->id, $notFlown->id]);
        $lap->results()->attach($flown->id, ['position' => 3, 'did_not_arrive' => false]);

        $this->actingAs($this->me);

        $this->patch(route('olr-races.seasons.races.results.update', [$myRace, $mySeason, $lap, $notFlown]), ['position' => 1])
            ->assertNotFound();
        $this->patch(route('olr-races.seasons.races.results.update', [$myRace, $mySeason, $lap, $flown]), ['position' => 1])
            ->assertRedirect();

        $this->assertSame(1, (int) $lap->results()->first()->pivot->position);
    }

    public function test_entries_still_work_for_my_own_birds(): void
    {
        [$myRace, $mySeason] = $this->olr($this->me);
        $bird = $this->pigeon($this->me, 'MINE');
        $mySeason->entries()->attach($bird->id);

        $this->actingAs($this->me)
            ->patch(route('olr-races.seasons.entries.update', [$myRace, $mySeason, $bird]), ['overall_position' => 4])
            ->assertRedirect();
        $this->actingAs($this->me)
            ->delete(route('olr-races.seasons.entries.destroy', [$myRace, $mySeason, $bird]))
            ->assertRedirect();

        $this->assertSame(0, $mySeason->entries()->count());
    }

    public function test_another_users_club_season_event_and_race_cannot_be_reached(): void
    {
        [$myClub, $mySeason] = $this->club($this->me);
        [, $theirSeason] = $this->club($this->victim);
        $theirEvent = $theirSeason->events()->create(['name' => 'Their event']);
        $theirRace = $theirSeason->races()->create(['name' => 'Their race', 'distance_unit' => 'km']);
        $theirBird = $this->pigeon($this->victim, 'THEIRS');
        $theirEvent->entries()->attach($theirBird->id);

        $this->actingAs($this->me);

        $this->get(route('clubs.seasons.show', [$myClub, $theirSeason]))->assertNotFound();
        $this->get(route('clubs.seasons.events.show', [$myClub, $theirSeason, $theirEvent]))->assertNotFound();
        $this->get(route('clubs.seasons.events.show', [$myClub, $mySeason, $theirEvent]))->assertNotFound();
        $this->delete(route('clubs.seasons.events.entries.destroy', [$myClub, $mySeason, $theirEvent, $theirBird]))
            ->assertNotFound();
        $this->get(route('clubs.seasons.races.show', [$myClub, $mySeason, $theirRace]))->assertNotFound();
        $this->delete(route('clubs.seasons.races.destroy', [$myClub, $mySeason, $theirRace]))->assertNotFound();

        $this->assertSame(1, $theirEvent->entries()->count());
        $this->assertNotNull($theirRace->fresh());
    }

    public function test_club_results_and_events_are_scoped(): void
    {
        [$myClub, $mySeason] = $this->club($this->me);
        $otherSeasonOfMine = $myClub->seasons()->create(['name' => 'Other', 'year' => 2024, 'status' => 'active']);
        $eventInOther = $otherSeasonOfMine->events()->create(['name' => 'Elsewhere']);
        $race = $mySeason->races()->create(['name' => 'Race 1', 'distance_unit' => 'km']);
        $flown = $this->pigeon($this->me, 'FLOWN');
        $notFlown = $this->pigeon($this->me, 'NOT-FLOWN');
        $mySeason->entries()->attach([$flown->id, $notFlown->id]);
        $race->results()->attach($flown->id, ['position' => 2, 'did_not_arrive' => false]);

        $this->actingAs($this->me);

        $this->patch(route('clubs.seasons.races.results.update', [$myClub, $mySeason, $race, $notFlown]), ['position' => 1])
            ->assertNotFound();
        $this->delete(route('clubs.seasons.races.results.destroy', [$myClub, $mySeason, $race, $notFlown]))
            ->assertNotFound();
        $this->patch(route('clubs.seasons.events.update', [$myClub, $mySeason, $eventInOther]), ['name' => 'Moved'])
            ->assertNotFound();
        $this->delete(route('clubs.seasons.events.destroy', [$myClub, $mySeason, $eventInOther]))
            ->assertNotFound();

        $this->delete(route('clubs.seasons.races.results.destroy', [$myClub, $mySeason, $race, $flown]))
            ->assertRedirect();

        $this->assertSame(0, $race->results()->count());
        $this->assertSame('Elsewhere', $eventInOther->fresh()->name);
    }

    public function test_my_own_nested_club_pages_still_open(): void
    {
        [$myClub, $mySeason] = $this->club($this->me);
        $event = $mySeason->events()->create(['name' => 'Basketing']);
        $race = $mySeason->races()->create(['name' => 'Race 1', 'distance_unit' => 'km']);

        $this->actingAs($this->me);

        $this->get(route('clubs.seasons.show', [$myClub, $mySeason]))->assertOk();
        $this->get(route('clubs.seasons.events.show', [$myClub, $mySeason, $event]))->assertOk();
        $this->get(route('clubs.seasons.races.show', [$myClub, $mySeason, $race]))->assertOk();
    }
}
