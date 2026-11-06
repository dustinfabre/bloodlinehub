<?php

namespace Tests\Feature;

use App\Models\Bloodline;
use App\Models\Location;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PigeonIndexFilterTest extends TestCase
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
        return Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ringsFor(array $query): array
    {
        $rings = [];

        $this->actingAs($this->user)
            ->get(route('pigeons.index', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rings) {
                $rings = collect($page->toArray()['props']['pigeons']['data'])->pluck('ring_number')->sort()->values()->all();
            });

        return $rings;
    }

    public function test_status_filter_accepts_multiple_values(): void
    {
        $this->pigeon('R-1', ['status' => 'racing']);
        $this->pigeon('R-2', ['status' => 'breeding']);
        $this->pigeon('R-3', ['status' => 'stock']);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['status' => ['racing', 'breeding']]));
        // A single value still works for old links
        $this->assertSame(['R-3'], $this->ringsFor(['status' => 'stock']));
    }

    public function test_the_list_shows_birds_in_the_loft_by_default(): void
    {
        foreach (Pigeon::STATUSES as $status) {
            $this->pigeon(strtoupper($status), ['status' => $status]);
        }

        $this->assertSame(['BREEDING', 'INJURED', 'RACING', 'STOCK'], $this->ringsFor([]));

        $this->actingAs($this->user)
            ->get(route('pigeons.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', ['stock', 'racing', 'breeding', 'injured'])
                ->where('inLoftStatuses', ['stock', 'racing', 'breeding', 'injured'])
                ->where('pigeons.total', 4)
            );
    }

    public function test_gone_birds_can_still_be_listed(): void
    {
        $this->pigeon('R-1', ['status' => 'racing']);
        $this->pigeon('R-2', ['status' => 'deceased']);
        $this->pigeon('R-3', ['status' => 'missing']);
        $this->pigeon('R-4', ['status' => 'flyaway']);

        $this->assertSame(['R-1', 'R-2', 'R-3', 'R-4'], $this->ringsFor(['status' => 'all']));
        $this->assertSame(['R-2', 'R-4'], $this->ringsFor(['status' => ['deceased', 'flyaway']]));

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['status' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', []));
    }

    public function test_unknown_status_values_are_ignored(): void
    {
        $this->pigeon('R-1', ['status' => 'racing']);
        $this->pigeon('R-2', ['status' => 'stock']);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['status' => ['bogus']]));
    }

    public function test_bloodline_filter_matches_any_selected_line(): void
    {
        $janssen = Bloodline::create(['user_id' => $this->user->id, 'name' => 'JANSSEN']);
        $sion = Bloodline::create(['user_id' => $this->user->id, 'name' => 'SION']);
        $other = Bloodline::create(['user_id' => $this->user->id, 'name' => 'OTHER']);

        $this->pigeon('R-1')->bloodlines()->attach($janssen->id, ['is_primary' => true]);
        // Secondary lines count too
        $this->pigeon('R-2')->bloodlines()->attach([$other->id => ['is_primary' => true], $sion->id => ['is_primary' => false]]);
        $this->pigeon('R-3')->bloodlines()->attach($other->id, ['is_primary' => true]);

        $this->assertSame(['R-1', 'R-2'], $this->ringsFor(['bloodline' => [$janssen->id, $sion->id]]));
    }

    public function test_location_filter_supports_ids_and_no_location(): void
    {
        $lofts = Location::create(['user_id' => $this->user->id, 'name' => 'Main loft', 'type' => 'manual']);
        $breeding = Location::create(['user_id' => $this->user->id, 'name' => 'Breeding loft', 'type' => 'manual']);

        $this->pigeon('R-1', ['location_id' => $lofts->id]);
        $this->pigeon('R-2', ['location_id' => $breeding->id]);
        $this->pigeon('R-3');

        $this->assertSame(['R-1'], $this->ringsFor(['location' => [$lofts->id]]));
        $this->assertSame(['R-1', 'R-3'], $this->ringsFor(['location' => [$lofts->id, 'none']]));
    }

    public function test_filters_combine_and_are_echoed_back_as_arrays(): void
    {
        $loft = Location::create(['user_id' => $this->user->id, 'name' => 'Main loft', 'type' => 'manual']);
        $this->pigeon('R-1', ['status' => 'racing', 'gender' => 'male', 'location_id' => $loft->id]);
        $this->pigeon('R-2', ['status' => 'racing', 'gender' => 'female', 'location_id' => $loft->id]);
        $this->pigeon('R-3', ['status' => 'stock', 'gender' => 'male', 'location_id' => $loft->id]);

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['status' => ['racing'], 'gender' => 'male', 'location' => [$loft->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Index')
                ->has('pigeons.data', 1)
                ->where('pigeons.data.0.ring_number', 'R-1')
                ->where('pigeons.data.0.location.name', 'Main loft')
                ->where('filters.status', ['racing'])
                ->where('filters.location', [(string) $loft->id])
                ->where('filters.bloodline', [])
                ->has('locations', 1)
            );
    }

    public function test_per_page_accepts_supported_sizes_only(): void
    {
        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['per_page' => 24]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 24));

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['per_page' => 1000]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 12));
    }

    /**
     * Ring numbers in the order the page lists them.
     *
     * @return list<string>
     */
    private function orderFor(array $query): array
    {
        $rings = [];

        $this->actingAs($this->user)
            ->get(route('pigeons.index', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rings) {
                $rings = collect($page->toArray()['props']['pigeons']['data'])->pluck('ring_number')->all();
            });

        return $rings;
    }

    public function test_gender_filter_takes_several_values_including_ungendered(): void
    {
        $this->pigeon('COCK', ['gender' => 'male']);
        $this->pigeon('HEN', ['gender' => 'female']);
        $this->pigeon('UNKNOWN', ['gender' => null]);

        $this->assertSame(['UNKNOWN'], $this->ringsFor(['gender' => ['none']]));
        $this->assertSame(['COCK', 'UNKNOWN'], $this->ringsFor(['gender' => ['male', 'none']]));
        $this->assertSame(['COCK', 'HEN'], $this->ringsFor(['gender' => ['male', 'female']]));
        // Old single-value links keep working; unknown values are ignored
        $this->assertSame(['HEN'], $this->ringsFor(['gender' => 'female']));
        $this->assertSame(['COCK', 'HEN', 'UNKNOWN'], $this->ringsFor(['gender' => ['other']]));

        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['gender' => ['none', 'male', 'bogus']]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.gender', ['none', 'male']));
    }

    public function test_pigeons_can_be_sorted(): void
    {
        // Added in this order: 300 three days ago, 200 two days ago, 100 yesterday
        $added = fn (Pigeon $pigeon, int $daysAgo) => $pigeon->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        $added($this->pigeon('BE-2022-300', ['name' => 'Zorro', 'hatch_date' => '2022-03-01']), 3);
        $added($this->pigeon('BE-2024-100', ['name' => 'alpha', 'hatch_date' => '2024-01-10']), 1);
        $added($this->pigeon('BE-2023-200', ['name' => null, 'hatch_date' => null]), 2);

        $this->assertSame(['BE-2024-100', 'BE-2023-200', 'BE-2022-300'], $this->orderFor([]));
        $this->assertSame(['BE-2022-300', 'BE-2023-200', 'BE-2024-100'], $this->orderFor(['sort' => 'ring']));
        $this->assertSame(['BE-2024-100', 'BE-2023-200', 'BE-2022-300'], $this->orderFor(['sort' => 'ring_desc']));
        // Case-insensitive, unnamed birds last
        $this->assertSame(['BE-2024-100', 'BE-2022-300', 'BE-2023-200'], $this->orderFor(['sort' => 'name']));
        // Birds without a hatch date last either way
        $this->assertSame(['BE-2024-100', 'BE-2022-300', 'BE-2023-200'], $this->orderFor(['sort' => 'youngest']));
        $this->assertSame(['BE-2022-300', 'BE-2024-100', 'BE-2023-200'], $this->orderFor(['sort' => 'oldest']));
        // Unknown sorts fall back to the default
        $this->assertSame(['BE-2024-100', 'BE-2023-200', 'BE-2022-300'], $this->orderFor(['sort' => 'drop table']));
    }

    public function test_best_merits_first_uses_merit_weights(): void
    {
        $finisher = $this->pigeon('FINISHER');
        $finisher->merits()->createMany([['type' => 'finisher'], ['type' => 'finisher'], ['type' => 'finisher']]);
        $this->pigeon('NONE');
        $this->pigeon('SUPER-BREEDER')->merits()->create(['type' => 'super_breeder']);
        $this->pigeon('ACE')->merits()->create(['type' => 'ace_pigeon']);

        // Super Breeder 9 > Ace 8 > three Finishers 3 > nothing
        $this->assertSame(['SUPER-BREEDER', 'ACE', 'FINISHER', 'NONE'], $this->orderFor(['sort' => 'merits']));
    }

    public function test_sort_options_and_choice_are_sent_to_the_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('pigeons.index', ['sort' => 'oldest']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'oldest')
                ->has('sorts', 7)
                ->where('sorts.0', ['value' => 'recent', 'label' => 'Recently added']));
    }
}
