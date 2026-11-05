<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\Bloodline;
use App\Models\Clutch;
use App\Models\OlrRace;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\ImageUploadService;
use App\Services\Lineage\LineageService;
use App\Services\Merits\MeritSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Cross-user access found in the security audit: pairings, clutches, pedigree image removal, bloodline and clutch
 * ids on pigeons, and race rows that link one user's bird to another user's season.
 */
class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected User $victim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create();
        $this->victim = User::factory()->create();
    }

    private function pigeon(User $owner, string $ring, array $attributes = []): Pigeon
    {
        return Pigeon::create(['user_id' => $owner->id, 'status' => 'stock', 'ring_number' => $ring, ...$attributes]);
    }

    private function pairing(User $owner, string $suffix): Pairing
    {
        return Pairing::create([
            'user_id' => $owner->id,
            'sire_id' => $this->pigeon($owner, "COCK-{$suffix}", ['gender' => 'male'])->id,
            'dam_id' => $this->pigeon($owner, "HEN-{$suffix}", ['gender' => 'female'])->id,
            'pair_name' => "Pair {$suffix}",
            'status' => 'active',
            'current_clutch_number' => 0,
            'started_at' => now(),
        ]);
    }

    public function test_pairing_search_stays_within_my_pairings(): void
    {
        $this->pairing($this->me, 'MINE');
        $this->pairing($this->victim, 'THEIRS');

        $this->actingAs($this->me)
            ->get(route('pairings.index', ['search' => 'HEN']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pairings.data', 1)
                ->where('pairings.data.0.pair_name', 'Pair MINE'));
    }

    public function test_pairing_page_size_is_limited(): void
    {
        $this->actingAs($this->me)
            ->get(route('pairings.index', ['per_page' => 100000]))
            ->assertInertia(fn (Assert $page) => $page->where('pairings.per_page', 20));

        $this->actingAs($this->me)
            ->get(route('pairings.index', ['per_page' => 50]))
            ->assertInertia(fn (Assert $page) => $page->where('pairings.per_page', 50));
    }

    public function test_cannot_pair_another_users_birds(): void
    {
        $theirCock = $this->pigeon($this->victim, 'THEIR-COCK', ['gender' => 'male']);
        $theirHen = $this->pigeon($this->victim, 'THEIR-HEN', ['gender' => 'female']);

        $this->actingAs($this->me)
            ->post(route('pairings.store'), [
                'sire_id' => $theirCock->id,
                'dam_id' => $theirHen->id,
                'pair_name' => 'Stolen',
                'started_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors(['sire_id', 'dam_id']);

        $this->assertSame(0, Pairing::where('user_id', $this->me->id)->count());
        $this->assertSame('stock', $theirCock->fresh()->status);
        $this->assertSame('stock', $theirHen->fresh()->status);
    }

    public function test_cannot_touch_clutches_of_another_users_pairing(): void
    {
        $theirs = $this->pairing($this->victim, 'THEIRS');
        $clutch = $theirs->clutches()->create(['clutch_number' => 1, 'status' => 'pending']);

        $this->actingAs($this->me);

        $this->post(route('pairings.clutches.store', $theirs), ['notes' => 'x'])->assertNotFound();
        $this->patch(route('pairings.clutches.update', [$theirs, $clutch]), ['status' => 'failed'])->assertNotFound();
        $this->delete(route('pairings.clutches.destroy', [$theirs, $clutch]))->assertNotFound();

        $this->assertSame(1, $theirs->clutches()->count());
        $this->assertSame('pending', $clutch->fresh()->status);
        $this->assertSame(0, $theirs->fresh()->current_clutch_number);
    }

    public function test_my_clutches_still_work(): void
    {
        $mine = $this->pairing($this->me, 'MINE');

        $this->actingAs($this->me)
            ->post(route('pairings.clutches.store', $mine), ['notes' => 'First round'])
            ->assertRedirect(route('pairings.show', $mine));

        $this->assertSame(1, $mine->clutches()->count());
    }

    public function test_biological_pairing_must_be_mine(): void
    {
        $mine = $this->pairing($this->me, 'MINE');
        $theirs = $this->pairing($this->victim, 'THEIRS');
        $myOther = $this->pairing($this->me, 'OTHER');

        $this->actingAs($this->me)
            ->post(route('pairings.clutches.store', $mine), ['is_fostered' => true, 'biological_pairing_id' => $theirs->id])
            ->assertSessionHasErrors('biological_pairing_id');

        $this->actingAs($this->me)
            ->post(route('pairings.clutches.store', $mine), ['is_fostered' => true, 'biological_pairing_id' => $myOther->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($myOther->id, $mine->clutches()->sole()->biological_pairing_id);
    }

    public function test_only_my_own_pedigree_images_can_be_deleted(): void
    {
        $mine = 'https://cdn.example.test/pigeons/pedigree/mine.webp';
        $theirs = 'https://cdn.example.test/pigeons/photos/theirs.webp';
        $bird = $this->pigeon($this->me, 'MINE', ['pedigree_images' => [$mine]]);

        $this->mock(ImageUploadService::class, function (MockInterface $mock) use ($mine) {
            $mock->shouldReceive('deleteMultiple')->once()->with([$mine]);
            $mock->shouldNotReceive('delete');
        });

        $this->actingAs($this->me)
            ->put(route('pigeons.update', $bird), [
                'status' => 'stock',
                'ring_number' => 'MINE',
                'remove_pedigree' => [$theirs, $mine],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $bird->fresh()->pedigree_images);
    }

    public function test_another_users_bloodlines_are_ignored(): void
    {
        $theirLine = Bloodline::create(['user_id' => $this->victim->id, 'name' => 'SECRET LINE']);
        $myLine = Bloodline::create(['user_id' => $this->me->id, 'name' => 'JANSSEN']);

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), [
                'status' => 'stock',
                'ring_number' => 'NEW-1',
                'bloodlines' => [
                    ['id' => $theirLine->id, 'is_primary' => true],
                    ['id' => $myLine->id, 'is_primary' => false],
                ],
            ])
            ->assertSessionHasNoErrors();

        $bird = Pigeon::where('ring_number', 'NEW-1')->sole();
        $this->assertSame([$myLine->id], $bird->bloodlines()->pluck('bloodlines.id')->all());
        $this->assertNull($bird->bloodline);

        // A malformed bloodlines value is ignored rather than crashing
        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'NEW-2', 'bloodlines' => 'oops'])
            ->assertSessionHasNoErrors();
    }

    public function test_clutch_id_must_be_one_of_my_clutches(): void
    {
        $theirClutch = $this->pairing($this->victim, 'THEIRS')->clutches()->create(['clutch_number' => 1, 'status' => 'pending']);
        $myClutch = $this->pairing($this->me, 'MINE')->clutches()->create(['clutch_number' => 1, 'status' => 'pending']);

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'CUCKOO', 'clutch_id' => $theirClutch->id])
            ->assertSessionHasErrors('clutch_id');

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'CHICK', 'clutch_id' => $myClutch->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Pigeon::where('clutch_id', $theirClutch->id)->count());
        $this->assertInstanceOf(Clutch::class, Pigeon::where('ring_number', 'CHICK')->sole()->clutch);
    }

    public function test_results_planted_in_another_users_season_are_ignored(): void
    {
        // Rows like these could be written before nested routes were scoped: my bird in the victim's season
        $theirRace = OlrRace::create(['user_id' => $this->victim->id, 'name' => 'Their OLR']);
        $theirSeason = $theirRace->seasons()->create(['name' => 'Their season', 'year' => 2025, 'status' => 'completed']);
        $myBird = $this->pigeon($this->me, 'MINE');
        $theirSeason->entries()->attach($myBird->id, ['overall_position' => 1]);
        foreach (['Lap 1', 'Lap 2'] as $name) {
            $theirSeason->races()->create(['name' => $name, 'distance_unit' => 'km'])
                ->results()->attach($myBird->id, ['position' => 1, 'arrival_time' => '10:00:00', 'did_not_arrive' => false]);
        }

        $this->assertSame([], app(MeritSuggester::class)->forPigeons($this->me->id, [$myBird->id]));
        $this->assertSame([], app(LineageService::class)->raceRecords([$myBird->id]));
        $this->assertSame([], app(MeritSuggester::class)->season('olr', $theirSeason->id));

        // The same results in my own season still count
        $myRace = OlrRace::create(['user_id' => $this->me->id, 'name' => 'My OLR']);
        $mySeason = $myRace->seasons()->create(['name' => 'Mine', 'year' => 2025, 'status' => 'active']);
        $mySeason->entries()->attach($myBird->id);
        $mySeason->races()->create(['name' => 'Lap 1', 'distance_unit' => 'km'])
            ->results()->attach($myBird->id, ['position' => 1, 'arrival_time' => '10:00:00', 'did_not_arrive' => false]);

        $types = array_map(fn ($s) => $s['merit']['type'], app(MeritSuggester::class)->forPigeons($this->me->id, [$myBird->id])[$myBird->id]);
        $this->assertSame([MeritType::RaceWinner->value], $types);
        $this->assertSame(1, app(LineageService::class)->raceRecords([$myBird->id])[$myBird->id]['flown']);
    }
}
