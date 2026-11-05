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

    public function test_sources_list_birds_with_the_same_parent_first(): void
    {
        $target = $this->pigeon(['sire_ring_number' => 'NL 2019-1234']);
        $unrelated = $this->pigeon(['ring_number' => 'BE-2023-1', 'dam_name' => 'SOME HEN']);
        $sibling = $this->pigeon(['ring_number' => 'BE-2024-2', 'sire_ring_number' => 'nl2019-1234', 'sire_name' => 'OLD COCK']);
        $this->ancestors($sibling, ['SS', 'SD', 'SSS', 'DS']);
        $this->pigeon(['ring_number' => 'BE-2024-3']); // nothing recorded: not offered
        $this->pigeon(['sire_ring_number' => 'NL 2019-1234'], User::factory()->create()); // another loft

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertOk()
            ->assertJsonCount(2, 'pigeons')
            ->assertJsonPath('pigeons.0.id', $sibling->id)
            ->assertJsonPath('pigeons.0.shared', ['sire'])
            ->assertJsonPath('pigeons.0.sire.state', 'external')
            ->assertJsonPath('pigeons.0.sire.name', 'OLD COCK')
            ->assertJsonPath('pigeons.0.sire.ancestors', 3)
            ->assertJsonPath('pigeons.0.dam.state', 'empty')
            ->assertJsonPath('pigeons.1.id', $unrelated->id)
            ->assertJsonPath('pigeons.1.shared', []);

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', [$target, 'q' => '2023']))
            ->assertJsonCount(1, 'pigeons')
            ->assertJsonPath('pigeons.0.id', $unrelated->id);

        $this->actingAs(User::factory()->create())
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertNotFound();
    }

    public function test_sources_report_loft_parents_as_linked(): void
    {
        $loftSire = $this->pigeon(['gender' => 'male', 'ring_number' => 'BE-2020-500']);
        $target = $this->pigeon(['sire_id' => $loftSire->id]);
        $halfSibling = $this->pigeon(['sire_id' => $loftSire->id]);

        $this->actingAs($this->user)
            ->getJson(route('pigeons.pedigreeCopySources', $target))
            ->assertJsonPath('pigeons.0.id', $halfSibling->id)
            ->assertJsonPath('pigeons.0.shared', ['sire'])
            ->assertJsonPath('pigeons.0.sire.state', 'linked')
            ->assertJsonPath('pigeons.0.sire.pigeon_id', $loftSire->id)
            ->assertJsonPath('pigeons.0.sire.ring_number', 'BE-2020-500')
            ->assertJsonPath('pigeons.0.sire.ancestors', null);
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
