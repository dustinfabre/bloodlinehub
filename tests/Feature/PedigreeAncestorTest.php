<?php

namespace Tests\Feature;

use App\Models\ColorTag;
use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeAncestorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function pigeon(array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => 'stock',
            'ring_number' => 'BE-2024-'.rand(100000, 999999),
            ...$attributes,
        ]);
    }

    private function saveAncestor(Pigeon $subject, Pigeon $anchor, string $path, array $details = [])
    {
        return $this->actingAs($this->user)->put(route('pigeons.saveAncestor', $subject), [
            'anchor_id' => $anchor->id,
            'path' => $path,
            'ring_number' => "RING-{$path}",
            ...$details,
        ]);
    }

    public function test_editor_page_shows_the_tree_with_storage_details(): void
    {
        $subject = $this->pigeon(['ring_number' => 'BE-2024-100001', 'color' => 'BLUE BAR', 'sire_name' => 'BOUGHT COCK']);

        $this->actingAs($this->user)
            ->get(route('pigeons.editPedigree', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/EditPedigree')
                ->where('pigeon.id', $subject->id)
                ->where('tree.sire.external', true)
                ->where('tree.sire.anchor_id', $subject->id)
                ->where('tree.sire.path', 'S')
                ->where('colors', ['BLUE BAR'])
                ->has('colorTags')
            );

        $this->actingAs(User::factory()->create())
            ->get(route('pigeons.editPedigree', $subject))
            ->assertNotFound();
    }

    public function test_grandparents_can_be_added_behind_an_external_parent(): void
    {
        $tag = ColorTag::create(['user_id' => $this->user->id, 'name' => 'Ace', 'color' => '#EF4444']);
        $subject = $this->pigeon(['sire_ring_number' => 'NL-2019-1']);

        $this->saveAncestor($subject, $subject, 'SD', [
            'name' => 'GOLDEN LADY',
            'color' => 'BLUE CHECK',
            'remarks' => '1st National Bourges',
            'color_tag_id' => $tag->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAncestor($subject, $subject, 'SDS')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pedigree_ancestors', [
            'pigeon_id' => $subject->id,
            'path' => 'SD',
            'name' => 'GOLDEN LADY',
            'remarks' => '1st National Bourges',
        ]);

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $subject))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.ring_number', 'NL-2019-1')
                ->where('tree.sire.sire', null)
                ->where('tree.sire.dam.ring_number', 'RING-SD')
                ->where('tree.sire.dam.gender', 'female')
                ->where('tree.sire.dam.remarks', '1st National Bourges')
                ->where('tree.sire.dam.highlight.id', $tag->id)
                ->where('tree.sire.dam.anchor_id', $subject->id)
                ->where('tree.sire.dam.path', 'SD')
                ->where('tree.sire.dam.sire.ring_number', 'RING-SDS')
                ->where('tree.sire.dam.sire.gender', 'male')
            );

        // Saving the same position again updates it
        $this->saveAncestor($subject, $subject, 'SD', ['name' => 'RENAMED']);
        $this->assertSame(2, PedigreeAncestor::count());
        $this->assertSame('RENAMED', PedigreeAncestor::firstWhere('path', 'SD')->name);
    }

    public function test_ancestors_saved_on_a_loft_bird_are_inherited_by_its_offspring(): void
    {
        // A bought cock in the loft with an external sire on its record
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500', 'sire_ring_number' => 'NL-2015-9']);
        $child = $this->pigeon(['ring_number' => 'BE-2024-1', 'sire_id' => $loftSire->id]);
        $sibling = $this->pigeon(['ring_number' => 'BE-2024-2', 'sire_id' => $loftSire->id]);
        $grandchild = $this->pigeon(['ring_number' => 'BE-2025-1', 'sire_id' => $child->id]);

        // Edited from the child's pedigree editor, but stored on the loft sire
        $this->saveAncestor($child, $loftSire, 'SS')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedigree_ancestors', ['pigeon_id' => $loftSire->id, 'path' => 'SS']);

        foreach ([$child, $sibling] as $offspring) {
            $this->actingAs($this->user)
                ->get(route('pigeons.pedigree', $offspring))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tree.sire.id', $loftSire->id)
                    ->where('tree.sire.sire.ring_number', 'NL-2015-9')
                    ->where('tree.sire.sire.sire.ring_number', 'RING-SS')
                    ->where('tree.sire.sire.sire.anchor_id', $loftSire->id)
                );
        }

        // One generation further down the same ancestor appears one column further out
        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $grandchild))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.sire.sire.sire.ring_number', 'RING-SS')
            );
    }

    public function test_parent_positions_update_the_pigeon_record(): void
    {
        $tag = ColorTag::create(['user_id' => $this->user->id, 'name' => 'Gold', 'color' => '#EAB308']);
        $subject = $this->pigeon();

        $this->saveAncestor($subject, $subject, 'D', [
            'ring_number' => 'NL-2020-77',
            'name' => 'QUEEN',
            'color' => 'RED',
            'remarks' => 'Top breeding hen',
            'color_tag_id' => $tag->id,
        ])->assertSessionHasNoErrors();

        $subject->refresh();
        $this->assertSame('NL-2020-77', $subject->dam_ring_number);
        $this->assertSame('QUEEN', $subject->dam_name);
        $this->assertSame('RED', $subject->dam_color);
        $this->assertSame('Top breeding hen', $subject->dam_notes);
        $this->assertSame($tag->id, $subject->dam_color_tag_id);
        $this->assertSame(0, PedigreeAncestor::count());
    }

    public function test_an_ancestor_needs_its_offspring_first(): void
    {
        $subject = $this->pigeon();

        $this->saveAncestor($subject, $subject, 'SS')->assertSessionHasErrors('path');
        $this->saveAncestor($subject, $subject, 'S')->assertSessionHasNoErrors();
        $this->saveAncestor($subject, $subject, 'SSD')->assertSessionHasErrors('path');
        $this->saveAncestor($subject, $subject, 'SS')->assertSessionHasNoErrors();
        $this->saveAncestor($subject, $subject, 'SSD')->assertSessionHasNoErrors();
    }

    public function test_loft_birds_cannot_be_overwritten_from_the_editor(): void
    {
        $sire = $this->pigeon(['gender' => 'male']);
        $subject = $this->pigeon(['sire_id' => $sire->id]);

        $this->saveAncestor($subject, $subject, 'S')->assertSessionHasErrors('path');
        $this->saveAncestor($subject, $subject, 'SD')->assertSessionHasErrors('path');
        $this->assertNull($subject->fresh()->sire_ring_number);
    }

    public function test_input_is_validated(): void
    {
        $subject = $this->pigeon();
        $otherTag = ColorTag::create(['user_id' => User::factory()->create()->id, 'name' => 'X', 'color' => '#000000']);

        $this->saveAncestor($subject, $subject, 'S', ['ring_number' => '', 'name' => ''])
            ->assertSessionHasErrors('ring_number');
        $this->saveAncestor($subject, $subject, 'S', ['ring_number' => '', 'name' => 'NAMED ONLY'])
            ->assertSessionHasNoErrors();
        $this->saveAncestor($subject, $subject, 'SX')->assertSessionHasErrors('path');
        $this->saveAncestor($subject, $subject, 'SSSSS')->assertSessionHasErrors('path');
        $this->saveAncestor($subject, $subject, 'S', ['color_tag_id' => $otherTag->id])
            ->assertSessionHasErrors('color_tag_id');
    }

    public function test_other_users_birds_cannot_be_edited(): void
    {
        $other = User::factory()->create();
        $theirs = $this->pigeon([], $other);
        $mine = $this->pigeon();

        $this->saveAncestor($mine, $theirs, 'S')->assertSessionHasErrors('anchor_id');
        $this->saveAncestor($theirs, $mine, 'S')->assertNotFound();
        $this->actingAs($this->user)
            ->delete(route('pigeons.removeAncestor', $mine), ['anchor_id' => $theirs->id, 'path' => 'S'])
            ->assertSessionHasErrors('anchor_id');
    }

    public function test_removing_an_ancestor_also_removes_those_behind_it(): void
    {
        $subject = $this->pigeon(['sire_ring_number' => 'EXT-S', 'sire_notes' => 'Remark']);
        foreach (['SS', 'SD', 'SSS', 'SSD'] as $path) {
            $this->saveAncestor($subject, $subject, $path)->assertSessionHasNoErrors();
        }

        $this->actingAs($this->user)
            ->delete(route('pigeons.removeAncestor', $subject), ['anchor_id' => $subject->id, 'path' => 'SS'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['SD'], PedigreeAncestor::pluck('path')->all());

        // Removing the external parent clears it from the pigeon and everything recorded behind it
        $this->actingAs($this->user)
            ->delete(route('pigeons.removeAncestor', $subject), ['anchor_id' => $subject->id, 'path' => 'S'])
            ->assertSessionHasNoErrors();
        $subject->refresh();
        $this->assertNull($subject->sire_ring_number);
        $this->assertNull($subject->sire_notes);
        $this->assertSame(0, PedigreeAncestor::count());
    }

    public function test_public_charts_hide_where_ancestors_are_stored(): void
    {
        $subject = $this->pigeon(['sire_ring_number' => 'EXT-S']);
        $this->saveAncestor($subject, $subject, 'SS');
        $subject->pedigreeRecord()->update(['is_public' => true]);

        auth()->logout();

        $this->get(route('pedigrees.shared', $subject->fresh()->pedigree->serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.anchor_id', null)
                ->where('tree.sire.path', null)
                ->where('tree.sire.sire.ring_number', 'RING-SS')
                ->where('tree.sire.sire.anchor_id', null)
            );
    }

    public function test_save_and_add_ancestors_continues_to_the_editor(): void
    {
        $this->actingAs($this->user)
            ->post(route('pigeons.store'), [
                'ring_number' => 'BE-2024-424242',
                'status' => 'stock',
                'edit_pedigree' => 1,
            ])
            ->assertRedirect(route('pigeons.editPedigree', Pigeon::firstWhere('ring_number', 'BE-2024-424242')));

        $pigeon = Pigeon::firstWhere('ring_number', 'BE-2024-424242');

        $this->actingAs($this->user)
            ->patch(route('pigeons.update', $pigeon), [
                'ring_number' => 'BE-2024-424242',
                'status' => 'stock',
                'edit_pedigree' => 1,
            ])
            ->assertRedirect(route('pigeons.editPedigree', $pigeon));
    }

    public function test_deleting_a_pigeon_removes_its_recorded_ancestors(): void
    {
        $subject = $this->pigeon(['sire_ring_number' => 'EXT-S']);
        $this->saveAncestor($subject, $subject, 'SS');

        $this->actingAs($this->user)->delete(route('pigeons.destroy', $subject));

        $this->assertSame(0, PedigreeAncestor::count());
    }
}
