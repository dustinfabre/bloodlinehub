<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A pedigree carries the bird's COI at the foot of the sheet, and the pedigree pages add the Pairing Analysis of the
 * bird's parents and the bird's Lineage: in full on the owner's page, and on the shared page without internal ids,
 * loft numbers, statuses, relatives' photos or private notes.
 */
class PedigreeBreedingReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->premium()->create();
    }

    private function bird(string $ring, string $gender, ?Pigeon $sire = null, ?Pigeon $dam = null, array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->me->id,
            'status' => 'stock',
            'gender' => $gender,
            'ring_number' => $ring,
            'sire_id' => $sire?->id,
            'dam_id' => $dam?->id,
            ...$attributes,
        ]);
    }

    /**
     * A bird bred from half-siblings (COI 12.5%), with a full sister, a son and a mate.
     *
     * @return array{grandsire: Pigeon, sire: Pigeon, dam: Pigeon, bird: Pigeon, sister: Pigeon, mate: Pigeon, son: Pigeon}
     */
    private function family(): array
    {
        $grandsire = $this->bird('GRANDSIRE', 'male');
        $sire = $this->bird('SIRE', 'male', $grandsire, $this->bird('GRANDDAM-1', 'female'));
        $dam = $this->bird('DAM', 'female', $grandsire, $this->bird('GRANDDAM-2', 'female'));
        $bird = $this->bird('BIRD', 'male', $sire, $dam);
        $sister = $this->bird('SISTER', 'female', $sire, $dam, [
            'personal_number' => 'LOFT-7',
            'status' => 'deceased',
            'notes' => 'private loft note',
            'photo_url' => 'https://example.test/media/sister-photo',
        ]);
        $mate = $this->bird('MATE', 'female');
        $son = $this->bird('SON', 'male', $bird, $mate);

        return compact('grandsire', 'sire', 'dam', 'bird', 'sister', 'mate', 'son');
    }

    private function share(Pigeon $pigeon, string $visibility = 'public'): string
    {
        $pigeon->pedigreeRecord()->update(match ($visibility) {
            'public' => ['is_public' => true],
            'password' => ['is_public' => false, 'password' => 'secret-password'],
            default => ['is_public' => false],
        });

        return route('pedigrees.shared', $pigeon->pedigreeRecord()->serial);
    }

    /**
     * The page props as JSON, to check what never reaches the browser.
     */
    private function propsJson(TestResponse $response): string
    {
        return json_encode($response->viewData('page')['props']);
    }

    public function test_pedigree_sheet_carries_the_birds_coi(): void
    {
        ['bird' => $bird] = $this->family();

        $this->actingAs($this->me)
            ->get(route('pigeons.pedigree', $bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.coi.coefficient', 0.125)
                ->where('meta.coi.assessment.level', 'close')
                ->where('meta.coi.common_ancestors', [
                    ['name' => null, 'ring_number' => 'GRANDSIRE', 'contribution' => 0.125],
                ])
                ->where('meta.coi.common_count', 1)
                ->where('meta.coi.generations', 6)
                ->where('meta.coi.complete_generations', 2)
            );
    }

    public function test_coi_is_left_off_the_sheet_when_a_parent_is_unknown(): void
    {
        $bird = $this->bird('BIRD', 'male', $this->bird('SIRE', 'male'));

        $this->actingAs($this->me)
            ->get(route('pigeons.pedigree', $bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('meta.coi', null));
    }

    public function test_shared_pedigree_shows_the_pairing_analysis_of_the_birds_parents(): void
    {
        ['bird' => $bird] = $this->family();

        $this->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pedigrees/Shared')
                ->where('meta.coi.coefficient', 0.125)
                ->where('analysis.coi', 0.125)
                ->where('analysis.relationship', 0.25)
                ->where('analysis.sire.ring_number', 'SIRE')
                ->where('analysis.dam.ring_number', 'DAM')
                ->where('analysis.common_ancestors.0.bird.ring_number', 'GRANDSIRE')
                ->where('analysis.common_ancestors.0.lines.0', ['sire' => 'SS', 'dam' => 'DS'])
                // The bird itself is left out of the other birds from the pair: its sister and its son remain
                ->where('analysis.shared.total', 2)
                ->where('analysis.shared.direct', 1)
                ->where('analysis.shared.birds', fn ($birds) => collect($birds)->pluck('bird.ring_number')->sort()->values()->all() === ['SISTER', 'SON'])
                ->missing('analysis.pairings')
                ->where('generations', 6)
            );
    }

    public function test_shared_pedigree_shows_the_birds_lineage(): void
    {
        ['bird' => $bird] = $this->family();

        $this->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lineage.bird.ring_number', 'BIRD')
                ->where('lineage.family.descendants.0.label', 'Children')
                ->where('lineage.family.descendants.0.birds.0.bird.ring_number', 'SON')
                ->where('lineage.family.descendants.0.birds.0.other_parent.ring_number', 'MATE')
                ->where('lineage.family.siblings.full.0.ring_number', 'SISTER')
                ->where('lineage.family.mates.0.bird.ring_number', 'MATE')
                ->where('lineage.family.mates.0.offspring', 1)
                ->where('descendantGenerations', 6)
                ->where('ownerLinks', null)
            );
    }

    public function test_shared_breeding_report_leaves_out_ids_loft_numbers_statuses_photos_and_notes(): void
    {
        ['bird' => $bird, 'sister' => $sister, 'son' => $son] = $this->family();

        $response = $this->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.sire.id', null)
                ->where('lineage.family.siblings.full.0.id', null)
                ->where('lineage.family.siblings.full.0.personal_number', null)
                ->where('lineage.family.siblings.full.0.status', null)
                ->where('lineage.family.descendants.0.birds.0.other_parent.id', null)
            );

        $json = $this->propsJson($response);

        // Graph keys carry pigeon ids ("p:12"); public keys don't
        $this->assertStringNotContainsString('"p:', $json);
        $this->assertStringNotContainsString('"x:', $json);
        $this->assertStringNotContainsString('LOFT-7', $json);
        $this->assertStringNotContainsString('private loft note', $json);
        $this->assertStringNotContainsString('sister-photo', $json);
        $this->assertStringNotContainsString('deceased', $json);
        $this->assertStringNotContainsString('"id":'.$son->id.',', $json);
        $this->assertStringNotContainsString('"id":'.$sister->id.',', $json);
    }

    public function test_golden_pair_shows_on_a_shared_pedigree_without_the_winners_records(): void
    {
        ['bird' => $bird, 'sire' => $sire, 'dam' => $dam] = $this->family();
        $bird->merits()->create(['type' => MeritType::winners()[0]->value, 'year' => 2025]);
        $this->bird('BROTHER', 'male', $sire, $dam)->merits()->create(['type' => MeritType::winners()[0]->value, 'year' => 2025]);

        $this->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.golden_pair.golden', true)
                ->where('analysis.golden_pair.winner_count', 2)
                ->where('analysis.golden_pair.winners', [])
            );
    }

    public function test_bird_with_one_parent_recorded_has_no_pairing_analysis(): void
    {
        $bird = $this->bird('BIRD', 'male', $this->bird('SIRE', 'male'));

        $this->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis', null)
                ->where('lineage.family.sire.ring_number', 'SIRE')
            );
    }

    public function test_locked_pedigrees_send_no_breeding_report(): void
    {
        ['bird' => $bird, 'sister' => $sister] = $this->family();

        foreach ([$this->share($bird, 'private'), $this->share($sister, 'password')] as $url) {
            $response = $this->get($url)
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page
                    ->missing('analysis')
                    ->missing('lineage')
                    ->missing('ownerLinks')
                );

            $this->assertStringNotContainsString('GRANDSIRE', $this->propsJson($response));
        }
    }

    public function test_password_visitors_see_the_breeding_report_once_unlocked(): void
    {
        ['bird' => $bird] = $this->family();
        $url = $this->share($bird, 'password');

        $this->post(route('pedigrees.unlock', $bird->pedigreeRecord()->serial), ['password' => 'secret-password']);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.coi', 0.125)
                ->where('lineage.family.siblings.full.0.ring_number', 'SISTER')
                ->where('ownerLinks', null)
            );
    }

    public function test_owner_gets_links_to_the_full_reports(): void
    {
        ['bird' => $bird, 'sire' => $sire, 'dam' => $dam] = $this->family();

        $this->actingAs($this->me)
            ->get($this->share($bird, 'private'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // The owner sees what visitors see, plus links into the app
                ->where('analysis.sire.id', null)
                ->where('ownerLinks.pairing_analysis', route('pairing-analysis.index', ['sire' => $sire->id, 'dam' => $dam->id]))
                ->where('ownerLinks.lineage', route('lineage.index', ['pigeon' => $bird->id]))
                ->where('ownerLinks.edit', route('pigeons.edit', $bird))
            );
    }

    public function test_owner_pedigree_page_shows_the_full_breeding_report(): void
    {
        ['bird' => $bird, 'sire' => $sire, 'dam' => $dam, 'sister' => $sister] = $this->family();

        $this->actingAs($this->me)
            ->get(route('pigeons.pedigree', $bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Pedigree')
                ->where('meta.coi.coefficient', 0.125)
                // The owner's own view keeps ids (links to the birds' pages) and statuses
                ->where('analysis.coi', 0.125)
                ->where('analysis.sire.id', $sire->id)
                ->where('analysis.shared.total', 2)
                ->where('lineage.bird.id', $bird->id)
                ->where('lineage.family.siblings.full.0.id', $sister->id)
                ->where('lineage.family.siblings.full.0.status', 'deceased')
                ->where('generations', 6)
                ->where('descendantGenerations', 6)
                ->where('ownerLinks.pairing_analysis', route('pairing-analysis.index', ['sire' => $sire->id, 'dam' => $dam->id]))
                ->where('ownerLinks.lineage', route('lineage.index', ['pigeon' => $bird->id]))
            );
    }

    public function test_pairing_analysis_needs_two_loft_birds_for_the_owner_link(): void
    {
        // Parents recorded only on the bird (bought-in): the analysis still works from ring numbers
        $bird = $this->bird('BIRD', 'male', attributes: ['sire_ring_number' => 'OUT-SIRE', 'dam_ring_number' => 'OUT-DAM']);

        $this->actingAs($this->me)
            ->get($this->share($bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.sire.ring_number', 'OUT-SIRE')
                ->where('analysis.sire.external', true)
                ->where('analysis.coi', 0)
                ->where('analysis.golden_pair.golden', false)
                ->where('ownerLinks.pairing_analysis', null)
            );
    }

    public function test_sample_pedigree_carries_the_pairs_coi(): void
    {
        ['sire' => $sire, 'dam' => $dam] = $this->family();

        $this->actingAs($this->me)
            ->get(route('pairing-analysis.index', ['sire' => $sire->id, 'dam' => $dam->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.coi', 0.125)
                ->where('sample.meta.coi.coefficient', 0.125)
                ->where('sample.meta.coi.common_ancestors.0.ring_number', 'GRANDSIRE')
            );
    }

    public function test_old_test_pairing_address_redirects_to_pairing_analysis(): void
    {
        $this->actingAs($this->me)
            ->get('/test-pairing?sire=1&dam=2')
            ->assertRedirect(route('pairing-analysis.index', ['sire' => 1, 'dam' => 2]));
    }
}
