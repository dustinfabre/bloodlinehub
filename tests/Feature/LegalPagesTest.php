<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Privacy Policy and Terms are public pages at /privacy and /terms (the privacy URL is also given to Google for
 * the Drive OAuth consent screen, so it must stay reachable without logging in).
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_privacy_policy_is_public(): void
    {
        $this->assertSame(url('/privacy'), route('privacy'));

        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/Privacy'));
    }

    public function test_terms_are_public(): void
    {
        $this->assertSame(url('/terms'), route('terms'));

        $this->get('/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/Terms'));
    }

    public function test_signed_in_users_can_open_both_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/privacy')->assertOk();
        $this->actingAs($user)->get('/terms')->assertOk();
    }
}
