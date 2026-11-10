<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Merits on pedigrees: loft birds' merits on the chart, and merits recorded on ancestors that are not in the loft.
 */
class PedigreeMeritTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function pigeon(string $ring, array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    private function saveAncestor(Pigeon $subject, string $path, array $details)
    {
        return $this->actingAs($this->user)->put(route('pigeons.saveAncestor', $subject), [
            'anchor_id' => $subject->id,
            'path' => $path,
            ...$details,
        ]);
    }

    public function test_external_parents_and_ancestors_keep_their_merits(): void
    {
        $subject = $this->pigeon('BE-2024-1');

        $this->saveAncestor($subject, 'S', [
            'ring_number' => 'BE-2018-777',
            'name' => 'KANNIBAAL',
            'merits' => [
                ['type' => 'super_breeder'],
                ['type' => 'ace_pigeon', 'level' => 'national', 'year' => 2019, 'detail' => '1st Nat Ace Middle Distance'],
            ],
        ])->assertSessionHasNoErrors();

        $this->saveAncestor($subject, 'SD', [
            'ring_number' => 'BE-2015-555',
            'merits' => [['type' => 'olympiad', 'year' => 2017, 'level' => '', 'detail' => ' ']],
        ])->assertSessionHasNoErrors();

        $subject->refresh();
        $this->assertSame([
            ['type' => 'super_breeder', 'level' => null, 'year' => null, 'detail' => null],
            ['type' => 'ace_pigeon', 'level' => 'national', 'year' => 2019, 'detail' => '1st Nat Ace Middle Distance'],
        ], $subject->sire_merits);
        $this->assertSame(
            [['type' => 'olympiad', 'level' => null, 'year' => 2017, 'detail' => null]],
            $subject->pedigreeAncestors()->where('path', 'SD')->sole()->merits,
        );

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.merits.0.code', 'SB')
                ->where('tree.sire.merits.1.short', "ACE Nat '19")
                ->where('tree.sire.merits.1.detail', '1st Nat Ace Middle Distance')
                ->where('tree.sire.dam.merits.0.type', 'olympiad')
                ->where('tree.dam', null));

        // Saving without merits clears them
        $this->saveAncestor($subject, 'S', ['ring_number' => 'BE-2018-777', 'name' => 'KANNIBAAL']);
        $this->assertNull($subject->fresh()->sire_merits);
    }

    public function test_external_merits_are_validated(): void
    {
        $subject = $this->pigeon('BE-2024-1');

        $this->saveAncestor($subject, 'S', [
            'ring_number' => 'X',
            'merits' => [['type' => 'golden_breeder'], ['type' => 'race_winner', 'year' => 1800]],
        ])->assertSessionHasErrors(['merits.0.type', 'merits.1.year']);
    }

    public function test_loft_birds_show_their_merits_on_the_chart(): void
    {
        $sire = $this->pigeon('BE-2020-1', ['gender' => 'male']);
        $sire->merits()->create(['type' => MeritType::OverallWinner, 'level' => 'derby', 'year' => 2024]);
        $subject = $this->pigeon('BE-2024-1', ['sire_id' => $sire->id]);
        $subject->merits()->create(['type' => MeritType::Superset, 'year' => 2025]);

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.merits.0.code', 'SS')
                ->where('tree.sire.merits.0.short', "OA Derby '24")
                ->where('tree.sire.merits.0.id', $sire->merits()->sole()->id));

        // Public charts show merits but not their internal ids
        $subject->pedigreeRecord()->update(['is_public' => true]);

        $this->get(route('pedigrees.shared', $subject->pedigreeRecord()->serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.merits.0.short', "OA Derby '24")
                ->where('tree.sire.merits.0.id', null));
    }

    public function test_external_ancestor_merits_count_in_lineage_and_pairing_analysis(): void
    {
        // Two birds whose sires are both the same bought-in cock (matched by ring number)
        $cock = $this->pigeon('C1', ['gender' => 'male', 'sire_ring_number' => 'BE 2018-777', 'sire_name' => 'KANNIBAAL']);
        $hen = $this->pigeon('H1', ['gender' => 'female', 'sire_ring_number' => 'BE-2018-777']);

        $this->saveAncestor($cock, 'S', ['ring_number' => 'BE 2018-777', 'name' => 'KANNIBAAL', 'merits' => [['type' => 'super_breeder']]]);

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $cock->id, 'dam' => $hen->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.coi', 0.125)
                ->where('analysis.common_ancestors.0.bird.external', true)
                ->where('analysis.common_ancestors.0.bird.merits.0.code', 'SB')
                ->where('analysis.common_ancestors.0.bird.merit_score', MeritType::SuperBreeder->weight()));
    }

    public function test_editor_page_has_merit_options(): void
    {
        $subject = $this->pigeon('BE-2024-1');

        $this->actingAs($this->user)
            ->get(route('pigeons.editPedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page
                ->has('meritTypes', count(MeritType::cases()))
                ->has('meritLevels'));
    }
}
