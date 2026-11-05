<?php

namespace Tests\Feature;

use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Request an invite" at /register while registration is closed.
 */
class InviteRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function requestInvite(array $data)
    {
        return $this->from(route('register'))->post(route('invite-requests.store'), $data);
    }

    public function test_guests_see_the_request_invite_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
    }

    public function test_logged_in_users_are_redirected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('register'))->assertRedirect();
        $this->actingAs($user)->post(route('invite-requests.store'), ['email' => 'someone@example.test'])->assertRedirect();

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_a_valid_email_is_stored_normalized(): void
    {
        $this->requestInvite(['email' => '  Juan.Dela.Cruz@Example.TEST '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('register'));

        $this->assertSame('juan.dela.cruz@example.test', InviteRequest::sole()->email);
    }

    public function test_a_duplicate_is_not_stored_twice_and_responds_the_same(): void
    {
        $first = $this->requestInvite(['email' => 'juan@example.test']);
        $second = $this->requestInvite(['email' => 'JUAN@example.test']);

        $second->assertSessionHasNoErrors()->assertRedirect(route('register'));
        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, InviteRequest::count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->requestInvite(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->requestInvite([])->assertSessionHasErrors('email');

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_the_honeypot_stores_nothing_but_looks_like_a_success(): void
    {
        $this->requestInvite(['email' => 'bot@example.test', 'website' => 'https://spam.example.test'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('register'));

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_requests_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestInvite(['email' => "person{$attempt}@example.test"])->assertRedirect(route('register'));
        }

        $this->requestInvite(['email' => 'one-too-many@example.test'])->assertTooManyRequests();

        $this->assertSame(5, InviteRequest::count());
    }

    public function test_the_list_command_shows_stored_emails(): void
    {
        InviteRequest::create(['email' => 'juan@example.test']);

        $this->artisan('invites:list')
            ->expectsOutputToContain('juan@example.test')
            ->expectsOutputToContain('1 invite request.')
            ->assertSuccessful();

        $this->artisan('invites:list', ['--since' => now()->addDay()->toDateString()])
            ->doesntExpectOutputToContain('juan@example.test')
            ->assertSuccessful();

        $this->artisan('invites:list', ['--since' => 'yesterday'])->assertFailed();
    }
}
