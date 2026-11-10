<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Below the chart: photos and certificates of the bird and its loft parents, and the notable relatives the owner
 * picks in Edit pedigree. Nothing is shown twice.
 */
class PedigreeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create();
    }

    private function bird(string $ring, array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->me)->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    private function withMerit(Pigeon $pigeon): Pigeon
    {
        $pigeon->merits()->create(['type' => MeritType::RaceWinner, 'year' => 2024]);

        return $pigeon;
    }

    public function test_a_parents_photos_and_shown_certificates_appear_renamed_for_the_bird(): void
    {
        $sire = $this->bird('RMVC 018', [
            'gender' => 'male',
            'photo_url' => '/media/sire-main.webp',
            'extra_photos' => ['/media/sire-2.webp'],
            'pedigree_images' => [
                '/media/victoria.webp',
                '/media/sire-dam.webp',
                '/media/sire-certificate.webp',
                '/media/sire-private.webp',
                '/media/sire-offspring.webp',
            ],
            'pedigree_image_relations' => [
                '/media/victoria.webp' => 'sibling',
                '/media/sire-dam.webp' => 'D',
                '/media/sire-private.webp' => 'relative',
                '/media/sire-offspring.webp' => 'offspring',
            ],
            'pedigree_images_public' => [
                '/media/victoria.webp',
                '/media/sire-dam.webp',
                '/media/sire-certificate.webp',
                '/media/sire-offspring.webp',
            ],
        ]);
        $subject = $this->bird('PHA 2026 239438', [
            'sire_id' => $sire->id,
            'pedigree_images' => ['/media/own-certificate.webp'],
            'pedigree_images_public' => ['/media/own-certificate.webp'],
        ]);

        $this->actingAs($this->me)
            ->get(route('pigeons.pedigree', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.relative_photos', [
                    // The sire's other photos go with the sire; its main photo is on its chart card
                    ['url' => '/media/sire-2.webp', 'relation' => 'S'],
                    // The sire's sister is the bird's aunt on the father's side
                    ['url' => '/media/victoria.webp', 'relation' => 'aunt_uncle_sire'],
                    // The sire's dam is the bird's grandmother
                    ['url' => '/media/sire-dam.webp', 'relation' => 'SD'],
                    ['url' => '/media/sire-offspring.webp', 'relation' => 'half_sibling_sire'],
                ])
                ->where('meta.documents', [
                    ['url' => '/media/own-certificate.webp', 'of' => ''],
                    ['url' => '/media/sire-certificate.webp', 'of' => 'S'],
                ])
            );
    }

    public function test_notable_relatives_are_related_loft_birds_off_the_chart_with_merits_or_a_photo(): void
    {
        $grandsire = $this->bird('GS', ['gender' => 'male']);
        $sire = $this->bird('S', ['gender' => 'male', 'sire_id' => $grandsire->id]);
        $dam = $this->bird('D', ['gender' => 'female']);
        $subject = $this->bird('ME', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);

        $fullSister = $this->withMerit($this->bird('FULL', ['gender' => 'female', 'sire_id' => $sire->id, 'dam_id' => $dam->id]));
        $halfBrother = $this->bird('HALF', ['gender' => 'male', 'sire_id' => $sire->id, 'photo_url' => '/media/half.webp']);
        $son = $this->withMerit($this->bird('SON', ['gender' => 'male', 'sire_id' => $subject->id]));
        $aunt = $this->withMerit($this->bird('AUNT', ['gender' => 'female', 'sire_id' => $grandsire->id]));
        // Related but nothing to show
        $this->bird('PLAIN', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);
        // Not related, or someone else's
        $this->withMerit($this->bird('STRANGER'));
        $this->withMerit($this->bird('THEIRS', ['sire_id' => $sire->id], User::factory()->create()));

        $this->actingAs($this->me)
            ->get(route('pigeons.editPedigree', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notableRelatives', 4)
                ->where('notableRelatives.0.id', $fullSister->id)
                ->where('notableRelatives.0.relation', 'sibling')
                ->where('notableRelatives.0.merits.0.type', MeritType::RaceWinner->value)
                ->where('notableRelatives.1.id', $halfBrother->id)
                ->where('notableRelatives.1.relation', 'half_sibling_sire')
                ->where('notableRelatives.2.id', $son->id)
                ->where('notableRelatives.2.relation', 'offspring')
                ->where('notableRelatives.3.id', $aunt->id)
                ->where('notableRelatives.3.relation', 'aunt_uncle_sire')
                ->where('notableRelatives.3.featured', false)
            );
    }

    public function test_a_bird_on_the_chart_is_never_a_notable_relative(): void
    {
        // Line breeding: the sire's half-sister is also the dam
        $grandsire = $this->bird('GS', ['gender' => 'male']);
        $sire = $this->bird('S', ['gender' => 'male', 'sire_id' => $grandsire->id]);
        $dam = $this->withMerit($this->bird('D', ['gender' => 'female', 'sire_id' => $grandsire->id]));
        $subject = $this->bird('ME', ['sire_id' => $sire->id, 'dam_id' => $dam->id]);

        $this->actingAs($this->me)
            ->get(route('pigeons.editPedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page->has('notableRelatives', 0));
    }

    public function test_the_owner_picks_notable_relatives_and_they_show_below_the_pedigree(): void
    {
        $sire = $this->bird('S', ['gender' => 'male']);
        $subject = $this->bird('ME', ['sire_id' => $sire->id]);
        $sister = $this->withMerit($this->bird('SIS', ['gender' => 'female', 'sire_id' => $sire->id, 'photo_url' => '/media/sis.webp']));
        $stranger = $this->withMerit($this->bird('STRANGER'));

        $this->actingAs($this->me)
            ->put(route('pigeons.featureRelatives', $subject), ['pigeon_ids' => [$sister->id, $stranger->id, $sire->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Only the related bird off the chart is kept
        $this->assertSame([$sister->id], $subject->fresh()->featured_relatives);

        $subject->pedigreeRecord()->update(['is_public' => true]);
        auth()->logout();

        $this->get(route('pedigrees.shared', $subject->pedigreeRecord()->serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('meta.notable_relatives', 1)
                ->where('meta.notable_relatives.0.id', null)
                ->where('meta.notable_relatives.0.ring_number', 'SIS')
                ->where('meta.notable_relatives.0.relation', 'half_sibling_sire')
                ->where('meta.notable_relatives.0.photo_url', '/media/sis.webp')
                ->where('meta.notable_relatives.0.merits.0.id', null)
                ->missing('meta.notable_relatives.0.notes')
            );

        // Unticking everything clears it
        $this->actingAs($this->me)
            ->put(route('pigeons.featureRelatives', $subject), ['pigeon_ids' => []])
            ->assertSessionHasNoErrors();
        $this->assertNull($subject->fresh()->featured_relatives);
    }

    public function test_only_the_owner_can_pick_notable_relatives(): void
    {
        $subject = $this->bird('ME');

        $this->actingAs(User::factory()->create())
            ->put(route('pigeons.featureRelatives', $subject), ['pigeon_ids' => []])
            ->assertNotFound();
    }
}
