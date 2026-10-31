<?php

namespace Tests\Feature;

use App\Models\ColorTag;
use App\Models\Pedigree;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function pigeon(array $attributes = []): Pigeon
    {
        return Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => 'BE-2024-'.rand(100000, 999999),
            ...$attributes,
        ]);
    }

    private function colorTag(?User $owner = null, string $name = 'Ace'): ColorTag
    {
        return ColorTag::create([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => $name,
            'color' => '#EF4444',
        ]);
    }

    public function test_owner_can_view_pedigree_with_remarks_but_not_notes(): void
    {
        $tag = $this->colorTag();
        $sire = $this->pigeon([
            'gender' => 'male',
            'ring_number' => 'BE-2020-111111',
            'remarks' => '1st Bourges 2022',
            'notes' => 'Private breeding notes',
            'color_tag_id' => $tag->id,
        ]);
        $subject = $this->pigeon([
            'ring_number' => 'BE-2024-123456',
            'sire_id' => $sire->id,
            'dam_name' => 'EXTERNAL HEN',
            'dam_ring_number' => 'NL-2019-654321',
            'dam_notes' => 'Bought from a top loft',
            'dam_color_tag_id' => $tag->id,
            'notes' => 'Never on the pedigree',
        ]);

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Pedigree')
                ->where('tree.ring_number', 'BE-2024-123456')
                ->missing('tree.notes')
                ->where('tree.sire.id', $sire->id)
                ->where('tree.sire.remarks', '1st Bourges 2022')
                ->missing('tree.sire.notes')
                ->where('tree.sire.highlight.name', 'Ace')
                ->where('tree.dam.external', true)
                ->where('tree.dam.ring_number', 'NL-2019-654321')
                ->where('tree.dam.remarks', 'Bought from a top loft')
                ->where('tree.dam.highlight.color', '#EF4444')
                ->where('meta.is_public', false)
                ->where('meta.serial', fn (string $serial) => (bool) preg_match('/^BLH-[2-9A-Z]{4}-[2-9A-Z]{4}$/', $serial))
                ->where('meta.qr_svg', fn (string $svg) => str_starts_with($svg, '<svg'))
            );

        $this->assertDatabaseCount('pedigrees', 1);
    }

    public function test_pedigree_serial_is_stable_across_visits(): void
    {
        $subject = $this->pigeon();

        $this->actingAs($this->user)->get(route('pigeons.pedigree', $subject))->assertOk();
        $serial = $subject->fresh()->pedigree->serial;

        $this->actingAs($this->user)->get(route('pigeons.printPedigree', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/PrintPedigree')
                ->where('meta.serial', $serial)
            );

        $this->assertDatabaseCount('pedigrees', 1);
    }

    public function test_pedigree_shows_five_generations(): void
    {
        // Sire line: subject <- 1 <- 2 <- 3 <- 4 <- 5; the 5th ancestor is beyond the chart
        $ancestor = null;
        foreach ([5, 4, 3, 2, 1] as $generation) {
            $ancestor = $this->pigeon([
                'gender' => 'male',
                'ring_number' => "GEN-{$generation}",
                'sire_id' => $ancestor?->id,
            ]);
        }
        $subject = $this->pigeon(['sire_id' => $ancestor->id]);

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.ring_number', 'GEN-1')
                ->where('tree.sire.sire.sire.sire.ring_number', 'GEN-4')
                ->where('tree.sire.sire.sire.sire.sire', null)
            );
    }

    public function test_other_users_cannot_view_or_change_a_pedigree(): void
    {
        $subject = $this->pigeon();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('pigeons.pedigree', $subject))->assertNotFound();
        $this->actingAs($other)->get(route('pigeons.printPedigree', $subject))->assertNotFound();
        $this->actingAs($other)
            ->patch(route('pigeons.pedigreeVisibility', $subject), ['is_public' => true])
            ->assertNotFound();
    }

    public function test_pedigree_is_private_by_default(): void
    {
        $subject = $this->pigeon();
        $serial = $subject->pedigreeRecord()->serial;

        $this->get(route('pedigrees.shared', $serial))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pedigrees/Shared')
                ->where('tree', null)
                ->where('meta', null)
            );

        $this->actingAs(User::factory()->create())
            ->get(route('pedigrees.shared', $serial))
            ->assertForbidden();
    }

    public function test_owner_can_preview_their_private_shared_page(): void
    {
        $subject = $this->pigeon(['ring_number' => 'BE-2024-777777']);
        $serial = $subject->pedigreeRecord()->serial;

        $this->actingAs($this->user)
            ->get(route('pedigrees.shared', $serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isOwner', true)
                ->where('tree.ring_number', 'BE-2024-777777')
            );
    }

    public function test_public_pedigree_is_visible_to_anyone_without_internal_ids(): void
    {
        $sire = $this->pigeon(['gender' => 'male', 'remarks' => 'Ace pigeon', 'notes' => 'Secret']);
        $subject = $this->pigeon(['ring_number' => 'BE-2024-888888', 'sire_id' => $sire->id]);

        $this->actingAs($this->user)
            ->patch(route('pigeons.pedigreeVisibility', $subject), ['is_public' => true])
            ->assertRedirect();

        $serial = $subject->fresh()->pedigree->serial;
        $this->assertTrue($subject->fresh()->pedigree->is_public);

        auth()->logout();

        $this->get(route('pedigrees.shared', strtolower($serial)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pedigrees/Shared')
                ->where('isOwner', false)
                ->where('tree.ring_number', 'BE-2024-888888')
                ->where('tree.id', null)
                ->where('tree.sire.id', null)
                ->where('tree.sire.remarks', 'Ace pigeon')
                ->missing('tree.sire.notes')
                ->where('meta.is_public', true)
            );
    }

    public function test_unknown_serial_returns_not_found(): void
    {
        $this->get(route('pedigrees.shared', 'BLH-0000-0000'))->assertNotFound();
    }

    public function test_highlight_colors_are_saved_for_pigeon_and_external_parents(): void
    {
        $ace = $this->colorTag(name: 'Ace');
        $breeder = $this->colorTag(name: 'Breeder');

        $this->actingAs($this->user)
            ->post(route('pigeons.store'), [
                'ring_number' => 'BE-2025-100001',
                'status' => 'stock',
                'color_tag_id' => $ace->id,
                'sire_name' => 'OLD SIRE',
                'sire_color_tag_id' => $breeder->id,
                'dam_name' => 'OLD DAM',
                'dam_color_tag_id' => $ace->id,
            ])
            ->assertSessionHasNoErrors();

        $pigeon = Pigeon::where('ring_number', 'BE-2025-100001')->firstOrFail();
        $this->assertSame($ace->id, $pigeon->color_tag_id);
        $this->assertSame($breeder->id, $pigeon->sire_color_tag_id);
        $this->assertSame($ace->id, $pigeon->dam_color_tag_id);

        $this->actingAs($this->user)
            ->patch(route('pigeons.update', $pigeon), [
                'ring_number' => 'BE-2025-100001',
                'status' => 'stock',
                'color_tag_id' => null,
                'sire_color_tag_id' => $ace->id,
                'dam_color_tag_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $pigeon->refresh();
        $this->assertNull($pigeon->color_tag_id);
        $this->assertSame($ace->id, $pigeon->sire_color_tag_id);
        $this->assertNull($pigeon->dam_color_tag_id);
    }

    public function test_highlight_colors_must_belong_to_the_user(): void
    {
        $foreignTag = $this->colorTag(User::factory()->create(), 'Theirs');

        $this->actingAs($this->user)
            ->post(route('pigeons.store'), [
                'ring_number' => 'BE-2025-100002',
                'status' => 'stock',
                'color_tag_id' => $foreignTag->id,
                'sire_color_tag_id' => $foreignTag->id,
            ])
            ->assertSessionHasErrors(['color_tag_id', 'sire_color_tag_id']);
    }

    public function test_show_page_includes_parent_remarks_notes_and_highlights(): void
    {
        $tag = $this->colorTag();
        $sire = $this->pigeon([
            'gender' => 'male',
            'name' => 'THUNDER',
            'remarks' => '1st Club 300km',
            'notes' => 'Prefers the north loft',
            'color_tag_id' => $tag->id,
        ]);
        $subject = $this->pigeon([
            'sire_id' => $sire->id,
            'dam_name' => 'EXTERNAL HEN',
            'dam_notes' => 'Daughter of a national winner',
            'dam_color_tag_id' => $tag->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Show')
                ->where('pigeon.sire.name', 'THUNDER')
                ->where('pigeon.sire.remarks', '1st Club 300km')
                ->where('pigeon.sire.notes', 'Prefers the north loft')
                ->where('pigeon.sire.color_tag.name', 'Ace')
                ->where('pigeon.dam_notes', 'Daughter of a national winner')
                ->where('pigeon.dam_color_tag.name', 'Ace')
                ->where('pigeon.pedigree', null)
            );
    }

    public function test_deleting_a_pigeon_removes_its_pedigree(): void
    {
        $subject = $this->pigeon();
        $subject->pedigreeRecord();

        $this->actingAs($this->user)->delete(route('pigeons.destroy', $subject));

        $this->assertSame(0, Pedigree::count());
    }
}
