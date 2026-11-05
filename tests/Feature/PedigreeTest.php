<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Pedigree;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

    public function test_owner_can_view_pedigree_with_remarks_but_not_notes(): void
    {
        $sire = $this->pigeon([
            'gender' => 'male',
            'ring_number' => 'BE-2020-111111',
            'remarks' => '1st Bourges 2022',
            'notes' => 'Private breeding notes',
        ]);
        $sire->merits()->create(['type' => MeritType::RaceWinner, 'year' => 2022]);
        $subject = $this->pigeon([
            'ring_number' => 'BE-2024-123456',
            'sire_id' => $sire->id,
            'dam_name' => 'EXTERNAL HEN',
            'dam_ring_number' => 'NL-2019-654321',
            'dam_notes' => 'Bought from a top loft',
            'dam_merits' => [['type' => 'super_breeder', 'level' => null, 'year' => null, 'detail' => null]],
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
                // Merits carry their fixed color and weight; highlight colors are gone
                ->where('tree.sire.merits.0.color', MeritType::RaceWinner->color())
                ->where('tree.sire.merits.0.weight', MeritType::RaceWinner->weight())
                ->missing('tree.sire.highlight')
                ->where('tree.dam.external', true)
                ->where('tree.dam.ring_number', 'NL-2019-654321')
                ->where('tree.dam.remarks', 'Bought from a top loft')
                ->where('tree.dam.merits.0.color', MeritType::SuperBreeder->color())
                ->missing('tree.dam.highlight')
                ->where('meta.is_public', false)
                ->where('meta.visibility', 'private')
                ->where('sharing.has_password', false)
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
            ->patch(route('pigeons.pedigreeVisibility', $subject), ['visibility' => 'public'])
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
                ->where('locked', 'private')
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
            ->patch(route('pigeons.pedigreeVisibility', $subject), ['visibility' => 'public'])
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
                ->where('meta.visibility', 'public')
            );
    }

    public function test_unknown_serial_returns_not_found(): void
    {
        $this->get(route('pedigrees.shared', 'BLH-0000-0000'))->assertNotFound();
    }

    public function test_highlight_colors_are_removed(): void
    {
        $this->assertFalse(Schema::hasTable('color_tags'));
        $this->assertFalse(Schema::hasColumn('pigeons', 'color_tag_id'));
        $this->assertFalse(Schema::hasColumn('pigeons', 'sire_color_tag_id'));
        $this->assertFalse(Schema::hasColumn('pigeons', 'dam_color_tag_id'));
        $this->assertFalse(Schema::hasColumn('pedigree_ancestors', 'color_tag_id'));
        $this->assertFalse(Route::has('color-tags.index'));

        // A form still sending the old fields saves the bird and ignores them
        $this->actingAs($this->user)
            ->post(route('pigeons.store'), [
                'ring_number' => 'BE-2025-100001',
                'status' => 'stock',
                'color_tag_id' => 5,
                'sire_name' => 'OLD SIRE',
                'sire_color_tag_id' => 5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('OLD SIRE', Pigeon::where('ring_number', 'BE-2025-100001')->sole()->sire_name);
    }

    public function test_every_merit_has_its_own_readable_color(): void
    {
        $luminance = function (string $hex): float {
            $channels = array_map(function (int $value) {
                $channel = $value / 255;

                return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }, sscanf($hex, '#%02x%02x%02x'));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $ink = $luminance('#14243A');

        $colors = array_map(fn (MeritType $type) => $type->color(), MeritType::cases());
        $this->assertCount(count($colors), array_unique($colors));

        foreach (MeritType::cases() as $type) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $type->color());
            $fill = $luminance($type->color());
            // The chip text is white or navy, whichever reads better (resources/js/lib/merits.ts)
            $best = max(1.05 / ($fill + 0.05), ($fill + 0.05) / ($ink + 0.05));
            $this->assertGreaterThanOrEqual(4.5, $best, "{$type->code()} chip text contrast");
        }

        $this->assertSame(
            ['value', 'label', 'code', 'description', 'category', 'color', 'weight'],
            array_keys(MeritType::options()[0]),
        );
    }

    public function test_show_page_includes_parent_remarks_and_notes(): void
    {
        $sire = $this->pigeon([
            'gender' => 'male',
            'name' => 'THUNDER',
            'remarks' => '1st Club 300km',
            'notes' => 'Prefers the north loft',
        ]);
        $subject = $this->pigeon([
            'sire_id' => $sire->id,
            'dam_name' => 'EXTERNAL HEN',
            'dam_notes' => 'Daughter of a national winner',
        ]);

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Show')
                ->where('pigeon.sire.name', 'THUNDER')
                ->where('pigeon.sire.remarks', '1st Club 300km')
                ->where('pigeon.sire.notes', 'Prefers the north loft')
                ->missing('pigeon.sire.color_tag')
                ->where('pigeon.dam_notes', 'Daughter of a national winner')
                ->missing('pigeon.dam_color_tag')
                ->missing('pigeon.color_tag')
                ->where('pigeon.pedigree', null)
            );
    }

    public function test_show_page_lists_offspring_with_their_other_parent(): void
    {
        $sire = $this->pigeon(['gender' => 'male', 'name' => 'THUNDER']);
        $dam = $this->pigeon(['gender' => 'female', 'name' => 'LIGHTNING']);
        $this->pigeon(['name' => 'OLDER', 'sire_id' => $sire->id, 'dam_id' => $dam->id, 'hatch_date' => '2023-04-01']);
        $this->pigeon(['name' => 'YOUNGER', 'sire_id' => $sire->id, 'dam_name' => 'EXTERNAL HEN', 'hatch_date' => '2024-04-01']);
        $this->pigeon(['name' => 'UNRELATED']);

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $sire))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Show')
                ->has('children', 2)
                ->where('children.0.name', 'YOUNGER')
                ->where('children.0.mate.side', 'dam')
                ->where('children.0.mate.id', null)
                ->where('children.0.mate.name', 'EXTERNAL HEN')
                ->where('children.1.name', 'OLDER')
                ->where('children.1.mate.id', $dam->id)
            );

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $dam))
            ->assertInertia(fn (Assert $page) => $page
                ->has('children', 1)
                ->where('children.0.name', 'OLDER')
                ->where('children.0.mate.side', 'sire')
                ->where('children.0.mate.name', 'THUNDER')
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
