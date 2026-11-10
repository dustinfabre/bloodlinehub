<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LineageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function bird(string $ring, ?Pigeon $sire = null, ?Pigeon $dam = null, array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => $ring,
            'sire_id' => $sire?->id,
            'dam_id' => $dam?->id,
            ...$attributes,
        ]);
    }

    private function cock(string $ring, ?Pigeon $sire = null, ?Pigeon $dam = null, array $attributes = []): Pigeon
    {
        return $this->bird($ring, $sire, $dam, ['gender' => 'male', ...$attributes]);
    }

    private function hen(string $ring, ?Pigeon $sire = null, ?Pigeon $dam = null, array $attributes = []): Pigeon
    {
        return $this->bird($ring, $sire, $dam, ['gender' => 'female', ...$attributes]);
    }

    private function graph(): LoftGraph
    {
        return app(LineageService::class)->graph($this->user->id);
    }

    private function coi(Pigeon $sire, Pigeon $dam): float
    {
        return $this->graph()->mating('p:'.$sire->id, 'p:'.$dam->id)['coefficient'];
    }

    public function test_unrelated_birds_have_zero_coi(): void
    {
        $a = $this->cock('A', $this->cock('A-S'), $this->hen('A-D'));
        $b = $this->hen('B', $this->cock('B-S'), $this->hen('B-D'));

        $this->assertSame(0.0, $this->coi($a, $b));
    }

    public function test_half_siblings_give_12_5_percent(): void
    {
        $sire = $this->cock('X');
        $son = $this->cock('SON', $sire, $this->hen('M1'));
        $daughter = $this->hen('DAUGHTER', $sire, $this->hen('M2'));

        $this->assertEqualsWithDelta(0.125, $this->coi($son, $daughter), 1e-9);
    }

    public function test_full_siblings_and_parent_offspring_give_25_percent(): void
    {
        $sire = $this->cock('X');
        $dam = $this->hen('Y');
        $son = $this->cock('SON', $sire, $dam);
        $daughter = $this->hen('DAUGHTER', $sire, $dam);

        $this->assertEqualsWithDelta(0.25, $this->coi($son, $daughter), 1e-9);
        $this->assertEqualsWithDelta(0.25, $this->coi($sire, $daughter), 1e-9);
    }

    public function test_first_cousins_give_6_25_percent(): void
    {
        $gs = $this->cock('GS');
        $gd = $this->hen('GD');
        $brother = $this->cock('BROTHER', $gs, $gd);
        $sister = $this->hen('SISTER', $gs, $gd);
        $cousin1 = $this->cock('C1', $brother, $this->hen('W1'));
        $cousin2 = $this->hen('C2', $this->cock('H2'), $sister);

        $this->assertEqualsWithDelta(0.0625, $this->coi($cousin1, $cousin2), 1e-9);
    }

    public function test_inbred_common_ancestor_raises_coi(): void
    {
        // A is a son of full siblings (F = 0.25); half-siblings through A give 0.125 * (1 + 0.25)
        $gs = $this->cock('GS');
        $gd = $this->hen('GD');
        $a = $this->cock('A', $this->cock('BRO', $gs, $gd), $this->hen('SIS', $gs, $gd));
        $son = $this->cock('SON', $a, $this->hen('M1'));
        $daughter = $this->hen('DAUGHTER', $a, $this->hen('M2'));

        $this->assertEqualsWithDelta(0.25, $this->graph()->inbreeding('p:'.$a->id), 1e-9);
        $this->assertEqualsWithDelta(0.15625, $this->coi($son, $daughter), 1e-9);
    }

    public function test_external_ancestors_are_matched_by_ring_number(): void
    {
        // Both birds record the same bought-in sire, typed slightly differently
        $son = $this->cock('SON', null, $this->hen('M1'), ['sire_name' => 'KANNIBAAL', 'sire_ring_number' => 'BE-2012-123456']);
        $daughter = $this->hen('DAUGHTER', null, $this->hen('M2'), ['sire_ring_number' => 'be 2012 123456']);

        $this->assertEqualsWithDelta(0.125, $this->coi($son, $daughter), 1e-9);
    }

    public function test_deeper_pedigree_ancestors_count_and_can_resolve_to_loft_birds(): void
    {
        $founder = $this->cock('NL-2010-1');
        // SON's sire is external; its sire (SON's grandsire) is recorded as a pedigree ancestor whose ring is a loft bird
        $son = $this->cock('SON', null, $this->hen('M1'), ['sire_ring_number' => 'EXT-1']);
        PedigreeAncestor::create(['pigeon_id' => $son->id, 'path' => 'SS', 'ring_number' => 'NL 2010 1']);
        $daughter = $this->hen('DAUGHTER', $founder, $this->hen('M2'));

        // founder is SON's grandsire and DAUGHTER's sire (half-aunt x nephew): 0.5^(2 + 1 + 1)
        $this->assertEqualsWithDelta(0.0625, $this->coi($son, $daughter), 1e-9);
    }

    public function test_cross_finds_birds_descending_from_both_birds(): void
    {
        // A x B -> AB1, C x D -> CD2, AB1 x CD2 -> ABCD12
        $a = $this->cock('A');
        $b = $this->hen('B');
        $c = $this->cock('C');
        $d = $this->hen('D');
        $ab1 = $this->cock('AB1', $a, $b);
        $cd2 = $this->hen('CD2', $c, $d);
        $abcd12 = $this->cock('ABCD12', $ab1, $cd2);
        $this->cock('AB2', $a, $b);

        $shared = app(LineageService::class)->sharedDescendants($this->graph(), 'p:'.$a->id, 'p:'.$d->id);

        $this->assertCount(1, $shared);
        $this->assertSame('p:'.$abcd12->id, $shared[0]['key']);
        $this->assertSame(2, $shared[0]['from_a']['generation']);
        $this->assertSame(['p:'.$ab1->id], $shared[0]['from_a']['via']);
        $this->assertSame(['p:'.$cd2->id], $shared[0]['from_b']['via']);
        $this->assertEqualsWithDelta(0.25, $shared[0]['from_a']['share'], 1e-9);
        $this->assertFalse($shared[0]['direct']);
    }

    public function test_lineage_page_lists_descendants_by_generation_and_siblings(): void
    {
        $a = $this->cock('A');
        $mate = $this->hen('MATE');
        $child = $this->cock('CHILD', $a, $mate);
        $fullSibling = $this->hen('CHILD-2', $a, $mate);
        $halfSibling = $this->hen('HALF', $a, $this->hen('OTHER'));
        $grandchild = $this->cock('GRAND', $child, $this->hen('G-MATE'));

        $this->actingAs($this->user)
            ->get(route('lineage.index', ['pigeon' => $child->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lineage/Index')
                ->where('bird.ring_number', 'CHILD')
                ->where('family.sire.ring_number', 'A')
                ->where('family.siblings.full.0.ring_number', 'CHILD-2')
                ->where('family.siblings.sire_side.0.ring_number', 'HALF')
                ->has('family.siblings.dam_side', 0)
                ->where('family.descendants.0.label', 'Children')
                ->where('family.descendants.0.birds.0.bird.ring_number', 'GRAND')
                ->where('family.descendants.0.birds.0.other_parent.ring_number', 'G-MATE')
            );

        $this->actingAs($this->user)
            ->get(route('lineage.index', ['pigeon' => $a->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('family.descendants.0.label', 'Children')
                ->has('family.descendants.0.birds', 3)
                ->where('family.descendants.1.label', 'Grandchildren')
                ->where('family.descendants.1.birds.0.bird.ring_number', 'GRAND')
                ->where('family.descendants.1.birds.0.via.0.ring_number', 'CHILD')
                ->where('family.descendants.1.birds.0.share', 0.25)
                ->has('family.mates', 2)
            );
    }

    public function test_pair_mode_ranks_proven_descendants_first(): void
    {
        $a = $this->cock('A');
        $b = $this->hen('B');
        $this->cock('PLAIN', $a, $b);
        $winner = $this->hen('WINNER', $a, $b);
        $winner->merits()->create(['type' => MeritType::OverallWinner, 'year' => 2025]);

        $this->actingAs($this->user)
            ->get(route('lineage.index', ['mode' => 'pair', 'a' => $a->id, 'b' => $b->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shared.total', 2)
                ->where('shared.direct', 2)
                ->where('shared.proven', 1)
                ->where('shared.birds.0.bird.ring_number', 'WINNER')
                ->where('shared.birds.0.bird.merits.0.code', 'OA')
            );
    }

    public function test_pairing_analysis_reports_coi_relationship_and_common_ancestors(): void
    {
        $sire = $this->cock('X');
        $son = $this->cock('SON', $sire, $this->hen('M1'));
        $daughter = $this->hen('DAUGHTER', $sire, $this->hen('M2'));

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $son->id, 'dam' => $daughter->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pairing-analysis/Index')
                ->where('analysis.coi', 0.125)
                ->where('analysis.relationship', 0.25)
                ->where('analysis.assessment.level', 'close')
                ->where('analysis.common_ancestors.0.bird.ring_number', 'X')
                ->where('analysis.common_ancestors.0.lines.0', ['sire' => 'SS', 'dam' => 'DS'])
                ->where('analysis.influential_ancestors.0.bird.ring_number', 'X')
                ->where('analysis.influential_ancestors.0.share', 0.5)
            );
    }

    public function test_other_users_birds_are_ignored(): void
    {
        $mine = $this->cock('MINE');
        $theirs = Pigeon::create(['user_id' => User::factory()->create()->id, 'status' => 'stock', 'ring_number' => 'THEIRS', 'gender' => 'female']);

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $mine->id, 'dam' => $theirs->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sire.ring_number', 'MINE')
                ->where('dam', null)
                ->where('analysis', null)
            );

        $this->actingAs($this->user)
            ->getJson(route('lineage.search', ['q' => 'THEIRS']))
            ->assertOk()
            ->assertJsonCount(0, 'pigeons');
    }

    public function test_search_filters_by_gender_and_term(): void
    {
        $this->cock('PHA-2024-1');
        $this->hen('PHA-2024-2');
        $this->bird('PHA-2024-3');

        $this->actingAs($this->user)
            ->getJson(route('lineage.search', ['q' => 'pha-2024', 'gender' => 'male']))
            ->assertOk()
            ->assertJsonCount(2, 'pigeons')
            ->assertJsonPath('pigeons.0.ring_number', 'PHA-2024-1')
            ->assertJsonPath('pigeons.1.ring_number', 'PHA-2024-3');
    }

    public function test_search_can_leave_out_birds_no_longer_in_the_loft(): void
    {
        $this->cock('PHA-1', attributes: ['status' => 'racing']);
        $this->cock('PHA-2', attributes: ['status' => 'deceased']);
        $this->cock('PHA-3', attributes: ['status' => 'missing']);
        $this->cock('PHA-4', attributes: ['status' => 'missing']);
        $this->cock('PHA-5', attributes: ['status' => 'injured']);

        // Pairing Analysis asks for birds in the loft only
        $this->actingAs($this->user)
            ->getJson(route('lineage.search', ['q' => 'pha', 'in_loft' => 1]))
            ->assertOk()
            ->assertJsonCount(2, 'pigeons')
            ->assertJsonPath('pigeons.0.ring_number', 'PHA-1')
            ->assertJsonPath('pigeons.1.ring_number', 'PHA-5');

        // Lineage still finds every bird, gone ones included
        $this->actingAs($this->user)
            ->getJson(route('lineage.search', ['q' => 'pha']))
            ->assertJsonCount(5, 'pigeons');
    }

    public function test_pairing_analysis_includes_a_sample_pedigree_of_the_youngster(): void
    {
        // The grandsire's outside sire, and that bird's sire: the last column of the youngster's chart
        $grandsire = $this->cock('GS', attributes: ['bloodline' => 'JANSSEN', 'sire_ring_number' => 'OUTSIDE-1']);
        PedigreeAncestor::create(['pigeon_id' => $grandsire->id, 'path' => 'SS', 'ring_number' => 'OUTSIDE-2']);
        $son = $this->cock('SON', $grandsire, $this->hen('M1'), ['bloodline' => 'JANSSEN']);
        $daughter = $this->hen('DAUGHTER', $grandsire, null, ['bloodline' => 'SION']);

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $son->id, 'dam' => $daughter->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sample.tree.id', null)
                ->where('sample.tree.name', 'Youngster')
                ->where('sample.tree.bloodline', 'JANSSEN × SION')
                ->where('sample.tree.sire.ring_number', 'SON')
                ->where('sample.tree.sire.sire.ring_number', 'GS')
                ->where('sample.tree.sire.sire.sire.ring_number', 'OUTSIDE-1')
                ->where('sample.tree.sire.sire.sire.sire.ring_number', 'OUTSIDE-2')
                ->where('sample.tree.sire.sire.sire.sire.sire', null)
                ->where('sample.tree.dam.ring_number', 'DAUGHTER')
                ->where('sample.tree.dam.sire.ring_number', 'GS')
                ->where('sample.tree.dam.dam', null)
                ->where('sample.meta.breeder', $this->user->name)
                ->where('sample.meta.qr_svg', '')
            );
    }

    public function test_sample_pedigree_stops_at_five_generations(): void
    {
        // A line six birds deep: the youngster's chart only reaches the sire's great-grandsire (SSSS)
        $line = $this->cock('G1');
        foreach (['G2', 'G3', 'G4', 'G5'] as $ring) {
            $line = $this->cock($ring, $line);
        }
        $dam = $this->hen('HEN');

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $line->id, 'dam' => $dam->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sample.tree.sire.ring_number', 'G5')
                ->where('sample.tree.sire.sire.sire.sire.ring_number', 'G2')
                ->where('sample.tree.sire.sire.sire.sire.sire', null)
            );
    }

    public function test_no_sample_pedigree_until_both_birds_are_picked(): void
    {
        $cock = $this->cock('C1');

        $this->actingAs($this->user)
            ->get(route('pairing-analysis.index', ['sire' => $cock->id]))
            ->assertInertia(fn (Assert $page) => $page->where('sample', null));
    }

    public function test_coi_assessment_bands(): void
    {
        $level = fn (float $coi) => \App\Services\Lineage\PairingAnalysis::assess($coi)['level'];

        $this->assertSame('outcross', $level(0));
        $this->assertSame('light', $level(1 / 64));
        $this->assertSame('linebreeding', $level(0.0625));
        $this->assertSame('close', $level(0.125));
        $this->assertSame('inbreeding', $level(0.25));
    }
}
