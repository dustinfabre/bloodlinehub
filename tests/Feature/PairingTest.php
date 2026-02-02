<?php

namespace Tests\Feature;

use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_creating_pairing_updates_pigeon_status_to_breeding(): void
    {
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Male Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Female Pigeon',
            'gender' => 'female',
            'status' => 'stock',
        ]);

        $this->actingAs($this->user);

        $response = $this->post(route('pairings.store'), [
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'pair_name' => 'Test Pairing',
        ]);

        $response->assertRedirect();

        // Both pigeons should now have status 'breeding', not 'pigeon_status'
        $this->assertDatabaseHas('pigeons', [
            'id' => $sire->id,
            'status' => 'breeding',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $dam->id,
            'status' => 'breeding',
        ]);
    }

    public function test_ending_pairing_session_updates_pigeon_status_to_stock(): void
    {
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Male Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Female Pigeon',
            'gender' => 'female',
            'status' => 'stock',
        ]);

        $pairing = Pairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'pair_name' => 'Test Pairing',
            'status' => 'active',
            'current_clutch_number' => 1,
            'started_at' => now(),
        ]);

        // Update pigeons to breeding status
        Pigeon::whereIn('id', [$sire->id, $dam->id])
            ->update(['status' => 'breeding']);

        $this->actingAs($this->user);

        $response = $this->post(route('pairings.end-session', $pairing));

        $response->assertRedirect();

        // Both pigeons should now have status 'stock', not 'pigeon_status'
        $this->assertDatabaseHas('pigeons', [
            'id' => $sire->id,
            'status' => 'stock',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $dam->id,
            'status' => 'stock',
        ]);
    }

    public function test_deleting_active_pairing_updates_pigeon_status_to_stock(): void
    {
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Male Pigeon',
            'gender' => 'male',
            'status' => 'breeding',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Female Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
        ]);

        $pairing = Pairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'pair_name' => 'Test Pairing',
            'status' => 'active',
            'current_clutch_number' => 1,
            'started_at' => now(),
        ]);

        $this->actingAs($this->user);

        $response = $this->delete(route('pairings.destroy', $pairing));

        $response->assertRedirect();

        // Both pigeons should now have status 'stock'
        $this->assertDatabaseHas('pigeons', [
            'id' => $sire->id,
            'status' => 'stock',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $dam->id,
            'status' => 'stock',
        ]);
    }

    public function test_pigeon_status_update_does_not_use_pigeon_status_column(): void
    {
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Male Pigeon',
            'gender' => 'male',
            'status' => 'stock',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Female Pigeon',
            'gender' => 'female',
            'status' => 'stock',
        ]);

        // This should not throw an error about pigeon_status column
        Pigeon::whereIn('id', [$sire->id, $dam->id])
            ->update(['status' => 'breeding']);

        $this->assertDatabaseHas('pigeons', [
            'id' => $sire->id,
            'status' => 'breeding',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $dam->id,
            'status' => 'breeding',
        ]);

        // Now update back to stock
        Pigeon::whereIn('id', [$sire->id, $dam->id])
            ->update(['status' => 'stock']);

        $this->assertDatabaseHas('pigeons', [
            'id' => $sire->id,
            'status' => 'stock',
        ]);

        $this->assertDatabaseHas('pigeons', [
            'id' => $dam->id,
            'status' => 'stock',
        ]);
    }
}
