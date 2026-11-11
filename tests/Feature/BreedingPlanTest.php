<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\PlannedPairing;
use App\Models\User;
use App\Services\Merits\GoldenPairs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Golden Pairs (worked out from the offspring's merits) and the breeding plan (saved test pairs).
 */
class BreedingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->premium()->create();
    }

    private function bird(string $ring, array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    private function child(Pigeon $sire, Pigeon $dam, string $ring, ?MeritType $merit = null): Pigeon
    {
        $child = $this->bird($ring, ['sire_id' => $sire->id, 'dam_id' => $dam->id]);
        if ($merit) {
            $child->merits()->create(['type' => $merit, 'year' => 2025]);
        }

        return $child;
    }

    public function test_a_pair_with_two_winning_youngsters_is_golden(): void
    {
        $sire = $this->bird('COCK', ['gender' => 'male']);
        $dam = $this->bird('HEN', ['gender' => 'female']);
        $otherHen = $this->bird('HEN-2', ['gender' => 'female']);

        $this->child($sire, $dam, 'Y1', MeritType::OverallWinner);
        $this->child($sire, $dam, 'Y2', MeritType::Superset);
        $this->child($sire, $dam, 'Y3');
        $this->child($sire, $otherHen, 'Y4', MeritType::RaceWinner);

        $pairs = app(GoldenPairs::class);
        $pair = $pairs->forPair($this->user->id, $sire->id, $dam->id);
        $this->assertSame(3, $pair['offspring']);
        $this->assertSame(1, $pair['winner_count']);
        $this->assertFalse($pair['golden']);

        $this->child($sire, $dam, 'Y5', MeritType::FinalRaceWinner);

        $pair = $pairs->forPair($this->user->id, $sire->id, $dam->id);
        $this->assertTrue($pair['golden']);
        $this->assertSame(2, $pair['winner_count']);
        // Highest merit first
        $this->assertSame(['Y1', 'Y5'], array_column($pair['winners'], 'ring_number'));
        $this->assertFalse($pairs->forPair($this->user->id, $sire->id, $otherHen->id)['golden']);
    }

    public function test_golden_pair_shows_on_pairings_lineage_and_offspring(): void
    {
        $sire = $this->bird('COCK', ['gender' => 'male']);
        $dam = $this->bird('HEN', ['gender' => 'female']);
        $winner = $this->child($sire, $dam, 'Y1', MeritType::RaceWinner);
        $this->child($sire, $dam, 'Y2', MeritType::HotSpotWinner);
        $pairing = Pairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $sire->id,
            'dam_id' => $dam->id,
            'pair_name' => 'Gold',
            'status' => 'active',
            'current_clutch_number' => 1,
            'started_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('pairings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pairings.data.0.golden_pair.golden', true)
                ->where('pairings.data.0.golden_pair.winner_count', 2));

        $this->actingAs($this->user)
            ->get(route('pairings.show', $pairing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('goldenPair.golden', true)
                ->has('goldenPair.winners', 2));

        $this->actingAs($this->user)
            ->get(route('lineage.index', ['pigeon' => $sire->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('family.mates.0.golden', true)
                ->where('family.mates.0.winners', 2));

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $winner))
            ->assertInertia(fn (Assert $page) => $page->where('parentPair.golden', true));

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $sire->id, 'dam' => $dam->id]))
            ->assertInertia(fn (Assert $page) => $page->where('analysis.golden_pair.golden', true));
    }

    public function test_a_test_pair_can_be_saved_to_the_plan(): void
    {
        $sire = $this->bird('COCK', ['gender' => 'male']);
        $dam = $this->bird('HEN', ['gender' => 'female']);

        $this->actingAs($this->user)
            ->post(route('planned-pairings.store'), ['sire_id' => $sire->id, 'dam_id' => $dam->id, 'notes' => 'After the derby'])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Saving the same pair again updates the note rather than adding a second plan
        $this->actingAs($this->user)
            ->post(route('planned-pairings.store'), ['sire_id' => $sire->id, 'dam_id' => $dam->id, 'notes' => 'Before the derby']);

        $plan = PlannedPairing::sole();
        $this->assertSame('Before the derby', $plan->notes);
        $this->assertSame($this->user->id, $plan->user_id);

        $this->actingAs($this->user)
            ->patch(route('planned-pairings.update', $plan), ['notes' => 'Changed my mind'])
            ->assertRedirect();
        $this->assertSame('Changed my mind', $plan->fresh()->notes);

        $this->actingAs($this->user)
            ->delete(route('planned-pairings.destroy', $plan))
            ->assertRedirect();
        $this->assertSame(0, PlannedPairing::count());
    }

    public function test_plans_only_take_the_users_own_two_different_birds(): void
    {
        $sire = $this->bird('COCK', ['gender' => 'male']);
        $theirs = $this->bird('THEIRS', ['gender' => 'female'], User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('planned-pairings.store'), ['sire_id' => $sire->id, 'dam_id' => $theirs->id])
            ->assertSessionHasErrors('dam_id');

        $this->actingAs($this->user)
            ->post(route('planned-pairings.store'), ['sire_id' => $sire->id, 'dam_id' => $sire->id])
            ->assertSessionHasErrors('dam_id');

        $this->assertSame(0, PlannedPairing::count());
    }

    public function test_other_users_cannot_change_a_plan(): void
    {
        $plan = PlannedPairing::create([
            'user_id' => $this->user->id,
            'sire_id' => $this->bird('COCK')->id,
            'dam_id' => $this->bird('HEN')->id,
        ]);
        // Premium, so it's the ownership check that turns them away
        $intruder = User::factory()->premium()->create();

        $this->actingAs($intruder)->patch(route('planned-pairings.update', $plan), ['notes' => 'x'])->assertNotFound();
        $this->actingAs($intruder)->delete(route('planned-pairings.destroy', $plan))->assertNotFound();
        $this->assertNotNull($plan->fresh());
    }

    public function test_pairing_analysis_page_lists_the_plan_with_coi_and_conflicts(): void
    {
        $grandsire = $this->bird('GS', ['gender' => 'male']);
        $grandam = $this->bird('GD', ['gender' => 'female']);
        // Half siblings: COI of their youngsters 12.5%
        $cock = $this->bird('COCK', ['gender' => 'male', 'sire_id' => $grandsire->id, 'dam_id' => $grandam->id]);
        $hen = $this->bird('HEN', ['gender' => 'female', 'sire_id' => $grandsire->id]);
        $otherHen = $this->bird('HEN-2', ['gender' => 'female', 'status' => 'deceased']);

        PlannedPairing::create(['user_id' => $this->user->id, 'sire_id' => $cock->id, 'dam_id' => $hen->id, 'notes' => 'Line breed on GS']);
        PlannedPairing::create(['user_id' => $this->user->id, 'sire_id' => $cock->id, 'dam_id' => $otherHen->id]);
        PlannedPairing::create(['user_id' => User::factory()->create()->id, 'sire_id' => $grandsire->id, 'dam_id' => $grandam->id]);

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $cock->id, 'dam' => $hen->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pairing-analysis/Index')
                ->has('plans', 2)
                ->where('plan.notes', 'Line breed on GS')
                ->where('plans.1.sire.id', $cock->id)
                ->where('plans.1.dam.id', $hen->id)
                ->where('plans.1.coi', 0.125)
                ->where('plans.1.assessment.level', 'close')
                ->where('plans.1.sire_plans', 2)
                ->where('plans.1.dam_plans', 1)
                ->where('plans.0.dam.status', 'deceased')
                ->where('plans.0.coi', 0)
                ->where('plans.0.golden', false));

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('plan', null)
                ->has('plans', 2));
    }
}
