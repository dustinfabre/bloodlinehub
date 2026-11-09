<?php

namespace Tests\Feature;

use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Changing a pigeon's status from the status menu (pigeon cards and the pigeon page), and flyaway merged into
 * missing.
 */
class PigeonStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function pigeon(string $ring, string $status = 'racing', ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => $status,
            'ring_number' => $ring,
            'name' => 'Blue Bar Cock',
            'notes' => 'Keep these notes',
        ]);
    }

    public function test_owner_can_change_only_the_status(): void
    {
        $pigeon = $this->pigeon('PHA-2026-1001');

        $this->actingAs($this->user)
            ->from(route('pigeons.index'))
            ->patch(route('pigeons.status', $pigeon), ['status' => 'deceased'])
            ->assertRedirect(route('pigeons.index'));

        $pigeon->refresh();
        $this->assertSame('deceased', $pigeon->status);
        // Nothing else on the bird is touched
        $this->assertSame('PHA-2026-1001', $pigeon->ring_number);
        $this->assertSame('Blue Bar Cock', $pigeon->name);
        $this->assertSame('Keep these notes', $pigeon->notes);
    }

    public function test_a_gone_bird_can_be_brought_back_to_the_loft(): void
    {
        $pigeon = $this->pigeon('PHA-2026-1002', 'missing');

        $this->actingAs($this->user)
            ->patch(route('pigeons.status', $pigeon), ['status' => 'racing'])
            ->assertSessionHasNoErrors();

        $this->assertSame('racing', $pigeon->refresh()->status);
    }

    public function test_status_must_be_a_known_one(): void
    {
        $pigeon = $this->pigeon('PHA-2026-1003');

        foreach (['flyaway', 'sold', ''] as $status) {
            $this->actingAs($this->user)
                ->patch(route('pigeons.status', $pigeon), ['status' => $status])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame('racing', $pigeon->refresh()->status);
    }

    public function test_cannot_change_the_status_of_someone_elses_bird(): void
    {
        $theirs = $this->pigeon('PHA-2026-1004', owner: User::factory()->create());

        $this->actingAs($this->user)
            ->patch(route('pigeons.status', $theirs), ['status' => 'deceased'])
            ->assertNotFound();

        $this->assertSame('racing', $theirs->refresh()->status);
    }

    public function test_guests_cannot_change_a_status(): void
    {
        $pigeon = $this->pigeon('PHA-2026-1005');

        $this->patch(route('pigeons.status', $pigeon), ['status' => 'deceased'])
            ->assertRedirect(route('login'));

        $this->assertSame('racing', $pigeon->refresh()->status);
    }

    public function test_flyaway_birds_become_missing(): void
    {
        $flyaway = $this->pigeon('PHA-2026-1006');
        $other = $this->pigeon('PHA-2026-1007', 'deceased');
        // Written straight to the table: flyaway is no longer a status the app accepts
        DB::table('pigeons')->where('id', $flyaway->id)->update(['status' => 'flyaway']);

        $migration = require database_path('migrations/2026_11_07_000001_merge_flyaway_into_missing.php');
        $migration->up();

        $this->assertSame('missing', $flyaway->refresh()->status);
        $this->assertSame('deceased', $other->refresh()->status);
        $this->assertNotContains('flyaway', Pigeon::STATUSES);
    }
}
