<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The free trial (full Premium for a number of days an admin sets) and the public pricing.
 */
class TrialAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signUp(string $email = 'juan@example.test')
    {
        return $this->post(route('register.store'), [
            'name' => 'Juan dela Cruz',
            'email' => $email,
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
            'terms' => true,
        ]);
    }

    public function test_new_accounts_start_the_trial_for_the_days_set(): void
    {
        Plan::premium()->update(['trial_days' => 10]);

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('trialDays', 10));
        $this->signUp();

        $user = User::sole();
        // Signed in, but the app opens only once the email is verified
        $user->markEmailAsVerified();
        $this->actingAs($user);
        $this->assertTrue($user->isOnTrial());
        $this->assertFalse($user->isPremium());
        $this->assertTrue($user->hasPremium());
        $this->assertSame(now()->addDays(10)->toDateString(), $user->trial_ends_at->toDateString());

        // The trial is full Premium
        $this->get(route('loft-checks.index'))->assertOk();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.premium', true));
    }

    public function test_no_trial_when_it_is_set_to_zero_days(): void
    {
        Plan::premium()->update(['trial_days' => 0]);

        $this->signUp();

        $this->assertNull(User::sole()->trial_ends_at);
        $user = User::sole();
        $user->markEmailAsVerified();
        $this->actingAs($user)->get(route('loft-checks.index'))->assertForbidden();
    }

    public function test_the_trial_ends(): void
    {
        $user = User::factory()->create(['trial_ends_at' => now()->subMinute()]);

        $this->assertFalse($user->hasPremium());
        $this->actingAs($user)->get(route('loft-checks.index'))->assertForbidden();
    }

    public function test_older_accounts_can_start_the_trial_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('trial.available', true)->where('trial.days', 14));

        $this->actingAs($user)->post(route('billing.trial.store'))->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isOnTrial());

        // Once only, even after it ends
        $user->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->actingAs($user)->post(route('billing.trial.store'))->assertSessionHasErrors('trial');
        $this->assertFalse($user->fresh()->isOnTrial());

        $this->actingAs($user)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('trial.available', false)->where('trial.active', false));
    }

    public function test_the_trial_cant_be_started_when_trials_are_off(): void
    {
        Plan::premium()->update(['trial_days' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.trial.store'))->assertSessionHasErrors('trial');
        $this->assertNull($user->fresh()->trial_ends_at);
    }

    public function test_pricing_is_public_and_says_coming_soon_until_a_price_is_set(): void
    {
        $this->get(route('pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pricing')
                ->where('pricing.on_sale', false)
                ->where('pricing.price_php', null)
                ->where('pricing.trial_days', 14));

        Plan::premium()->update(['price_php' => 1499, 'price_usd' => 29.99, 'duration_months' => 12, 'is_active' => true]);
        PaymentAccount::create(['region' => 'local', 'bank_name' => 'GCash', 'account_name' => 'X', 'account_number' => '0917']);
        PaymentAccount::create(['region' => 'local', 'bank_name' => 'Hidden', 'account_name' => 'X', 'account_number' => '1', 'is_active' => false]);
        PaymentAccount::create(['region' => 'international', 'method' => 'paypal', 'account_name' => 'X', 'account_number' => 'pay@x.test']);

        $this->get(route('pricing'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pricing.on_sale', true)
                ->where('pricing.price_php', '1499.00')
                ->where('pricing.price_usd', '29.99')
                ->where('pricing.duration_months', 12)
                ->where('paymentMethods', ['GCash', 'PayPal']));

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('pricing.price_php', '1499.00'));
    }
}
