<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Sign-up at /register (Fortify's own registration stays off; see RegisteredUserController).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signUp(array $overrides = [])
    {
        return $this->post(route('register.store'), [
            'name' => 'Juan dela Cruz',
            'email' => 'juan@example.test',
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
            'terms' => true,
            ...$overrides,
        ]);
    }

    public function test_guests_see_the_sign_up_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
    }

    public function test_logged_in_users_are_redirected(): void
    {
        $this->actingAs(User::factory()->create())->get(route('register'))->assertRedirect();
    }

    public function test_a_new_account_is_created_and_signed_in(): void
    {
        $this->signUp(['email' => '  Juan@Example.TEST '])->assertRedirect(route('dashboard'));

        $user = User::sole();
        $this->assertSame('juan@example.test', $user->email);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->isPremium());
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_new_account_can_log_in_with_any_casing(): void
    {
        $this->signUp(['email' => 'Juan@Example.test']);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'JUAN@example.test', 'password' => 'a-long-password-123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_the_terms_must_be_accepted(): void
    {
        $this->signUp(['terms' => false])->assertSessionHasErrors('terms');

        $this->assertSame(0, User::count());
    }

    public function test_an_email_already_used_in_another_case_is_refused(): void
    {
        User::factory()->create(['email' => 'juan@example.test']);

        $this->signUp(['email' => 'JUAN@example.test'])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $this->signUp(['password_confirmation' => 'something-else-123'])->assertSessionHasErrors('password');
    }

    public function test_bots_filling_the_honeypot_create_nothing(): void
    {
        $this->signUp(['website' => 'https://spam.example'])->assertRedirect(route('home'));

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_sign_ups_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('register.store'), ['email' => "bad{$i}"]);
        }

        $this->post(route('register.store'), ['email' => 'bad6'])->assertStatus(429);
    }
}
