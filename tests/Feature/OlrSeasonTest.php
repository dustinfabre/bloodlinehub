<?php

namespace Tests\Feature;

use App\Models\OlrRace;
use App\Models\OlrSeason;
use App\Models\OlrSeasonRace;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OlrSeasonTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected OlrRace $olrRace;
    protected OlrSeason $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        $this->olrRace = OlrRace::create([
            'user_id' => $this->user->id,
            'name' => 'Test OLR Race',
            'description' => 'Test description',
        ]);

        $this->season = OlrSeason::create([
            'olr_race_id' => $this->olrRace->id,
            'name' => '2025 Season',
            'year' => 2025,
        ]);
    }

    public function test_olr_season_can_load_entries_without_pigeon_status_column(): void
    {
        $pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Racing Pigeon',
            'gender' => 'male',
            'status' => 'racing',
            'ring_number' => 'USA-2025-12345',
        ]);

        $this->season->entries()->attach($pigeon->id, [
            'entry_number' => '001',
        ]);

        // This should not throw an error about missing pigeon_status column
        $this->season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status');
        }]);

        $this->assertCount(1, $this->season->entries);
        $this->assertEquals('racing', $this->season->entries->first()->status);
    }

    public function test_olr_season_race_can_load_results_without_pigeon_status_column(): void
    {
        $pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Racing Pigeon',
            'gender' => 'male',
            'status' => 'racing',
            'ring_number' => 'USA-2025-12345',
        ]);

        $this->season->entries()->attach($pigeon->id, [
            'entry_number' => '001',
        ]);

        $race = OlrSeasonRace::create([
            'olr_season_id' => $this->season->id,
            'name' => 'Race 1',
            'release_point' => 'Test Location',
            'distance' => 500,
            'distance_unit' => 'km',
            'race_date' => now(),
        ]);

        $race->results()->attach($pigeon->id, [
            'position' => 1,
            'arrival_time' => now(),
            'speed' => 1500.50,
        ]);

        // This should not throw an error about missing pigeon_status column
        $race->load(['results' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status');
        }]);

        $this->assertCount(1, $race->results);
        $this->assertEquals('racing', $race->results->first()->status);
    }

    public function test_available_pigeons_query_without_race_type_column(): void
    {
        // Create various pigeons with different statuses
        $racingPigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Racing Pigeon',
            'gender' => 'male',
            'status' => 'racing',
        ]);

        $deceasedPigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Deceased Pigeon',
            'gender' => 'male',
            'status' => 'deceased',
        ]);

        $stockPigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Stock Pigeon',
            'gender' => 'female',
            'status' => 'stock',
        ]);

        // This query should not use race_type column
        $availablePigeons = Pigeon::where('user_id', $this->user->id)
            ->whereNotIn('status', ['deceased', 'missing', 'flyaway'])
            ->get();

        $this->assertCount(2, $availablePigeons);
        $this->assertTrue($availablePigeons->contains('id', $racingPigeon->id));
        $this->assertTrue($availablePigeons->contains('id', $stockPigeon->id));
        $this->assertFalse($availablePigeons->contains('id', $deceasedPigeon->id));
    }

    public function test_olr_season_controller_show_method_compatible_queries(): void
    {
        $this->actingAs($this->user);

        $pigeon1 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon 1',
            'gender' => 'male',
            'status' => 'racing',
            'ring_number' => 'USA-2025-00001',
        ]);

        $pigeon2 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon 2',
            'gender' => 'female',
            'status' => 'deceased',
            'ring_number' => 'USA-2025-00002',
        ]);

        $this->season->entries()->attach($pigeon1->id, ['entry_number' => '001']);

        // Simulate the controller's show method queries
        $this->season->load(['entries' => function ($query) {
            $query->select('pigeons.id', 'pigeons.name', 'pigeons.ring_number', 'pigeons.personal_number', 'pigeons.color', 'pigeons.status');
        }]);

        $availablePigeons = Pigeon::where('user_id', $this->user->id)
            ->whereNotIn('status', ['deceased', 'missing', 'flyaway'])
            ->whereNotIn('id', $this->season->entries->pluck('id'))
            ->get();

        // Should not throw database errors
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->season->entries);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $availablePigeons);
        $this->assertCount(1, $this->season->entries);
        $this->assertCount(0, $availablePigeons); // pigeon1 is already in season, pigeon2 is deceased
    }
}
