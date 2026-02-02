<?php

namespace Tests\Feature;

use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PigeonTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_pigeon_can_be_created_with_all_status_values(): void
    {
        $statuses = ['stock', 'racing', 'breeding', 'injured', 'deceased', 'missing', 'flyaway'];

        foreach ($statuses as $status) {
            $pigeon = Pigeon::create([
                'user_id' => $this->user->id,
                'name' => 'Test Pigeon',
                'gender' => 'male',
                'status' => $status,
                'ring_number' => 'USA-2025-' . rand(10000, 99999),
            ]);

            $this->assertDatabaseHas('pigeons', [
                'id' => $pigeon->id,
                'status' => $status,
            ]);
        }
    }

    public function test_pigeon_table_does_not_have_pigeon_status_column(): void
    {
        $pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        // This should fail if pigeon_status column exists
        $columns = \DB::getSchemaBuilder()->getColumnListing('pigeons');
        $this->assertNotContains('pigeon_status', $columns);
    }

    public function test_pigeon_table_does_not_have_race_type_column(): void
    {
        $pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        // This should fail if race_type column exists
        $columns = \DB::getSchemaBuilder()->getColumnListing('pigeons');
        $this->assertNotContains('race_type', $columns);
    }

    public function test_pigeon_can_be_queried_with_status_field(): void
    {
        Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Racing Pigeon',
            'gender' => 'male',
            'status' => 'racing',
        ]);

        Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Breeding Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
        ]);

        $racingPigeons = Pigeon::where('status', 'racing')->get();
        $this->assertCount(1, $racingPigeons);
        $this->assertEquals('Racing Pigeon', $racingPigeons->first()->name);

        $breedingPigeons = Pigeon::where('status', 'breeding')->get();
        $this->assertCount(1, $breedingPigeons);
        $this->assertEquals('Breeding Pigeon', $breedingPigeons->first()->name);
    }

    public function test_pigeon_status_can_be_updated(): void
    {
        $pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        $pigeon->update(['status' => 'racing']);

        $this->assertDatabaseHas('pigeons', [
            'id' => $pigeon->id,
            'status' => 'racing',
        ]);
    }

    public function test_multiple_pigeons_can_be_updated_at_once(): void
    {
        $pigeon1 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Pigeon 1',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        $pigeon2 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Pigeon 2',
            'gender' => 'female',
            'status' => 'stock',
        ]);

        Pigeon::whereIn('id', [$pigeon1->id, $pigeon2->id])
            ->update(['status' => 'breeding']);

        $this->assertDatabaseHas('pigeons', [
            'id' => $pigeon1->id,
            'status' => 'breeding',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $pigeon2->id,
            'status' => 'breeding',
        ]);
    }

    public function test_pigeons_can_be_selected_with_status_column(): void
    {
        Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Test Pigeon',
            'gender' => 'male',
            'status' => 'racing',
            'ring_number' => 'USA-2025-12345',
        ]);

        // This should not throw a database error
        $pigeons = Pigeon::select('id', 'name', 'ring_number', 'status')->get();
        
        $this->assertCount(1, $pigeons);
        $this->assertEquals('racing', $pigeons->first()->status);
    }
}
