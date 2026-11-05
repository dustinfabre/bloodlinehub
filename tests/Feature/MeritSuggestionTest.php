<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\OlrRace;
use App\Models\OlrSeason;
use App\Models\OlrSeasonRace;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Models\User;
use App\Services\Merits\MeritSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeritSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected OlrRace $olr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->olr = OlrRace::create(['user_id' => $this->user->id, 'name' => 'Rockdove OLR']);
    }

    private function bird(string $ring, array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => 'racing',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    private function season(string $status = 'completed', string $name = 'Derby 2025'): OlrSeason
    {
        return $this->olr->seasons()->create(['name' => $name, 'year' => 2025, 'status' => $status]);
    }

    private function race(OlrSeason $season, string $name, array $attributes = []): OlrSeasonRace
    {
        return $season->races()->create(['name' => $name, 'distance_unit' => 'km', 'race_type' => 'regular', ...$attributes]);
    }

    /**
     * Record a result: an int position, 'time' (clocked without a position), 'dna' (did not arrive) or null (not flown).
     */
    private function record(OlrSeasonRace $race, Pigeon $pigeon, int|string|null $outcome): void
    {
        $race->season->entries()->syncWithoutDetaching([$pigeon->id]);
        $race->results()->attach($pigeon->id, [
            'position' => is_int($outcome) ? $outcome : null,
            'arrival_time' => $outcome === null || $outcome === 'dna' ? null : '10:15:00',
            'did_not_arrive' => $outcome === 'dna',
        ]);
    }

    /**
     * @return list<string> merit types suggested for the bird
     */
    private function suggested(Pigeon $pigeon): array
    {
        return array_map(
            fn ($suggestion) => $suggestion['merit']['type'],
            app(MeritSuggester::class)->forPigeons($this->user->id, [$pigeon->id])[$pigeon->id] ?? [],
        );
    }

    private function suggestion(Pigeon $pigeon, MeritType $type): ?array
    {
        return collect(app(MeritSuggester::class)->forPigeons($this->user->id, [$pigeon->id])[$pigeon->id] ?? [])
            ->first(fn ($suggestion) => $suggestion['merit']['type'] === $type->value);
    }

    public function test_superset_and_finisher_from_a_completed_season(): void
    {
        $season = $this->season();
        $laps = [$this->race($season, 'Lap 1'), $this->race($season, 'Lap 2'), $this->race($season, 'Lap 3')];
        $training = $this->race($season, 'Toss', ['race_type' => 'training']);

        $allLaps = $this->bird('PHA-1');
        $someLaps = $this->bird('PHA-2');
        $noLaps = $this->bird('PHA-3');

        foreach ($laps as $lap) {
            $this->record($lap, $allLaps, 'time');
            $this->record($lap, $noLaps, 'dna');
        }
        $this->record($laps[0], $someLaps, 'time');
        $this->record($laps[1], $someLaps, 'dna');
        $this->record($laps[2], $someLaps, 'time');
        // A training toss is not a lap
        $this->record($training, $someLaps, 'dna');

        $superset = $this->suggestion($allLaps, MeritType::Superset);
        $this->assertNotNull($superset);
        $this->assertSame('olr', $superset['merit']['level']);
        $this->assertSame(2025, $superset['merit']['year']);
        $this->assertSame('Clocked in all 3 laps', $superset['reason']);
        $this->assertSame("olr_season:{$season->id}", $superset['source']);
        $this->assertSame("SS OLR '25", $superset['merit']['short']);

        $this->assertSame([MeritType::Finisher->value], $this->suggested($someLaps));
        $this->assertSame('Clocked in 2 of 3 laps', $this->suggestion($someLaps, MeritType::Finisher)['reason']);
        $this->assertSame([], $this->suggested($noLaps));
    }

    public function test_series_merits_wait_for_the_season_to_be_completed(): void
    {
        $season = $this->season('active');
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 'time');
        $this->record($this->race($season, 'Lap 2'), $bird, 'time');

        $this->assertSame([], $this->suggested($bird));

        $season->update(['status' => 'completed']);
        $this->assertSame([MeritType::Superset->value], $this->suggested($bird));
    }

    public function test_entries_added_before_a_lap_is_flown_are_not_clocked(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 'time');
        // "Add all entries" rows: no time, no position, not marked as lost
        $this->record($this->race($season, 'Lap 2'), $bird, null);

        $this->assertSame([MeritType::Finisher->value], $this->suggested($bird));
    }

    public function test_race_merits_from_positions(): void
    {
        $season = $this->season('active');
        $lap = $this->race($season, 'Lap 1', ['birds_released' => 1200]);
        $hotSpot = $this->race($season, 'Hot Spot', ['race_type' => 'hot_spot', 'birds_released' => 800]);
        $final = $this->race($season, 'Final', ['race_type' => 'final', 'release_point' => 'Aparri', 'birds_released' => 600]);
        $lap2 = $this->race($season, 'Lap 2', ['birds_released' => 100]);
        $lap3 = $this->race($season, 'Lap 3', ['birds_released' => 100]);

        $bird = $this->bird('PHA-1');
        $this->record($lap, $bird, 1);
        $this->record($hotSpot, $bird, 7);
        $this->record($final, $bird, 3);
        $this->record($lap2, $bird, 15);   // top 20% of 100
        $this->record($lap3, $bird, 25);   // outside the prizes

        $this->assertEqualsCanonicalizing([
            MeritType::RaceWinner->value,
            MeritType::HotSpotWinner->value,
            MeritType::FinalRaceWinner->value,
            MeritType::PrizeWinner->value,
        ], $this->suggested($bird));

        $final = $this->suggestion($bird, MeritType::FinalRaceWinner);
        $this->assertSame('3rd of 600 in the final, Aparri', $final['reason']);
        $this->assertSame('3rd of 600 in the final · Aparri · Derby 2025', $final['merit']['detail']);
        $this->assertSame('7th of 800 in the hot spot, Hot Spot', $this->suggestion($bird, MeritType::HotSpotWinner)['reason']);
        $this->assertSame('Top 20% in Lap 2 (15th of 100)', $this->suggestion($bird, MeritType::PrizeWinner)['reason']);
        $this->assertSame('1st of 1,200, Lap 1', $this->suggestion($bird, MeritType::RaceWinner)['reason']);
    }

    public function test_out_of_the_top_ten_earns_no_final_or_hot_spot_merit(): void
    {
        $season = $this->season('active');
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Final', ['race_type' => 'final']), $bird, 11);
        $this->record($this->race($season, 'Lap 1'), $bird, 2);

        $this->assertSame([], $this->suggested($bird));
    }

    public function test_overall_winner_from_the_overall_position(): void
    {
        $season = $this->season('active');
        $winner = $this->bird('PHA-1');
        $placed = $this->bird('PHA-2');
        $season->entries()->attach($winner->id, ['overall_position' => 4]);
        $season->entries()->attach($placed->id, ['overall_position' => 11]);

        $overall = $this->suggestion($winner, MeritType::OverallWinner);
        $this->assertSame('4th in the overall standings', $overall['reason']);
        $this->assertSame('4th overall · Derby 2025', $overall['merit']['detail']);
        $this->assertSame([], $this->suggested($placed));
    }

    public function test_club_seasons_suggest_club_level_merits(): void
    {
        $club = Club::create(['user_id' => $this->user->id, 'name' => 'Manila RPC']);
        $season = ClubSeason::create(['club_id' => $club->id, 'name' => 'Young Bird 2024', 'year' => 2024, 'status' => 'active']);
        $race = $season->races()->create(['name' => 'Race 1', 'distance_unit' => 'km']);
        $bird = $this->bird('PHA-1');
        $season->entries()->attach($bird->id);
        $race->results()->attach($bird->id, ['position' => 1, 'arrival_time' => '09:00:00', 'did_not_arrive' => false]);

        $winner = $this->suggestion($bird, MeritType::RaceWinner);
        $this->assertSame('club', $winner['merit']['level']);
        $this->assertSame(2024, $winner['merit']['year']);
        $this->assertSame("club_race:{$race->id}", $winner['source']);
    }

    public function test_breeder_of_winners_from_offspring_merits(): void
    {
        $sire = $this->bird('SIRE', ['gender' => 'male']);
        $dam = $this->bird('DAM', ['gender' => 'female']);
        $son = $this->bird('SON', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);
        $grandson = $this->bird('GRANDSON', ['sire_id' => $son->id]);

        $son->merits()->create(['type' => MeritType::RaceWinner]);
        $this->assertSame([], $this->suggested($sire));

        $grandson->merits()->create(['type' => MeritType::OverallWinner]);
        // Superset is a good result, but not a winning one
        $this->bird('DAUGHTER', ['sire_id' => $sire->id])->merits()->create(['type' => MeritType::Superset]);

        $breeder = $this->suggestion($sire, MeritType::BreederOfWinners);
        $this->assertSame('Parent of 1 winner, grandparent of 1 winner', $breeder['reason']);
        $this->assertNull($breeder['merit']['level']);
        // The dam is the winner's mother and the grandson's grandmother too
        $this->assertSame([MeritType::BreederOfWinners->value], $this->suggested($dam));
        // Once a bird holds the merit it isn't suggested again
        $sire->merits()->create(['type' => MeritType::BreederOfWinners, 'year' => 2020]);
        $this->assertSame([], $this->suggested($sire));
    }

    public function test_accepting_a_suggestion_adds_the_merit_once(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 'time');
        $this->record($this->race($season, 'Lap 2'), $bird, 'time');
        $key = $this->suggestion($bird, MeritType::Superset)['key'];

        $this->actingAs($this->user)
            ->post(route('merit-suggestions.accept'), ['keys' => [$key]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Merit added.');

        $merit = $bird->merits()->sole();
        $this->assertSame(MeritType::Superset, $merit->type);
        $this->assertSame("olr_season:{$season->id}", $merit->source);
        $this->assertSame('Clocked 2/2 laps · Derby 2025', $merit->detail);
        $this->assertSame([], $this->suggested($bird));

        // Accepting again does nothing
        $this->actingAs($this->user)->post(route('merit-suggestions.accept'), ['keys' => [$key]]);
        $this->assertSame(1, $bird->merits()->count());
    }

    public function test_dismissed_suggestions_stay_hidden(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 'time');
        $this->record($this->race($season, 'Lap 2'), $bird, 'dna');
        $key = $this->suggestion($bird, MeritType::Finisher)['key'];

        $this->actingAs($this->user)
            ->post(route('merit-suggestions.dismiss'), ['keys' => [$key]])
            ->assertRedirect();

        $this->assertDatabaseHas('merit_suggestion_dismissals', ['pigeon_id' => $bird->id, 'suggestion' => "finisher:olr_season:{$season->id}"]);
        $this->assertSame([], $this->suggested($bird));
        $this->assertSame(0, $bird->merits()->count());
    }

    public function test_a_merit_entered_by_hand_hides_the_matching_suggestion(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 'time');
        $this->record($this->race($season, 'Lap 2'), $bird, 'time');

        $bird->merits()->create(['type' => MeritType::Superset, 'level' => 'olr', 'year' => 2024]);
        $this->assertSame([MeritType::Superset->value], $this->suggested($bird));

        $bird->merits()->create(['type' => MeritType::Superset, 'level' => 'olr', 'year' => 2025]);
        $this->assertSame([], $this->suggested($bird));
    }

    public function test_cannot_accept_suggestions_for_another_users_bird(): void
    {
        $other = User::factory()->create();
        $otherRace = OlrRace::create(['user_id' => $other->id, 'name' => 'Other OLR']);
        $season = $otherRace->seasons()->create(['name' => 'S', 'year' => 2025, 'status' => 'completed']);
        $bird = $this->bird('THEIRS', [], $other);
        foreach (['Lap 1', 'Lap 2'] as $name) {
            $race = $season->races()->create(['name' => $name, 'distance_unit' => 'km']);
            $season->entries()->syncWithoutDetaching([$bird->id]);
            $race->results()->attach($bird->id, ['arrival_time' => '10:00:00', 'did_not_arrive' => false]);
        }

        $this->actingAs($this->user)
            ->post(route('merit-suggestions.accept'), ['keys' => ["{$bird->id}:superset:olr_season:{$season->id}"]]);

        $this->assertSame(0, PigeonMerit::count());
    }

    public function test_suggestion_keys_are_validated(): void
    {
        $this->actingAs($this->user)
            ->post(route('merit-suggestions.accept'), ['keys' => ['drop table']])
            ->assertSessionHasErrors('keys.0');
    }

    public function test_pigeon_page_shows_suggestions(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $this->record($this->race($season, 'Lap 1'), $bird, 1);

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $bird))
            ->assertInertia(fn (Assert $page) => $page
                ->has('meritSuggestions', 1)
                ->where('meritSuggestions.0.merit.type', 'race_winner')
                ->where('parentPair', null));
    }

    public function test_season_page_shows_standings(): void
    {
        $season = $this->season();
        $lap1 = $this->race($season, 'Lap 1');
        $lap2 = $this->race($season, 'Lap 2');
        $bird = $this->bird('PHA-1');
        $this->record($lap1, $bird, 3);
        $this->record($lap2, $bird, 'time');
        $season->entries()->updateExistingPivot($bird->id, ['overall_position' => 2]);

        $this->actingAs($this->user)
            ->get(route('olr-races.seasons.show', [$this->olr, $season]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('olr-races/seasons/Show')
                ->where("standings.{$bird->id}.laps", 2)
                ->where("standings.{$bird->id}.clocked", 2)
                ->where("standings.{$bird->id}.best", 3)
                ->where("standings.{$bird->id}.overall_position", 2)
                ->has("standings.{$bird->id}.suggestions", 2));
    }

    public function test_overall_position_is_saved_without_clearing_notes(): void
    {
        $season = $this->season();
        $bird = $this->bird('PHA-1');
        $season->entries()->attach($bird->id, ['notes' => 'Strong on long laps']);

        $this->actingAs($this->user)
            ->patch(route('olr-races.seasons.entries.update', [$this->olr, $season, $bird]), ['overall_position' => 7])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->patch(route('olr-races.seasons.entries.update', [$this->olr, $season, $bird]), ['status' => 'breeding']);

        $entry = $season->entries()->first()->pivot;
        $this->assertSame(7, (int) $entry->overall_position);
        $this->assertSame('Strong on long laps', $entry->notes);
        $this->assertSame('breeding', $bird->fresh()->status);
    }

    public function test_races_store_type_and_birds_released(): void
    {
        $season = $this->season('active');

        $this->actingAs($this->user)
            ->post(route('olr-races.seasons.races.store', [$this->olr, $season]), [
                'name' => 'Final',
                'distance_unit' => 'km',
                'race_type' => 'final',
                'birds_released' => 950,
            ])
            ->assertRedirect();

        $race = $season->races()->sole();
        $this->assertSame('final', $race->race_type->value);
        $this->assertSame(950, $race->birds_released);

        $this->actingAs($this->user)
            ->patch(route('olr-races.seasons.races.update', [$this->olr, $season, $race]), [
                'name' => 'Final',
                'distance_unit' => 'km',
                'race_type' => 'sprint',
            ])
            ->assertSessionHasErrors('race_type');

        // Races added without a type are regular laps
        $this->actingAs($this->user)
            ->post(route('olr-races.seasons.races.store', [$this->olr, $season]), ['name' => 'Lap 1', 'distance_unit' => 'km']);
        $this->assertSame('regular', $season->races()->where('name', 'Lap 1')->sole()->race_type->value);
    }
}
