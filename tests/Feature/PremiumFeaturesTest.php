<?php

namespace Tests\Feature;

use App\Models\BreederProfile;
use App\Models\Pigeon;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What the free plan includes and what only Premium unlocks (App\Support\PremiumFeature).
 */
class PremiumFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $free;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->free = User::factory()->create();
    }

    private function bird(User $owner, string $ring, string $gender, ?Pigeon $sire = null, ?Pigeon $dam = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => $owner->id,
            'status' => 'stock',
            'gender' => $gender,
            'ring_number' => $ring,
            'sire_id' => $sire?->id,
            'dam_id' => $dam?->id,
        ]);
    }

    /**
     * A bird bred from half-siblings, so it has a COI and a Pairing Analysis of its parents.
     */
    private function inbredBird(User $owner): Pigeon
    {
        $grandsire = $this->bird($owner, 'GRANDSIRE', 'male');
        $sire = $this->bird($owner, 'SIRE', 'male', $grandsire, $this->bird($owner, 'GRANDDAM-1', 'female'));
        $dam = $this->bird($owner, 'DAM', 'female', $grandsire, $this->bird($owner, 'GRANDDAM-2', 'female'));

        return $this->bird($owner, 'BIRD', 'male', $sire, $dam);
    }

    private function withContact(User $user): void
    {
        BreederProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Cruz Loft',
            'phone' => '0917 123 4567',
            'email' => 'loft@example.test',
            'address' => 'Lipa, Batangas',
            'show_phone' => true,
            'show_email' => true,
            'show_address' => true,
        ]);
    }

    public function test_the_premium_flag_is_shared_with_every_page(): void
    {
        $this->actingAs($this->free)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.premium', false));

        $this->actingAs(User::factory()->premium()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.premium', true));
    }

    public function test_free_accounts_keep_the_free_sections(): void
    {
        foreach (['pigeons.index', 'pairings.index', 'lineage.index', 'locations.index', 'olr-races.index', 'clubs.index', 'sales.index'] as $route) {
            $this->actingAs($this->free)->get(route($route))->assertOk();
        }
    }

    public function test_premium_pages_explain_the_feature_instead(): void
    {
        $this->actingAs($this->free)->get(route('loft-checks.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('premium/Required')
                ->where('feature.key', 'loft-check'));

        $this->actingAs($this->free)->get(route('pairing-analysis.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('premium/Required')
                ->where('feature.key', 'pairing-analysis'));
    }

    public function test_premium_actions_are_refused(): void
    {
        $bird = $this->bird($this->free, 'PH-1', 'male');
        $hen = $this->bird($this->free, 'PH-2', 'female');

        $this->actingAs($this->free)->post(route('loft-checks.store'), ['missing' => [$bird->id]])->assertForbidden();
        $this->actingAs($this->free)->post(route('planned-pairings.store'), ['sire_id' => $bird->id, 'dam_id' => $hen->id])
            ->assertForbidden();
        $this->actingAs($this->free)->getJson(route('pigeons.pedigreeCopySources', $bird))->assertForbidden();
        $this->actingAs($this->free)->post(route('pigeons.copyPedigree', $bird), ['source' => $hen->id])->assertForbidden();

        $this->assertDatabaseCount('loft_checks', 0);
        $this->assertDatabaseCount('planned_pairings', 0);
    }

    public function test_premium_unlocks_them(): void
    {
        $premium = User::factory()->premium()->create();

        $this->actingAs($premium)->get(route('loft-checks.index'))->assertOk();
        $this->actingAs($premium)->get(route('pairing-analysis.index'))->assertOk();
    }

    public function test_premium_locks_again_when_it_runs_out(): void
    {
        $lapsed = User::factory()->create(['premium_until' => now()->subDay()]);

        $this->actingAs($lapsed)->get(route('loft-checks.index'))->assertForbidden();
    }

    public function test_admins_get_every_feature_without_paying(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get(route('loft-checks.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.premium', true));
    }

    public function test_the_feature_page_can_be_opened_directly(): void
    {
        $this->actingAs($this->free)->get(route('premium.show', 'pedigree-export'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('premium/Required')->where('feature.key', 'pedigree-export'));

        $this->actingAs($this->free)->get('/premium/not-a-feature')->assertNotFound();
    }

    public function test_free_pedigrees_say_where_they_were_generated_instead_of_a_qr_code(): void
    {
        $this->withContact($this->free);
        $bird = $this->inbredBird($this->free);

        $this->actingAs($this->free)->get(route('pigeons.printPedigree', $bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.premium', false)
                ->where('meta.qr_svg', '')
                ->where('meta.share_url', '')
                // The loft name is free; contact details and the COI are Premium
                ->where('meta.breeder', 'Cruz Loft')
                ->where('meta.contact', ['phone' => null, 'email' => null, 'address' => null])
                ->where('meta.coi', null));
    }

    public function test_premium_pedigrees_have_the_qr_code_contact_details_and_coi(): void
    {
        $premium = User::factory()->premium()->create();
        $this->withContact($premium);
        $bird = $this->inbredBird($premium);

        $this->actingAs($premium)->get(route('pigeons.printPedigree', $bird))
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.premium', true)
                ->where('meta.qr_svg', fn ($svg) => str_contains($svg, '<svg'))
                ->where('meta.share_url', route('pedigrees.shared', $bird->pedigreeRecord()->serial))
                ->where('meta.contact.phone', '0917 123 4567')
                ->where('meta.coi', fn ($coi) => $coi !== null));
    }

    public function test_the_free_pedigree_page_keeps_lineage_but_not_the_pairing_analysis(): void
    {
        $bird = $this->inbredBird($this->free);

        $this->actingAs($this->free)->get(route('pigeons.pedigree', $bird))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pigeons/Pedigree')
                ->where('premium', false)
                ->where('analysis', null)
                ->where('ownerLinks.pairing_analysis', null)
                ->has('lineage.family'));
    }

    public function test_free_accounts_cant_share_pedigrees_online_but_can_make_them_private(): void
    {
        $bird = $this->bird($this->free, 'PH-1', 'male');

        $this->actingAs($this->free)->patch(route('pigeons.pedigreeVisibility', $bird), ['visibility' => 'public'])
            ->assertSessionHasErrors('visibility');
        $this->actingAs($this->free)->patch(route('pigeons.pedigreeVisibility', $bird), [
            'visibility' => 'password', 'password' => 'a-long-password', 'expires' => '7d',
        ])->assertSessionHasErrors('visibility');
        $this->assertFalse($bird->pedigreeRecord()->fresh()->is_public);

        $this->actingAs($this->free)->patch(route('pigeons.pedigreeVisibility', $bird), ['visibility' => 'private'])
            ->assertSessionHasNoErrors();
    }

    public function test_online_pedigrees_close_without_premium(): void
    {
        // Shared while Premium, then Premium ran out
        $owner = User::factory()->create(['premium_until' => now()->subDay()]);
        $bird = $this->bird($owner, 'PH-1', 'male');
        $serial = $bird->pedigreeRecord()->serial;
        $bird->pedigreeRecord()->update(['is_public' => true]);

        $this->get(route('pedigrees.shared', $serial))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('locked', 'private')->where('tree', null));

        $this->actingAs($owner)->get(route('pedigrees.shared', $serial))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('locked', 'premium')->where('pigeonId', $bird->id));

        // Renewing opens it again
        $owner->forceFill(['premium_until' => now()->addMonth()])->save();
        $this->post(route('logout'));
        $this->get(route('pedigrees.shared', $serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locked', null));
    }

    public function test_the_admin_seeder_creates_the_admin_and_resets_its_password(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', AdminSeeder::EMAIL)->sole();
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check(AdminSeeder::PASSWORD, $admin->password));

        $admin->forceFill(['password' => 'something-else', 'name' => 'Renamed'])->save();
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', AdminSeeder::EMAIL)->count());
        $this->assertTrue(Hash::check(AdminSeeder::PASSWORD, $admin->fresh()->password));
        $this->assertSame('Renamed', $admin->fresh()->name);
    }

    public function test_the_admin_seeder_refuses_the_placeholder_password_in_production(): void
    {
        if (AdminSeeder::PASSWORD !== 'change-this-password') {
            $this->markTestSkipped('The seeder password has been changed.');
        }

        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->app->make(AdminSeeder::class)->run();
    }
}
