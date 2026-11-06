<?php

namespace Tests\Feature;

use App\Models\Pigeon;
use App\Models\User;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeCopyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    private function ancestors(Pigeon $pigeon, array $paths): void
    {
        foreach ($paths as $path) {
            $pigeon->pedigreeAncestors()->create(['path' => $path, 'ring_number' => "{$pigeon->id}-{$path}"]);
        }
    }

    private function copy(Pigeon $target, Pigeon $source, array $sides)
    {
        return $this->actingAs($this->user)->post(route('pigeons.copyPedigree', $target), [
            'source_id' => $source->id,
            'sides' => $sides,
        ]);
    }

    private function paths(Pigeon $pigeon): array
    {
        return $pigeon->pedigreeAncestors()->orderBy('path')->pluck('path')->all();
    }

    private function copyParts(Pigeon $target, Pigeon $source, array $copies)
    {
        return $this->actingAs($this->user)->post(route('pigeons.copyPedigree', $target), [
            'source_id' => $source->id,
            'copies' => array_map(fn (string $from, string $to) => ['from' => $from, 'to' => $to], array_keys($copies), $copies),
        ]);
    }

    public function test_sources_list_related_birds_first_in_the_standard_search_format(): void
    {
        $target = $this->pigeon(['sire_ring_number' => 'NL 2019-1234', 'dam_ring_number' => 'NL-2020-8']);
        $this->ancestors($target, ['DS']);
        $target->pedigreeAncestors()->create(['path' => 'DSS', 'ring_number' => 'GB-2010-77']);
        $this->pigeon(['dam_ring_number' => 'X-1', 'dam_name' => 'SOME HEN']);
        $unrelated = $this->pigeon(['ring_number' => 'BE-2023-1', 'dam_name' => 'SOME HEN']);
        $sibling = $this->pigeon([
            'ring_number' => 'BE-2024-2', 'name' => 'BLUE BOY', 'bloodline' => 'Janssen', 'color' => 'BB',
            'notes' => 'Private', 'remarks' => 'Fast', 'sire_ring_number' => 'nl2019-1234', 'sire_name' => 'OLD COCK',
        ]);
        $this->ancestors($sibling, ['SS', 'SD', 'SSS', 'DS']);
        // Related only through a great-grandsire, who is its own grandsire
        $cousin = $this->pigeon(['ring_number' => 'BE-2024-3', 'dam_ring_number' => 'BE-2021-9']);
        $cousin->pedigreeAncestors()->create(['path' => 'DS', 'ring_number' => 'GB 2010 77']);
        $this->pigeon(['ring_number' => 'BE-2024-4']); // nothing recorded: not offered
        $this->pigeon(['sire_ring_number' => 'NL 2019-1234'], User::factory()->create()); // another loft

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertOk()
            ->assertJsonCount(4, 'pigeons')
            ->assertJsonPath('pigeons.0.id', $sibling->id)
            ->assertJsonPath('pigeons.0.name', 'BLUE BOY')
            ->assertJsonPath('pigeons.0.bloodline', 'Janssen')
            ->assertJsonPath('pigeons.0.color', 'BB')
            ->assertJsonPath('pigeons.0.notes', 'Private')
            ->assertJsonPath('pigeons.0.remarks', 'Fast')
            ->assertJsonPath('pigeons.0.sire', ['ring_number' => 'nl2019-1234', 'name' => 'OLD COCK'])
            ->assertJsonPath('pigeons.0.dam', null)
            // The shared sire, but not his parents, which follow from him
            ->assertJsonPath('pigeons.0.related', [['from' => 'S', 'to' => 'S']])
            ->assertJsonPath('pigeons.1.id', $cousin->id)
            ->assertJsonPath('pigeons.1.related', [['from' => 'DS', 'to' => 'DSS']])
            ->assertJsonPath('pigeons.2.id', $unrelated->id)
            ->assertJsonPath('pigeons.2.related', []);

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', [$target, 'q' => '2023']))
            ->assertJsonCount(1, 'pigeons')
            ->assertJsonPath('pigeons.0.id', $unrelated->id);

        $this->actingAs(User::factory()->create())
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertNotFound();
    }

    public function test_sources_match_loft_parents_and_show_them_as_parents(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500', 'name' => 'KING']);
        $target = $this->pigeon(['sire_id' => $loftSire->id]);
        $halfSibling = $this->pigeon(['sire_id' => $loftSire->id]);

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertJsonPath('pigeons.0.id', $halfSibling->id)
            ->assertJsonPath('pigeons.0.related', [['from' => 'S', 'to' => 'S']])
            ->assertJsonPath('pigeons.0.sire', ['ring_number' => 'BE-2020-500', 'name' => 'KING']);
    }

    public function test_the_pedigree_of_a_bird_to_copy_from_is_available_to_its_owner_only(): void
    {
        $target = $this->pigeon();
        $source = $this->pigeon(['sire_ring_number' => 'NL-1', 'sire_bloodline' => 'Janssen']);
        $source->pedigreeAncestors()->create(['path' => 'SD', 'ring_number' => 'NL-2', 'bloodline' => 'Aarden']);
        $theirs = $this->pigeon(['sire_ring_number' => 'X'], User::factory()->create());

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySource', [$target, $source]))
            ->assertOk()
            ->assertJsonPath('tree.id', $source->id)
            ->assertJsonPath('tree.sire.ring_number', 'NL-1')
            ->assertJsonPath('tree.sire.bloodline', 'Janssen')
            ->assertJsonPath('tree.sire.dam.bloodline', 'Aarden');

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySource', [$target, $theirs]))
            ->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->getJson(route('pigeons.pedigreeCopySource', [$target, $source]))
            ->assertNotFound();
    }

    public function test_a_great_grandsire_line_is_copied_from_a_bird_related_only_through_him(): void
    {
        // Here the great-grandsire is known, but nothing behind him
        $target = $this->pigeon(['sire_ring_number' => 'NL-2019-1']);
        $target->pedigreeAncestors()->create(['path' => 'SS', 'ring_number' => 'NL-2015-2']);
        $target->pedigreeAncestors()->create(['path' => 'SSS', 'ring_number' => 'GB-2010-77']);
        $target->pedigreeAncestors()->create(['path' => 'SD', 'ring_number' => 'KEEP-ME']);

        // The other bird has him as its sire, with his parents and grandparents
        $source = $this->pigeon([
            'sire_ring_number' => 'gb 2010 77', 'sire_name' => 'KLEINE DIRK', 'sire_bloodline' => 'Janssen',
            'sire_merits' => [['type' => 'ace_pigeon', 'level' => null, 'year' => 2012, 'detail' => null]],
        ]);
        $source->pedigreeAncestors()->create(['path' => 'SS', 'ring_number' => 'GB-2005-1', 'bloodline' => 'Janssen']);
        $source->pedigreeAncestors()->create(['path' => 'SD', 'ring_number' => 'GB-2006-2', 'remarks' => 'Golden hen']);
        $source->pedigreeAncestors()->create(['path' => 'SSS', 'ring_number' => 'TOO-FAR']);

        $this->copyParts($target, $source, ['S' => 'SSS'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', "Copied the sire's sire's sire from {$source->ring_number}.");

        $rows = $target->pedigreeAncestors()->get()->keyBy('path');
        $this->assertSame(['SD', 'SS', 'SSS', 'SSSD', 'SSSS'], $rows->keys()->sort()->values()->all());
        $this->assertSame('gb 2010 77', $rows['SSS']->ring_number);
        $this->assertSame('KLEINE DIRK', $rows['SSS']->name);
        $this->assertSame('Janssen', $rows['SSS']->bloodline);
        $this->assertSame('ace_pigeon', $rows['SSS']->merits[0]['type']);
        $this->assertSame('GB-2005-1', $rows['SSSS']->ring_number);
        $this->assertSame('Golden hen', $rows['SSSD']->remarks);
        // Only great-great-grandparents fit on the chart; the rest of the other places stay as they were
        $this->assertSame('KEEP-ME', $rows['SD']->ring_number);
        $this->assertSame('NL-2019-1', $target->fresh()->sire_ring_number);
    }

    public function test_a_copy_behind_a_loft_parent_is_saved_on_that_bird(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500']);
        $target = $this->pigeon(['sire_id' => $loftSire->id]);
        $brother = $this->pigeon(['sire_id' => $loftSire->id]);

        // A bird whose outside dam is the loft sire's dam, with her sire recorded behind her
        $source = $this->pigeon(['sire_ring_number' => 'NL-2016-1', 'dam_ring_number' => 'BE-2019-7', 'dam_name' => 'QUEEN']);
        $source->pedigreeAncestors()->create(['path' => 'DS', 'ring_number' => 'NL-2015-3']);

        // A hen's place can't take a cock
        $this->copyParts($target, $source, ['S' => 'SD'])->assertSessionHasErrors('copies');
        $this->copyParts($target, $source, ['D' => 'SD'])->assertSessionHasNoErrors();

        // Stored on the loft sire, as his outside dam
        $loftSire->refresh();
        $this->assertNull($loftSire->dam_id);
        $this->assertSame('BE-2019-7', $loftSire->dam_ring_number);
        $this->assertSame('QUEEN', $loftSire->dam_name);
        $this->assertSame(['DS'], $this->paths($loftSire));
        $this->assertSame([], $this->paths($target));

        // ...so every offspring of the loft sire inherits her
        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $brother))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.dam.ring_number', 'BE-2019-7')
                ->where('tree.sire.dam.sire.ring_number', 'NL-2015-3')
            );
    }

    public function test_a_loft_bird_is_linked_when_it_becomes_a_loft_birds_parent(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500']);
        $target = $this->pigeon(['sire_id' => $loftSire->id]);
        $loftHen = $this->pigeon(['gender' => 'female', 'ring_number' => 'BE-2019-7']);
        $source = $this->pigeon(['dam_id' => $loftHen->id]);

        $this->copyParts($target, $source, ['D' => 'SD'])->assertSessionHasNoErrors();

        // Copying into a loft bird's own parents links the loft bird, as with "D" into "D" elsewhere
        $this->assertSame($loftHen->id, $loftSire->fresh()->dam_id);

        // Deeper in, a loft bird is stored like an outside one, with its own pedigree behind it
        $other = $this->pigeon(['dam_ring_number' => 'EXT-DAM']);
        $this->copyParts($other, $target, ['SD' => 'DD'])->assertSessionHasNoErrors();
        $this->assertSame('BE-2019-7', $other->pedigreeAncestors()->firstWhere('path', 'DD')?->ring_number);
    }

    public function test_deeper_copies_are_checked_before_anything_changes(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500', 'sire_ring_number' => 'NL-1']);
        $target = $this->pigeon(['sire_id' => $loftSire->id, 'dam_ring_number' => 'OLD-DAM']);
        $source = $this->pigeon(['sire_ring_number' => 'NEW-SIRE', 'dam_ring_number' => 'NEW-DAM']);
        $source->pedigreeAncestors()->create(['path' => 'SS', 'ring_number' => 'NEW-SS']);
        $loftGrandsire = $this->pigeon(['gender' => 'male']);
        $loftSire->update(['sire_id' => $loftGrandsire->id]);

        $fails = fn (array $copies) => $this->copyParts($target, $source, $copies)->assertSessionHasErrors('copies');

        $fails(['S' => 'D']); // a sire into a dam's place
        $fails(['SS' => 'DDS']); // nothing recorded in front of that place yet
        $fails(['SS' => 'SS']); // a loft bird is there
        $fails(['D' => 'D', 'S' => 'DS']); // the second is already part of the first
        $fails(['DD' => 'D']); // nothing recorded there in the other pedigree
        $this->actingAs($this->user)->post(route('pigeons.copyPedigree', $target), [
            'source_id' => $source->id,
            'copies' => [['from' => 'SSSS', 'to' => 'S']],
        ])->assertSessionHasErrors('copies.0.from');

        $this->assertSame('OLD-DAM', $target->fresh()->dam_ring_number);
        $this->assertSame($loftGrandsire->id, $loftSire->fresh()->sire_id);
        $this->assertSame([], $this->paths($target));

        // A bird can't end up in its own pedigree
        $child = $this->pigeon(['dam_ring_number' => 'HEN', 'sire_id' => $target->id]);
        $grandchild = $this->pigeon(['sire_id' => $child->id]);
        $this->copyParts($target, $grandchild, ['SS' => 'DS'])
            ->assertSessionHasErrors(['copies' => "That part of the pedigree includes this pigeon itself, so it can't be copied here."]);
    }

    public function test_copying_the_sire_side_copies_the_parent_and_everything_behind_it(): void
    {
        $merits = [['type' => 'super_breeder', 'level' => null, 'year' => 2019, 'detail' => null]];
        $source = $this->pigeon([
            'sire_ring_number' => 'NL-2019-1234',
            'sire_name' => 'OLD COCK',
            'sire_color' => 'BLUE',
            'sire_notes' => '1st National',
            'sire_merits' => $merits,
            'dam_ring_number' => 'NL-2020-5',
        ]);
        $this->ancestors($source, ['SS', 'SD', 'SDS', 'DS']);
        $source->pedigreeAncestors()->where('path', 'SD')->update(['remarks' => 'Top hen', 'merits' => json_encode($merits)]);
        $target = $this->pigeon();

        $this->copy($target, $source, ['sire'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', "Copied the sire side from {$source->ring_number}.");

        $target->refresh();
        $this->assertNull($target->sire_id);
        $this->assertSame('NL-2019-1234', $target->sire_ring_number);
        $this->assertSame('OLD COCK', $target->sire_name);
        $this->assertSame('BLUE', $target->sire_color);
        $this->assertSame('1st National', $target->sire_notes);
        $this->assertSame($merits, $target->sire_merits);
        $this->assertNull($target->dam_ring_number);
        $this->assertSame(['SD', 'SDS', 'SS'], $this->paths($target));
        $this->assertDatabaseHas('pedigree_ancestors', [
            'pigeon_id' => $target->id,
            'path' => 'SD',
            'ring_number' => "{$source->id}-SD",
            'remarks' => 'Top hen',
        ]);
        $this->assertSame($merits, $target->pedigreeAncestors()->where('path', 'SD')->sole()->merits);

        // The source keeps its own records
        $this->assertSame(['DS', 'SD', 'SDS', 'SS'], $this->paths($source));

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $target))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.sire.ring_number', 'NL-2019-1234')
                ->where('tree.sire.anchor_id', $target->id)
                ->where('tree.sire.dam.ring_number', "{$source->id}-SD")
                ->where('tree.sire.dam.sire.ring_number', "{$source->id}-SDS")
                ->where('tree.dam', null)
            );
    }

    public function test_copying_both_sides_replaces_what_was_recorded(): void
    {
        $source = $this->pigeon(['sire_ring_number' => 'NEW-SIRE', 'dam_ring_number' => 'NEW-DAM']);
        $this->ancestors($source, ['SS', 'DD']);
        $target = $this->pigeon(['sire_ring_number' => 'OLD-SIRE', 'sire_notes' => 'Old remark', 'dam_name' => 'OLD DAM']);
        $this->ancestors($target, ['SS', 'SD', 'SDD', 'DS']);

        $this->copy($target, $source, ['sire', 'dam'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', "Copied both sides from {$source->ring_number}.");

        $target->refresh();
        $this->assertSame('NEW-SIRE', $target->sire_ring_number);
        $this->assertNull($target->sire_notes);
        $this->assertSame('NEW-DAM', $target->dam_ring_number);
        $this->assertNull($target->dam_name);
        $this->assertSame(['DD', 'SS'], $this->paths($target));
        $this->assertSame("{$source->id}-SS", $target->pedigreeAncestors()->firstWhere('path', 'SS')->ring_number);
    }

    public function test_a_loft_parent_is_linked_instead_of_copied(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500', 'name' => 'KING', 'color' => 'RED']);
        $source = $this->pigeon(['sire_id' => $loftSire->id, 'sire_ring_number' => 'BE-2020-500', 'sire_notes' => 'Private note']);
        $target = $this->pigeon(['sire_ring_number' => 'OLD-SIRE', 'sire_merits' => [['type' => 'finisher']]]);
        $this->ancestors($target, ['SS']);

        $this->copy($target, $source, ['sire'])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame($loftSire->id, $target->sire_id);
        $this->assertSame('BE-2020-500', $target->sire_ring_number);
        $this->assertSame('KING', $target->sire_name);
        $this->assertSame('RED', $target->sire_color);
        $this->assertNull($target->sire_notes);
        $this->assertNull($target->sire_merits);
        $this->assertSame([], $this->paths($target));
    }

    public function test_a_side_linked_to_a_loft_bird_is_not_overwritten(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male']);
        $target = $this->pigeon(['sire_id' => $loftSire->id]);
        $source = $this->pigeon(['sire_ring_number' => 'EXT-SIRE', 'dam_ring_number' => 'EXT-DAM']);

        // Nothing is copied when one of the sides can't be: not even the dam
        $this->copy($target, $source, ['sire', 'dam'])->assertSessionHasErrors('sides');
        $target->refresh();
        $this->assertSame($loftSire->id, $target->sire_id);
        $this->assertNull($target->dam_ring_number);

        // Already linked to the same loft bird: nothing to do
        $sibling = $this->pigeon(['sire_id' => $loftSire->id]);
        $this->copy($target, $sibling, ['sire'])->assertSessionHasNoErrors();
        $this->assertSame($loftSire->id, $target->fresh()->sire_id);
    }

    public function test_input_is_validated(): void
    {
        $target = $this->pigeon();
        $sireOnly = $this->pigeon(['sire_ring_number' => 'EXT-SIRE']);
        $theirs = $this->pigeon(['sire_ring_number' => 'EXT'], User::factory()->create());

        $this->copy($target, $sireOnly, ['dam'])->assertSessionHasErrors('sides');
        $this->copy($target, $sireOnly, ['sire', 'dam'])->assertSessionHasErrors('sides');
        $this->assertNull($target->fresh()->sire_ring_number);

        $this->copy($target, $sireOnly, [])->assertSessionHasErrors('sides');
        $this->copy($target, $sireOnly, ['uncle'])->assertSessionHasErrors('sides.0');
        $this->copy($target, $sireOnly, ['sire', 'sire'])->assertSessionHasErrors('sides.0');
        $this->copy($target, $target, ['sire'])->assertSessionHasErrors('source_id');
        $this->copy($target, $theirs, ['sire'])->assertSessionHasErrors('source_id');
        $this->actingAs(User::factory()->create())
            ->post(route('pigeons.copyPedigree', $target), ['source_id' => $sireOnly->id, 'sides' => ['sire']])
            ->assertNotFound();

        // A bird can't become its own parent
        $child = $this->pigeon(['sire_id' => $target->id]);
        $this->copy($target, $child, ['sire'])->assertSessionHasErrors('sides');
    }

    public function test_lineage_sees_copied_ancestors_as_shared_birds(): void
    {
        $bought = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2024-1', 'sire_ring_number' => 'NL-2019-1234']);
        $this->ancestors($bought, ['SS', 'SD']);
        $sister = $this->pigeon(['gender' => 'female', 'ring_number' => 'BE-2024-2']);

        $lineage = app(LineageService::class);
        $before = $lineage->graph($this->user->id);
        $this->assertSame(0.0, $before->mating(LoftGraph::pigeonKey($bought->id), LoftGraph::pigeonKey($sister->id))['coefficient']);

        $this->copy($sister, $bought, ['sire'])->assertSessionHasNoErrors();

        $graph = $lineage->graph($this->user->id);
        [$boughtSire] = $graph->parentsOf(LoftGraph::pigeonKey($bought->id));
        [$sisterSire] = $graph->parentsOf(LoftGraph::pigeonKey($sister->id));
        $this->assertSame('r:NL20191234', $boughtSire);
        $this->assertSame($boughtSire, $sisterSire);

        // Half siblings through the shared sire: a youngster from them would be 12.5% inbred
        $this->assertEqualsWithDelta(
            0.125,
            $graph->mating(LoftGraph::pigeonKey($bought->id), LoftGraph::pigeonKey($sister->id))['coefficient'],
            1e-9,
        );
    }
}
