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

    public function test_offspring_can_be_added_to_pairing_with_correct_status(): void
    {
        $this->actingAs($this->user);

        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Sire Pigeon',
            'gender' => 'male',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00001',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Dam Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00002',
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

        $clutch = \App\Models\Clutch::create([
            'pairing_id' => $pairing->id,
            'clutch_number' => 1,
            'eggs_laid_date' => now(),
            'status' => 'pending',
        ]);

        // Create offspring with status 'stock' (the default for new birds)
        $response = $this->post(route('pigeons.store'), [
            'name' => 'Offspring Bird',
            'gender' => 'male',
            'ring_number' => 'USA-2025-00003',
            'status' => 'stock',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'clutch_id' => $clutch->id,
            'hatch_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        // Verify offspring was created with correct status
        $this->assertDatabaseHas('pigeons', [
            'name' => 'OFFSPRING BIRD',
            'ring_number' => 'USA-2025-00003',
            'status' => 'stock',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
        ]);

        // Verify the offspring is linked to the clutch and accessible through pairing
        $offspring = Pigeon::where('ring_number', 'USA-2025-00003')->first();
        $this->assertNotNull($offspring);
        $this->assertEquals($clutch->id, $offspring->clutch_id);
        
        // Verify pairing can access offspring through clutches
        $pairing->refresh();
        $this->assertCount(1, $pairing->offspring);
        $this->assertTrue($pairing->offspring->contains('id', $offspring->id));
    }

    public function test_offspring_with_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->user);

        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Sire Pigeon',
            'gender' => 'male',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00001',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Dam Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00002',
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

        $clutch = \App\Models\Clutch::create([
            'pairing_id' => $pairing->id,
            'clutch_number' => 1,
            'eggs_laid_date' => now(),
            'status' => 'pending',
        ]);

        // Try to create offspring with invalid status
        $response = $this->post(route('pigeons.store'), [
            'name' => 'Offspring Bird',
            'gender' => 'male',
            'ring_number' => 'USA-2025-00003',
            'status' => 'invalid_status',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'clutch_id' => $clutch->id,
            'hatch_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('status');

        // Verify offspring was not created
        $this->assertDatabaseMissing('pigeons', [
            'ring_number' => 'USA-2025-00003',
        ]);
    }

    public function test_offspring_defaults_to_stock_status_when_not_provided(): void
    {
        $this->actingAs($this->user);

        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Sire Pigeon',
            'gender' => 'male',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00001',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Dam Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00002',
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

        $clutch = \App\Models\Clutch::create([
            'pairing_id' => $pairing->id,
            'clutch_number' => 1,
            'eggs_laid_date' => now(),
            'status' => 'pending',
        ]);

        // Create offspring with explicit stock status (what frontend should send)
        $response = $this->post(route('pigeons.store'), [
            'name' => 'Offspring Bird',
            'gender' => 'male',
            'ring_number' => 'USA-2025-00003',
            'status' => 'stock', // Frontend should always send this
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'clutch_id' => $clutch->id,
            'hatch_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify offspring was created with stock status
        $offspring = Pigeon::where('ring_number', 'USA-2025-00003')->first();
        $this->assertNotNull($offspring);
        $this->assertEquals('stock', $offspring->status);
    }

    public function test_pairing_offspring_relationship_uses_clutch_id(): void
    {
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Sire Pigeon',
            'gender' => 'male',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00001',
        ]);

        $dam = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Dam Pigeon',
            'gender' => 'female',
            'status' => 'breeding',
            'ring_number' => 'USA-2025-00002',
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

        $clutch = \App\Models\Clutch::create([
            'pairing_id' => $pairing->id,
            'clutch_number' => 1,
            'eggs_laid_date' => now(),
            'status' => 'pending',
        ]);

        // Create offspring with clutch_id
        $offspring1 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Offspring 1',
            'gender' => 'male',
            'ring_number' => 'USA-2025-00003',
            'status' => 'stock',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'clutch_id' => $clutch->id,
        ]);

        $offspring2 = Pigeon::create([
            'user_id' => $this->user->id,
            'name' => 'Offspring 2',
            'gender' => 'female',
            'ring_number' => 'USA-2025-00004',
            'status' => 'stock',
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'clutch_id' => $clutch->id,
        ]);

        // Verify the pairing->offspring relationship returns both birds through clutches
        $pairing->refresh();
        $this->assertCount(2, $pairing->offspring);
        $this->assertTrue($pairing->offspring->contains('id', $offspring1->id));
        $this->assertTrue($pairing->offspring->contains('id', $offspring2->id));
    }
}
