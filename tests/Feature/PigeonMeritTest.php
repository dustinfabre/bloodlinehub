<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PigeonMeritTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function pigeon(string $ring = 'PHA-2024-1', ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => 'racing',
            'ring_number' => $ring,
        ]);
    }

    public function test_owner_can_add_a_merit(): void
    {
        $pigeon = $this->pigeon();

        $this->actingAs($this->user)
            ->post(route('pigeons.merits.store', $pigeon), [
                'type' => 'superset',
                'level' => 'derby',
                'year' => 2025,
                'detail' => '5-lap Rockdove OLR',
            ])
            ->assertRedirect();

        $merit = $pigeon->merits()->sole();
        $this->assertSame(MeritType::Superset, $merit->type);
        $this->assertSame("SS Derby '25", $merit->summary()['short']);
        $this->assertSame('Superset Winner', $merit->summary()['label']);
    }

    public function test_merit_type_must_come_from_the_fixed_list(): void
    {
        $pigeon = $this->pigeon();

        $this->actingAs($this->user)
            ->post(route('pigeons.merits.store', $pigeon), ['type' => 'my_custom_title'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $pigeon->merits()->count());
    }

    public function test_cannot_add_or_remove_merits_on_another_users_pigeon(): void
    {
        $theirs = $this->pigeon('THEIRS', User::factory()->create());
        $merit = $theirs->merits()->create(['type' => MeritType::RaceWinner]);

        $this->actingAs($this->user)
            ->post(route('pigeons.merits.store', $theirs), ['type' => 'race_winner'])
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('pigeons.merits.destroy', [$theirs, $merit]))
            ->assertNotFound();

        $this->assertSame(1, $theirs->merits()->count());
    }

    public function test_owner_can_remove_a_merit(): void
    {
        $pigeon = $this->pigeon();
        $merit = $pigeon->merits()->create(['type' => MeritType::Finisher]);

        $this->actingAs($this->user)
            ->delete(route('pigeons.merits.destroy', [$pigeon, $merit]))
            ->assertRedirect();

        $this->assertModelMissing($merit);
    }

    public function test_index_can_filter_by_merit_and_shows_merits(): void
    {
        $winner = $this->pigeon('WINNER');
        $winner->merits()->create(['type' => MeritType::OverallWinner, 'year' => 2025]);
        $breeder = $this->pigeon('BREEDER');
        $breeder->merits()->create(['type' => MeritType::SuperBreeder]);
        $this->pigeon('PLAIN');

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['merit' => ['overall_winner', 'super_breeder']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pigeons.data', 2)
                ->where('filters.merit', ['overall_winner', 'super_breeder'])
                ->has('meritTypes', count(MeritType::cases()))
            );

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $winner))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pigeon.merits.0.code', 'OA')
                ->has('meritLevels')
            );
    }
}
