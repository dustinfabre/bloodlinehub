<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Models\Bloodline;
use App\Models\Club;
use App\Models\OlrRace;
use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Second round of audit fixes: sales and the public marketplace, private parent notes, website links, image
 * sizes, club event entries, the cross-user cleanup command and trusted proxies.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected User $victim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create(['email' => 'me@example.test']);
        $this->victim = User::factory()->create(['email' => 'victim@example.test']);
    }

    private function pigeon(User $owner, string $ring, array $attributes = []): Pigeon
    {
        return Pigeon::create(['user_id' => $owner->id, 'status' => 'stock', 'ring_number' => $ring, ...$attributes]);
    }

    public function test_sales_only_take_my_own_unlisted_birds(): void
    {
        $theirs = $this->pigeon($this->victim, 'THEIRS');
        $mine = $this->pigeon($this->me, 'MINE');

        $this->actingAs($this->me)
            ->post(route('sales.store'), ['pigeon_id' => $theirs->id, 'price' => 100])
            ->assertSessionHasErrors('pigeon_id');

        $this->actingAs($this->me)
            ->post(route('sales.store'), ['pigeon_id' => $mine->id, 'price' => 100, 'contact' => '0917 123 4567'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->me)
            ->post(route('sales.store'), ['pigeon_id' => $mine->id, 'price' => 90])
            ->assertSessionHasErrors(['pigeon_id' => 'This pigeon is already listed for sale.']);

        $this->assertSame(1, Sale::count());
        $this->assertSame('0917 123 4567', Sale::sole()->contact);
    }

    public function test_sale_photos_must_be_web_addresses(): void
    {
        $mine = $this->pigeon($this->me, 'MINE');

        $this->actingAs($this->me)
            ->post(route('sales.store'), ['pigeon_id' => $mine->id, 'additional_photos' => ['javascript:alert(1)']])
            ->assertSessionHasErrors('additional_photos.0');
    }

    public function test_sales_page_loads_with_an_active_listing(): void
    {
        $mine = $this->pigeon($this->me, 'MINE', ['photo_url' => 'https://cdn.example.test/mine.webp']);
        Sale::create(['user_id' => $this->me->id, 'pigeon_id' => $mine->id, 'price' => 50, 'contact' => 'FB: My Loft']);

        $this->actingAs($this->me)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeSales.0.pigeon.photo_url', 'https://cdn.example.test/mine.webp')
                ->where('activeSales.0.contact', 'FB: My Loft'));
    }

    public function test_marketplace_shows_only_what_sellers_publish(): void
    {
        $mine = $this->pigeon($this->me, 'MINE', ['photo_url' => 'https://cdn.example.test/mine.webp', 'notes' => 'private notes']);
        $hidden = $this->pigeon($this->me, 'HIDDEN-PRICE');
        $theirs = $this->pigeon($this->victim, 'THEIRS');
        Sale::create(['user_id' => $this->me->id, 'pigeon_id' => $mine->id, 'price' => 250, 'contact' => '0917 123 4567',
            'additional_photos' => ['https://cdn.example.test/2.webp', 'javascript:alert(1)']]);
        Sale::create(['user_id' => $this->me->id, 'pigeon_id' => $hidden->id, 'price' => 999, 'hide_price' => true]);
        // Listed before sales were limited to the seller's own birds
        Sale::create(['user_id' => $this->me->id, 'pigeon_id' => $theirs->id, 'price' => 1]);

        $response = $this->get(route('marketplace.index'))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/Index')
            ->has('sales.data', 2)
            ->where('sales.total', 2));

        $listings = collect($response->viewData('page')['props']['sales']['data'])->keyBy('pigeon.ring_number');
        $this->assertSame('250.00', $listings['MINE']['price']);
        $this->assertSame('0917 123 4567', $listings['MINE']['contact']);
        $this->assertSame(['https://cdn.example.test/2.webp'], $listings['MINE']['additional_photos']);
        $this->assertSame('https://cdn.example.test/mine.webp', $listings['MINE']['pigeon']['photo_url']);
        $this->assertNull($listings['HIDDEN-PRICE']['price']);
        $this->assertSame($this->me->name, $listings['MINE']['seller']);

        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('me@example.test', $json);
        $this->assertStringNotContainsString('private notes', $json);
        $this->assertStringNotContainsString('999', $json);
        $this->assertArrayNotHasKey('id', $listings['MINE']['pigeon']);
    }

    public function test_deleting_a_parent_keeps_offspring_notes_about_it_private(): void
    {
        $sire = $this->pigeon($this->me, 'SIRE');
        $child = $this->pigeon($this->me, 'CHILD', ['sire_id' => $sire->id, 'sire_notes' => 'Bought cheap, bad temper', 'notes' => 'Good flyer']);

        $this->actingAs($this->me)->delete(route('pigeons.destroy', $sire))->assertRedirect();

        $child->refresh();
        $this->assertNull($child->sire_id);
        $this->assertNull($child->sire_notes);
        $this->assertSame("Good flyer\n\nNotes about former sire SIRE (removed from the loft):\nBought cheap, bad temper", $child->notes);

        // Nothing about it reaches the pedigree as remarks
        $this->actingAs($this->me)
            ->get(route('pigeons.pedigree', $child))
            ->assertInertia(fn (Assert $page) => $page->where('tree.sire', null));
    }

    public function test_website_links_must_be_http(): void
    {
        $this->actingAs($this->me)
            ->post(route('clubs.store'), ['name' => 'Bad', 'status' => 'active', 'website' => 'javascript:alert(document.cookie)'])
            ->assertSessionHasErrors('website');

        $this->actingAs($this->me)
            ->post(route('olr-races.store'), ['name' => 'Rockdove', 'status' => 'active', 'website' => 'rockdove.ph'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://rockdove.ph', OlrRace::where('name', 'Rockdove')->sole()->website);
        $this->assertSame(0, Club::count());
    }

    public function test_oversized_images_are_rejected(): void
    {
        $this->actingAs($this->me)
            ->post(route('pigeons.store'), [
                'status' => 'stock',
                'ring_number' => 'BIG',
                'photo' => UploadedFile::fake()->image('huge.png', 9000, 10),
            ])
            ->assertSessionHasErrors(['photo' => 'The photo can be at most 8000 × 8000 pixels.']);
    }

    public function test_club_events_only_take_birds_entered_in_the_season(): void
    {
        $club = Club::create(['user_id' => $this->me->id, 'name' => 'Manila RPC']);
        $season = $club->seasons()->create(['name' => 'YB', 'year' => 2025, 'status' => 'active']);
        $event = $season->events()->create(['name' => 'Basketing']);
        $entered = $this->pigeon($this->me, 'ENTERED');
        $notEntered = $this->pigeon($this->me, 'NOT-ENTERED');
        $season->entries()->attach($entered->id);

        $this->actingAs($this->me)
            ->post(route('clubs.seasons.events.entries.bulk', [$club, $season, $event]), ['pigeon_ids' => [$entered->id, $notEntered->id]])
            ->assertRedirect();
        $this->actingAs($this->me)
            ->post(route('clubs.seasons.events.entries.store', [$club, $season, $event]), ['pigeon_id' => $notEntered->id])
            ->assertNotFound();

        $this->assertSame([$entered->id], $event->entries()->pluck('pigeons.id')->all());
    }

    public function test_cleanup_command_finds_and_fixes_cross_user_rows(): void
    {
        $victimRace = OlrRace::create(['user_id' => $this->victim->id, 'name' => 'Their OLR']);
        $victimSeason = $victimRace->seasons()->create(['name' => 'S', 'year' => 2025, 'status' => 'active']);
        $myBird = $this->pigeon($this->me, 'MINE');
        $theirBird = $this->pigeon($this->victim, 'THEIRS', ['gender' => 'male']);
        $theirHen = $this->pigeon($this->victim, 'THEIR-HEN', ['gender' => 'female']);
        $victimSeason->entries()->attach($myBird->id);
        $victimSeason->races()->create(['name' => 'Lap 1', 'distance_unit' => 'km'])->results()->attach($myBird->id);
        $myBird->bloodlines()->attach(Bloodline::create(['user_id' => $this->victim->id, 'name' => 'THEIR LINE'])->id);
        Sale::create(['user_id' => $this->me->id, 'pigeon_id' => $theirBird->id]);
        $stolen = Pairing::create(['user_id' => $this->me->id, 'sire_id' => $theirBird->id, 'dam_id' => $theirHen->id, 'status' => 'active', 'current_clutch_number' => 0, 'started_at' => now()]);
        $theirPairing = Pairing::create(['user_id' => $this->victim->id, 'sire_id' => $theirBird->id, 'dam_id' => $theirHen->id, 'status' => 'active', 'current_clutch_number' => 1, 'started_at' => now()]);
        $theirClutch = $theirPairing->clutches()->create(['clutch_number' => 1, 'status' => 'pending']);
        $this->pigeon($this->me, 'CUCKOO', ['clutch_id' => $theirClutch->id]);

        $this->artisan('security:cross-user-rows')
            ->expectsTable(['Check', 'Rows', 'With --fix'], [
                ['OLR season entries', 1, 'delete'],
                ['OLR race results', 1, 'delete'],
                ['Club season entries', 0, '-'],
                ['Club race results', 0, '-'],
                ['Club event entries', 0, '-'],
                ['Bloodline links', 1, 'delete'],
                ['Sale listings for another user\'s bird', 1, 'delete'],
                ['Foster clutches pointing at another user\'s pairing', 0, '-'],
                ['Pigeons in another user\'s clutch', 1, 'clear clutch'],
                ['Pairings made from another user\'s birds', 1, 'list only: review by hand'],
            ])
            ->assertExitCode(1);

        $this->assertSame(1, $victimSeason->entries()->count());

        $this->artisan('security:cross-user-rows --fix')->assertExitCode(0);

        $this->assertSame(0, $victimSeason->entries()->count());
        $this->assertSame(0, DB::table('olr_race_results')->count());
        $this->assertSame(0, $myBird->bloodlines()->count());
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Pigeon::where('clutch_id', $theirClutch->id)->count());
        // Pairings are only listed
        $this->assertNotNull($stolen->fresh());
    }

    public function test_forwarded_addresses_are_only_trusted_when_configured(): void
    {
        $clientIp = function (): ?string {
            $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']);

            return app(TrustProxies::class)->handle($request, fn (Request $request) => response($request->ip()))->getContent();
        };

        config(['app.trusted_proxies' => null]);
        $this->assertSame('10.0.0.1', $clientIp());

        config(['app.trusted_proxies' => '10.0.0.1, 10.0.0.2']);
        $this->assertSame('203.0.113.7', $clientIp());

        config(['app.trusted_proxies' => '*']);
        $this->assertSame('203.0.113.7', $clientIp());
    }
}
