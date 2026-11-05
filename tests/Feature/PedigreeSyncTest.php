<?php

namespace Tests\Feature;

use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The same outside bird recorded in several pedigrees (matched by ring number) stays in step when one record changes.
 */
class PedigreeSyncTest extends TestCase
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
        return Pigeon::create(['user_id' => $this->user->id, 'status' => 'stock', 'ring_number' => $ring, ...$attributes]);
    }

    private function row(Pigeon $pigeon, string $path, string $ring, array $attributes = []): void
    {
        $pigeon->pedigreeAncestors()->create(['path' => $path, 'ring_number' => $ring, ...$attributes]);
    }

    private function save(Pigeon $anchor, string $path, array $details, bool $sync = true)
    {
        return $this->actingAs($this->user)->put(route('pigeons.saveAncestor', $anchor), [
            'anchor_id' => $anchor->id,
            'path' => $path,
            'sync' => $sync,
            ...$details,
        ]);
    }

    private function remove(Pigeon $anchor, string $path, bool $sync = true)
    {
        return $this->actingAs($this->user)->delete(route('pigeons.removeAncestor', $anchor), [
            'anchor_id' => $anchor->id,
            'path' => $path,
            'sync' => $sync,
        ]);
    }

    private function ancestor(Pigeon $pigeon, string $path): ?object
    {
        return $pigeon->pedigreeAncestors()->where('path', $path)->first();
    }

    /**
     * The bought cock OLD COCK (NL-2019-1234) is the sire of A and B, and the grandsire of C.
     *
     * @return array{0: Pigeon, 1: Pigeon, 2: Pigeon}
     */
    private function family(): array
    {
        $a = $this->pigeon('PHA-2025-1', ['sire_ring_number' => 'NL-2019-1234', 'sire_name' => 'OLD COCK', 'sire_notes' => 'Remark on A']);
        $b = $this->pigeon('PHA-2025-2', ['sire_ring_number' => 'nl 2019 1234', 'sire_name' => 'OLD COCK', 'sire_notes' => 'Remark on B']);
        $c = $this->pigeon('PHA-2025-3', ['sire_ring_number' => 'NL-2021-77', 'sire_name' => 'SON OF OLD COCK']);
        $this->row($c, 'SS', 'NL2019-1234', ['name' => 'OLD COCK']);

        return [$a, $b, $c];
    }

    public function test_editing_an_outside_bird_updates_its_other_records(): void
    {
        [$a, $b, $c] = $this->family();

        $this->save($a, 'S', [
            'ring_number' => 'NL-2019-1234',
            'name' => 'GOLDEN COCK',
            'remarks' => 'Remark on A',
            'merits' => [['type' => 'super_breeder', 'level' => null, 'year' => 2022, 'detail' => null]],
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeSync', ['updated' => ['PHA-2025-2', 'PHA-2025-3'], 'skipped' => []]);

        $b->refresh();
        $this->assertSame('GOLDEN COCK', $b->sire_name);
        $this->assertSame('super_breeder', $b->sire_merits[0]['type']);
        // Only what changed is carried over: B keeps its own remark and ring spelling
        $this->assertSame('Remark on B', $b->sire_notes);
        $this->assertSame('nl 2019 1234', $b->sire_ring_number);
        $this->assertSame('GOLDEN COCK', $this->ancestor($c, 'SS')->name);
        $this->assertSame('super_breeder', $this->ancestor($c, 'SS')->merits[0]['type']);
    }

    public function test_correcting_the_ring_number_updates_the_other_records(): void
    {
        [$a, $b, $c] = $this->family();

        $this->save($a, 'S', ['ring_number' => 'NL-2019-1243', 'name' => 'OLD COCK', 'remarks' => 'Remark on A'])
            ->assertSessionHasNoErrors();

        $this->assertSame('NL-2019-1243', $b->fresh()->sire_ring_number);
        $this->assertSame('NL-2019-1243', $this->ancestor($c, 'SS')->ring_number);
    }

    public function test_the_other_records_can_be_left_alone(): void
    {
        [$a, $b, $c] = $this->family();

        $this->save($a, 'S', ['ring_number' => 'NL-2019-1234', 'name' => 'ONLY HERE'], sync: false)
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('pedigreeSync');

        $this->assertSame('ONLY HERE', $a->fresh()->sire_name);
        $this->assertSame('OLD COCK', $b->fresh()->sire_name);
        $this->assertSame('OLD COCK', $this->ancestor($c, 'SS')->name);
    }

    public function test_an_ancestor_added_behind_a_shared_bird_is_added_to_the_others(): void
    {
        [$a, $b, $c] = $this->family();
        // D already has a different dam recorded for OLD COCK; E has the same one
        $d = $this->pigeon('PHA-2025-4', ['sire_ring_number' => 'NL-2019-1234']);
        $this->row($d, 'SD', 'NL-2016-9999');
        $e = $this->pigeon('PHA-2025-5', ['sire_ring_number' => 'NL-2019-1234']);
        $this->row($e, 'SD', 'NL 2015 5555', ['name' => 'E SPELLING']);
        // F has OLD COCK so far back that his dam would fall off F's chart
        $f = $this->pigeon('PHA-2025-6', ['sire_ring_number' => 'NL-2023-1']);
        $this->row($f, 'SS', 'NL-2022-1');
        $this->row($f, 'SSS', 'NL-2021-1');
        $this->row($f, 'SSSS', 'NL-2019-1234');

        $this->save($a, 'SD', ['ring_number' => 'NL-2015-5555', 'name' => 'QUEEN', 'remarks' => '1st National'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeSync', ['updated' => ['PHA-2025-2', 'PHA-2025-3'], 'skipped' => ['PHA-2025-4']]);

        $this->assertSame('QUEEN', $this->ancestor($b, 'SD')->name);
        $this->assertSame('1st National', $this->ancestor($b, 'SD')->remarks);
        $this->assertSame('QUEEN', $this->ancestor($c, 'SSD')->name);
        $this->assertSame('NL-2016-9999', $this->ancestor($d, 'SD')->ring_number);
        $this->assertSame('E SPELLING', $this->ancestor($e, 'SD')->name);
        $this->assertNull($this->ancestor($f, 'SSSSD'));

        // Editing her afterwards reaches every copy, including the one E already had
        $this->save($a, 'SD', ['ring_number' => 'NL-2015-5555', 'name' => 'QUEEN MOTHER', 'remarks' => '1st National']);
        $this->assertSame('QUEEN MOTHER', $this->ancestor($b, 'SD')->name);
        $this->assertSame('QUEEN MOTHER', $this->ancestor($c, 'SSD')->name);
        $this->assertSame('QUEEN MOTHER', $this->ancestor($e, 'SD')->name);
    }

    public function test_removing_an_ancestor_behind_a_shared_bird_removes_the_same_bird_elsewhere(): void
    {
        [$a, $b, $c] = $this->family();
        $this->row($a, 'SD', 'NL-2015-5555');
        $this->row($b, 'SD', 'NL2015-5555');
        $this->row($b, 'SDS', 'NL-2010-1');
        $this->row($c, 'SSD', 'NL-2016-9999'); // a different dam: kept

        $this->remove($a, 'SD')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeSync', ['updated' => ['PHA-2025-2'], 'skipped' => []]);

        $this->assertNull($this->ancestor($a, 'SD'));
        $this->assertNull($this->ancestor($b, 'SD'));
        $this->assertNull($this->ancestor($b, 'SDS'));
        $this->assertNotNull($this->ancestor($c, 'SSD'));
    }

    public function test_removing_only_here(): void
    {
        [$a, $b] = $this->family();
        $this->row($a, 'SD', 'NL-2015-5555');
        $this->row($b, 'SD', 'NL-2015-5555');

        $this->remove($a, 'SD', sync: false)->assertSessionHasNoErrors();

        $this->assertNull($this->ancestor($a, 'SD'));
        $this->assertNotNull($this->ancestor($b, 'SD'));
    }

    public function test_hidden_and_ringless_records_are_not_synced(): void
    {
        [$a] = $this->family();
        // Sire now linked to a loft bird: the old typed sire no longer shows anywhere
        $loftSire = $this->pigeon('PHA-2020-1', ['gender' => 'male']);
        $linked = $this->pigeon('PHA-2025-7', ['sire_id' => $loftSire->id, 'sire_ring_number' => 'NL-2019-1234', 'sire_name' => 'OLD COCK']);
        // A row left behind a removed bird ("SS" is missing)
        $orphan = $this->pigeon('PHA-2025-8', ['sire_ring_number' => 'NL-2022-2']);
        $this->row($orphan, 'SSD', 'NL-2019-1234', ['name' => 'OLD COCK']);
        // Birds known only by name can't be matched
        $named = $this->pigeon('PHA-2025-9', ['sire_name' => 'NAMELESS']);
        $sameName = $this->pigeon('PHA-2025-10', ['sire_name' => 'NAMELESS']);

        $this->save($a, 'S', ['ring_number' => 'NL-2019-1234', 'name' => 'RENAMED', 'remarks' => 'Remark on A']);
        $this->save($named, 'S', ['name' => 'NAMELESS', 'color' => 'RED']);

        $this->assertSame('OLD COCK', $linked->fresh()->sire_name);
        $this->assertSame('OLD COCK', $this->ancestor($orphan, 'SSD')->name);
        $this->assertNull($sameName->fresh()->sire_color);
    }

    public function test_the_editor_shows_where_each_outside_bird_is_also_recorded(): void
    {
        [$a, $b, $c] = $this->family();

        $this->actingAs($this->user)
            ->get(route('pigeons.editPedigree', $a))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/EditPedigree')
                ->has('links.NL20191234', 3)
                ->where('links.NL20191234', fn ($records) => collect($records)->pluck('label')->sort()->values()->all() === ['PHA-2025-1', 'PHA-2025-2', 'PHA-2025-3'])
                ->where('links.NL20191234', fn ($records) => collect($records)->contains(fn ($record) => $record['anchor_id'] === $c->id && $record['path'] === 'SS'))
                ->where('syncReport', null)
            );

        $this->save($a, 'S', ['ring_number' => 'NL-2019-1234', 'name' => 'GOLDEN COCK', 'remarks' => 'Remark on A']);

        $this->actingAs($this->user)
            ->get(route('pigeons.editPedigree', $a))
            ->assertInertia(fn (Assert $page) => $page->where('syncReport.updated', ['PHA-2025-2', 'PHA-2025-3']));
    }

    public function test_changes_made_on_the_pigeon_form_update_the_other_records(): void
    {
        [$a, $b, $c] = $this->family();

        $this->actingAs($this->user)
            ->get(route('pigeons.edit', $a))
            ->assertInertia(fn (Assert $page) => $page
                ->where('outsideLinks.sire', ['PHA-2025-2', 'PHA-2025-3'])
                ->where('outsideLinks.dam', [])
            );

        $form = [
            'ring_number' => 'PHA-2025-1',
            'status' => 'stock',
            'sire_ring_number' => 'NL-2019-1234',
            'sire_name' => 'OLD COCK',
            'sire_color' => 'BLUE BAR',
            'sire_notes' => 'Remark on A',
        ];

        $this->actingAs($this->user)
            ->patch(route('pigeons.update', $a), $form)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeSync', ['updated' => ['PHA-2025-2', 'PHA-2025-3'], 'skipped' => []]);

        $this->assertSame('BLUE BAR', $b->fresh()->sire_color);
        $this->assertSame('BLUE BAR', $this->ancestor($c, 'SS')->color);

        $this->actingAs($this->user)
            ->patch(route('pigeons.update', $a), [...$form, 'sire_color' => 'RED', 'sync_sire' => 0])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('pedigreeSync');

        $this->assertSame('RED', $a->fresh()->sire_color);
        $this->assertSame('BLUE BAR', $b->fresh()->sire_color);
    }
}
