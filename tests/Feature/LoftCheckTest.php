<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LoftCheck;
use App\Models\Pigeon;
use App\Models\PigeonStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Loft checks: count a group of birds (by location, ring series and status), mark the ones not seen as missing,
 * and keep a history of when each bird went missing or came back.
 */
class LoftCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function bird(string $ring, string $status = 'racing', array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'ring_number' => $ring,
            'status' => $status,
            ...$attributes,
        ]);
    }

    private function location(string $name, string $type = 'manual'): Location
    {
        return Location::create(['user_id' => $this->user->id, 'name' => $name, 'type' => $type]);
    }

    /**
     * The create page's props for a scope.
     */
    private function board(array $query = []): array
    {
        $props = [];

        $this->actingAs($this->user)
            ->get(route('loft-checks.create', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $page->component('loft-checks/Create');
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    /**
     * @return list<string>
     */
    private function rings(array $birds): array
    {
        return array_column($birds, 'ring_number');
    }

    public function test_board_lists_birds_in_the_loft_in_ring_order(): void
    {
        $this->bird('PHA-2026-1010');
        $this->bird('PHA-2026-1002', 'stock');
        $this->bird('PHA-2026-1003', 'missing');
        $this->bird('PHA-2026-1004', 'deceased');
        $this->bird('PHA-2026-1001', 'injured');
        $this->bird('OTHER-USER-1', owner: User::factory()->create());

        $props = $this->board();

        $this->assertSame(['PHA-2026-1001', 'PHA-2026-1002', 'PHA-2026-1010'], $this->rings($props['birds']));
        // A missing bird can still be ticked if it came back
        $this->assertSame(['PHA-2026-1003'], $this->rings($props['missingBirds']));
        $this->assertSame('All locations', $props['label']);
        // Each bird has its place in the series and the standard search details
        $this->assertSame(['PHA-2026', '1001'], [$props['birds'][0]['series'], $props['birds'][0]['number']]);
        $this->assertArrayHasKey('sire', $props['birds'][0]);
    }

    public function test_board_can_count_one_location_and_leaves_out_birds_at_an_olr(): void
    {
        $youngBirds = $this->location('Young bird loft');
        $olr = $this->location('Rockdove OLR', 'olr');
        $this->bird('PHA-2026-1001', attributes: ['location_id' => $youngBirds->id]);
        $this->bird('PHA-2026-1002', attributes: ['location_id' => $olr->id]);
        $this->bird('PHA-2026-1003');

        $this->assertSame(['PHA-2026-1001', 'PHA-2026-1003'], $this->rings($this->board()['birds']));

        $props = $this->board(['location' => $youngBirds->id]);
        $this->assertSame(['PHA-2026-1001'], $this->rings($props['birds']));
        $this->assertSame('Young bird loft', $props['label']);
        // Only home locations are offered
        $this->assertSame(['Young bird loft'], array_column($props['locations'], 'name'));

        $this->assertSame(['PHA-2026-1003'], $this->rings($this->board(['location' => 'none'])['birds']));
    }

    public function test_board_can_count_one_ring_series(): void
    {
        $this->bird('PHA-2026-1001');
        $this->bird('PHA 2026 1002');
        $this->bird('PHA-2025-0500');
        $this->bird('12345');
        $this->bird('', attributes: ['personal_number' => 'P-7']);

        $props = $this->board(['series' => 'pha 2026']);

        $this->assertSame(['PHA-2026-1001', 'PHA 2026 1002'], $this->rings($props['birds']));
        $this->assertSame('All locations · PHA-2026', $props['label']);
        $this->assertSame(
            [
                ['value' => 'PHA-2026', 'label' => 'PHA-2026', 'count' => 2, 'from' => '1001', 'to' => '1002'],
                ['value' => 'digits', 'label' => 'Numbers only', 'count' => 1, 'from' => '12345', 'to' => '12345'],
                ['value' => 'PHA-2025', 'label' => 'PHA-2025', 'count' => 1, 'from' => '0500', 'to' => '0500'],
                ['value' => 'none', 'label' => 'No ring series', 'count' => 1, 'from' => null, 'to' => null],
            ],
            $props['seriesOptions'],
        );

        $this->assertSame(['P-7'], array_column($this->board(['series' => 'none'])['birds'], 'personal_number'));
    }

    public function test_board_can_count_some_statuses(): void
    {
        $this->bird('PHA-2026-1001', 'racing');
        $this->bird('PHA-2026-1002', 'breeding');
        $this->bird('PHA-2026-1003', 'stock');

        $props = $this->board(['status' => ['racing', 'stock', 'deceased']]);

        $this->assertSame(['PHA-2026-1001', 'PHA-2026-1003'], $this->rings($props['birds']));
        // Kept in the standard order; statuses for birds no longer in the loft are dropped
        $this->assertSame(['stock', 'racing'], $props['scope']['status']);
        $this->assertSame('All locations · In Stock, Racing', $props['label']);
    }

    public function test_saving_a_check_marks_birds_missing_and_records_when(): void
    {
        $loft = $this->location('Young bird loft');
        $seen = $this->bird('PHA-2026-1001', attributes: ['location_id' => $loft->id]);
        $lost = $this->bird('PHA-2026-1002', attributes: ['location_id' => $loft->id]);
        $notSure = $this->bird('PHA-2026-1003', 'injured', ['location_id' => $loft->id]);
        $elsewhere = $this->bird('PHA-2026-1004');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), [
                'location' => (string) $loft->id,
                'present' => [$seen->id],
                'missing' => [$lost->id],
                'note' => 'Training toss, Tarlac 40 km',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('loft-checks.show', LoftCheck::first()));

        $check = LoftCheck::first();
        $this->assertSame('Young bird loft', $check->label);
        $this->assertSame('Training toss, Tarlac 40 km', $check->note);
        $this->assertSame(['location' => (string) $loft->id, 'series' => 'all', 'status' => Pigeon::IN_LOFT_STATUSES], $check->scope);
        $this->assertSame([3, 1, 1, 1, 0], [$check->expected_count, $check->present_count, $check->missing_count, $check->unchecked_count, $check->returned_count]);
        $this->assertEqualsCanonicalizing(
            [$seen->id => 'present', $lost->id => 'missing', $notSure->id => 'unchecked'],
            $check->pigeons->pluck('pivot.result', 'id')->all(),
        );

        $this->assertSame('missing', $lost->refresh()->status);
        // Birds not seen but not marked missing stay as they were
        $this->assertSame('injured', $notSure->refresh()->status);
        $this->assertSame('racing', $seen->refresh()->status);
        $this->assertSame('racing', $elsewhere->refresh()->status);

        $change = PigeonStatusChange::where('pigeon_id', $lost->id)->sole();
        $this->assertSame(['racing', 'missing', $check->id], [$change->from_status, $change->to_status, $change->loft_check_id]);
        $this->assertNotNull($lost->refresh()->statusSince());
        $this->assertSame(1, PigeonStatusChange::count());
    }

    public function test_a_missing_bird_that_came_back_gets_its_old_status_back(): void
    {
        $bird = $this->bird('PHA-2026-1001', 'breeding');
        $bird->update(['status' => 'missing']);
        $neverRecorded = $this->bird('PHA-2026-1002', 'missing');
        $stillGone = $this->bird('PHA-2026-1003', 'missing');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), ['returned' => [$bird->id, $neverRecorded->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame('breeding', $bird->refresh()->status);
        // No record of the status it had before going missing
        $this->assertSame('stock', $neverRecorded->refresh()->status);
        $this->assertSame('missing', $stillGone->refresh()->status);

        $check = LoftCheck::first();
        $this->assertSame(2, $check->returned_count);
        $this->assertSame(
            ['missing', 'breeding', $check->id],
            [$bird->statusChanges()->first()->from_status, $bird->statusChanges()->first()->to_status, $bird->statusChanges()->first()->loft_check_id],
        );
    }

    public function test_only_birds_of_the_group_are_changed(): void
    {
        $loft = $this->location('Young bird loft');
        $inGroup = $this->bird('PHA-2026-1001', attributes: ['location_id' => $loft->id]);
        $outside = $this->bird('PHA-2026-1002');
        $deceased = $this->bird('PHA-2026-1003', 'deceased', ['location_id' => $loft->id]);
        $theirs = $this->bird('PHA-2026-1004', owner: User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), [
                'location' => (string) $loft->id,
                'missing' => [$inGroup->id, $outside->id, $deceased->id, $theirs->id],
                'returned' => [$theirs->id, $outside->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('missing', $inGroup->refresh()->status);
        $this->assertSame('racing', $outside->refresh()->status);
        $this->assertSame('deceased', $deceased->refresh()->status);
        $this->assertSame('racing', $theirs->refresh()->status);
        $this->assertSame(1, LoftCheck::first()->missing_count);
    }

    public function test_a_bird_ticked_present_is_never_marked_missing(): void
    {
        $bird = $this->bird('PHA-2026-1001');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), ['present' => [$bird->id], 'missing' => [$bird->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame('racing', $bird->refresh()->status);
        $this->assertSame(1, LoftCheck::first()->present_count);
    }

    public function test_cannot_check_someone_elses_location_or_an_empty_group(): void
    {
        $other = User::factory()->create();
        $theirLoft = Location::create(['user_id' => $other->id, 'name' => 'Their loft', 'type' => 'manual']);

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), ['location' => (string) $theirLoft->id])
            ->assertSessionHasErrors('location');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), [])
            ->assertSessionHasErrors('present');

        $this->assertSame(0, LoftCheck::count());
    }

    public function test_history_lists_checks_and_where_the_missing_birds_are_now(): void
    {
        $lost = $this->bird('PHA-2026-1001');
        $back = $this->bird('PHA-2026-1002');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), ['missing' => [$lost->id, $back->id]]);
        $back->refresh()->update(['status' => 'racing']);

        $this->actingAs($this->user)
            ->get(route('loft-checks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('loft-checks/Index')
                ->has('checks.data', 1)
                ->where('checks.data.0.missing_count', 2)
                ->where('checks.data.0.missing.0.ring_number', 'PHA-2026-1001')
                ->where('checks.data.0.missing.0.status', 'missing')
                ->where('checks.data.0.missing.1.status', 'racing')
                ->where('missingNow', 1));
    }

    public function test_show_groups_the_birds_by_result(): void
    {
        $seen = $this->bird('PHA-2026-1001', attributes: ['notes' => 'Private note']);
        $lost = $this->bird('PHA-2026-1002');

        $this->actingAs($this->user)
            ->post(route('loft-checks.store'), ['present' => [$seen->id], 'missing' => [$lost->id]]);

        $this->actingAs($this->user)
            ->get(route('loft-checks.show', LoftCheck::first()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('loft-checks/Show')
                ->where('present.0.ring_number', 'PHA-2026-1001')
                ->where('present.0.notes', 'Private note')
                ->where('missing.0.ring_number', 'PHA-2026-1002')
                ->where('missing.0.status', 'missing')
                ->has('unchecked', 0)
                ->has('returned', 0));
    }

    public function test_checks_are_private_and_deleting_one_keeps_the_statuses(): void
    {
        $lost = $this->bird('PHA-2026-1001');
        $this->actingAs($this->user)->post(route('loft-checks.store'), ['missing' => [$lost->id]]);
        $check = LoftCheck::first();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('loft-checks.show', $check))->assertNotFound();
        $this->actingAs($other)->delete(route('loft-checks.destroy', $check))->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('loft-checks.destroy', $check))
            ->assertRedirect(route('loft-checks.index'));

        $this->assertSame(0, LoftCheck::count());
        $this->assertSame('missing', $lost->refresh()->status);
        $this->assertNull(PigeonStatusChange::where('pigeon_id', $lost->id)->sole()->loft_check_id);
    }

    public function test_board_shows_the_last_count_of_the_same_group(): void
    {
        $bird = $this->bird('PHA-2026-1001');
        $this->actingAs($this->user)->post(route('loft-checks.store'), ['present' => [$bird->id]]);
        $this->actingAs($this->user)->post(route('loft-checks.store'), ['present' => [$bird->id], 'series' => 'PHA-2026']);

        $this->assertSame('All locations', $this->board()['lastCheck']['label']);
        $this->assertSame('All locations · PHA-2026', $this->board(['series' => 'PHA-2026'])['lastCheck']['label']);
        $this->assertNull($this->board(['status' => ['racing']])['lastCheck']);
    }

    public function test_every_status_change_is_recorded(): void
    {
        $bird = $this->bird('PHA-2026-1001');

        $bird->update(['name' => 'Blue Bar']);
        $this->assertSame(0, $bird->statusChanges()->count());

        $this->actingAs($this->user)->patch(route('pigeons.status', $bird), ['status' => 'missing']);
        $this->actingAs($this->user)->patch(route('pigeons.status', $bird), ['status' => 'racing']);

        $this->assertSame(
            [['missing', 'racing'], ['racing', 'missing']],
            $bird->statusChanges()->get()->map(fn ($change) => [$change->from_status, $change->to_status])->all(),
        );

        $this->actingAs($this->user)
            ->get(route('pigeons.show', $bird))
            ->assertInertia(fn (Assert $page) => $page
                ->has('statusHistory', 2)
                ->where('statusHistory.0.to_status', 'racing')
                ->where('statusHistory.0.loft_check', null));
    }

    public function test_pigeon_list_shows_since_when_a_bird_is_missing(): void
    {
        $bird = $this->bird('PHA-2026-1001');
        $bird->update(['status' => 'missing']);
        $this->bird('PHA-2026-1002', 'missing');

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['status' => 'missing', 'sort' => 'ring']))
            ->assertInertia(function (Assert $page) {
                $cards = collect($page->toArray()['props']['pigeons']['data'])->keyBy('ring_number');
                $this->assertNotNull($cards['PHA-2026-1001']['status_since']);
                // Went missing before the history was kept
                $this->assertNull($cards['PHA-2026-1002']['status_since']);
            });
    }
}
