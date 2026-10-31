<?php

namespace Tests\Feature;

use App\Models\Pedigree;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeSharingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pigeon $pigeon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'ring_number' => 'BE-2024-555555',
        ]);
    }

    private function share(array $data)
    {
        return $this->actingAs($this->user)->patch(route('pigeons.pedigreeVisibility', $this->pigeon), $data);
    }

    private function pedigree(): Pedigree
    {
        return $this->pigeon->fresh()->pedigree;
    }

    private function asGuest(): void
    {
        auth()->logout();
    }

    public function test_owner_can_protect_a_pedigree_with_a_permanent_password(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pedigree = $this->pedigree();
        $this->assertFalse($pedigree->is_public);
        $this->assertTrue(Hash::check('pigeon1', $pedigree->password));
        $this->assertNull($pedigree->password_expires_at);
        $this->assertSame('password', $pedigree->visibility());

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $this->pigeon))
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.visibility', 'password')
                ->where('sharing.visibility', 'password')
                ->where('sharing.has_password', true)
                ->where('sharing.password_expires_at', null)
                ->missing('sharing.password')
            );
    }

    public function test_visitors_need_the_password(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never']);
        $serial = $this->pedigree()->serial;
        $this->asGuest();

        $this->get(route('pedigrees.shared', $serial))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locked', 'password')
                ->where('tree', null)
            );

        $this->from(route('pedigrees.shared', $serial))
            ->post(route('pedigrees.unlock', $serial), ['password' => 'wrong'])
            ->assertRedirect(route('pedigrees.shared', $serial))
            ->assertSessionHasErrors('password');

        $this->post(route('pedigrees.unlock', strtolower($serial)), ['password' => 'pigeon1'])
            ->assertRedirect(route('pedigrees.shared', $serial));

        $this->get(route('pedigrees.shared', $serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locked', null)
                ->where('isOwner', false)
                ->where('tree.ring_number', 'BE-2024-555555')
                ->where('tree.id', null)
                ->where('meta.visibility', 'password')
                ->where('accessExpiresAt', null)
            );
    }

    public function test_a_timed_password_stops_working_when_it_expires(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => '7d'])
            ->assertSessionHasNoErrors();
        $pedigree = $this->pedigree();
        $this->assertTrue($pedigree->password_expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        $this->asGuest();
        $this->post(route('pedigrees.unlock', $pedigree->serial), ['password' => 'pigeon1']);
        $this->get(route('pedigrees.shared', $pedigree->serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accessExpiresAt', $pedigree->password_expires_at->toIso8601String())
            );

        $this->travel(8)->days();

        // Earlier access ends and the password no longer unlocks it; it reads as private again
        $this->get(route('pedigrees.shared', $pedigree->serial))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('locked', 'private'));
        $this->post(route('pedigrees.unlock', $pedigree->serial), ['password' => 'pigeon1'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $this->pigeon))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sharing.visibility', 'private')
                ->where('sharing.password_expired', true)
            );
    }

    public function test_password_can_last_until_a_chosen_date(): void
    {
        $until = now()->addDays(3)->endOfDay();

        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'custom', 'expires_at' => $until->toIso8601String()])
            ->assertSessionHasNoErrors();
        $this->assertSame($until->timestamp, $this->pedigree()->password_expires_at->timestamp);

        $this->share(['visibility' => 'password', 'expires' => 'custom', 'expires_at' => now()->subDay()->toIso8601String()])
            ->assertSessionHasErrors('expires_at');
        $this->share(['visibility' => 'password', 'expires' => 'custom'])
            ->assertSessionHasErrors('expires_at');
    }

    public function test_password_is_required_only_when_none_is_set(): void
    {
        $this->share(['visibility' => 'password', 'expires' => 'never'])->assertSessionHasErrors('password');
        $this->share(['visibility' => 'password', 'password' => 'abc', 'expires' => 'never'])->assertSessionHasErrors('password');
        $this->share(['visibility' => 'password', 'password' => 'pigeon1'])->assertSessionHasErrors('expires');

        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never'])->assertSessionHasNoErrors();

        // Blank keeps the current password, e.g. when only extending it
        $this->share(['visibility' => 'password', 'password' => '', 'expires' => '30d'])->assertSessionHasNoErrors();
        $pedigree = $this->pedigree();
        $this->assertTrue(Hash::check('pigeon1', $pedigree->password));
        $this->assertNotNull($pedigree->password_expires_at);
    }

    public function test_changing_the_password_ends_earlier_access(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never']);
        $serial = $this->pedigree()->serial;

        $this->asGuest();
        $this->post(route('pedigrees.unlock', $serial), ['password' => 'pigeon1']);
        $this->get(route('pedigrees.shared', $serial))->assertOk();

        $this->share(['visibility' => 'password', 'password' => 'newpass', 'expires' => 'never']);

        $this->asGuest();
        $this->get(route('pedigrees.shared', $serial))->assertForbidden();
    }

    public function test_switching_to_public_or_private_removes_the_password(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => '7d']);

        $this->share(['visibility' => 'public'])->assertSessionHasNoErrors();
        $pedigree = $this->pedigree();
        $this->assertTrue($pedigree->is_public);
        $this->assertNull($pedigree->password);
        $this->assertNull($pedigree->password_expires_at);

        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never']);
        $this->share(['visibility' => 'private'])->assertSessionHasNoErrors();
        $pedigree = $this->pedigree();
        $this->assertFalse($pedigree->is_public);
        $this->assertNull($pedigree->password);
        $this->assertSame('private', $pedigree->visibility());

        $this->share(['visibility' => 'everyone'])->assertSessionHasErrors('visibility');
    }

    public function test_password_attempts_are_rate_limited(): void
    {
        $this->share(['visibility' => 'password', 'password' => 'pigeon1', 'expires' => 'never']);
        $serial = $this->pedigree()->serial;
        $this->asGuest();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('pedigrees.unlock', $serial), ['password' => 'guess'.$attempt])
                ->assertSessionHasErrors('password');
        }

        $this->post(route('pedigrees.unlock', $serial), ['password' => 'pigeon1'])->assertTooManyRequests();
    }
}
