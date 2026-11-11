<?php

namespace Tests\Feature;

use App\Models\InviteRequest;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PaymentReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The admin dashboard: payments review, bank accounts, plans, users and access.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $payer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('r2');
        Notification::fake();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->payer = User::factory()->create();
    }

    private function payment(array $attributes = []): Payment
    {
        return $this->payer->payments()->create([
            'payer_name' => $this->payer->name,
            'payer_email' => $this->payer->email,
            'plan_name' => 'Premium 1 year',
            'duration_months' => 12,
            'paid_to' => 'BDO · 0012',
            'currency' => 'PHP',
            'amount' => 1500,
            'paid_on' => now()->toDateString(),
            'receipt_path' => 'receipts/r.webp',
            'receipt_mime' => 'image/webp',
            ...$attributes,
        ]);
    }

    public function test_admin_pages_are_hidden_from_everyone_else(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        foreach (['admin.dashboard', 'admin.payments.index', 'admin.inbox.index', 'admin.users.index', 'admin.bank-accounts.index', 'admin.plans.index'] as $route) {
            $this->actingAs($this->payer)->get(route($route))->assertNotFound();
        }

        $payment = $this->payment();
        $this->actingAs($this->payer)->post(route('admin.payments.approve', $payment))->assertNotFound();
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);

        $this->actingAs($this->payer)->patch(route('admin.users.premium', $this->payer), ['premium_until' => now()->addYear()->toDateString()])
            ->assertNotFound();
        $this->assertNull($this->payer->fresh()->premium_until);
    }

    public function test_is_admin_cant_be_set_through_the_profile_form(): void
    {
        $this->actingAs($this->payer)->patch(route('profile.update'), [
            'name' => 'Sneaky', 'email' => $this->payer->email, 'is_admin' => true, 'premium_until' => '2099-01-01',
        ]);

        $this->assertFalse($this->payer->fresh()->is_admin);
        $this->assertNull($this->payer->fresh()->premium_until);
    }

    public function test_the_dashboard_counts_what_is_waiting(): void
    {
        $this->payment();
        $this->payer->feedback()->create(['category' => 'bug', 'message' => 'It broke']);
        InviteRequest::create(['email' => 'waiting@example.test']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('stats.users', 2)
                ->where('stats.pending_payments', 1)
                ->where('stats.unread_feedback', 1)
                ->where('waitlist', ['waiting@example.test'])
                ->where('adminCounts.pending_payments', 1)
                ->where('adminCounts.unread_feedback', 1));
    }

    public function test_admin_counts_are_not_shared_with_other_users(): void
    {
        $this->actingAs($this->payer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('adminCounts', null));
    }

    public function test_approving_starts_premium_today(): void
    {
        $this->travelTo(now()->startOfDay()->setDate(2026, 11, 11));
        $payment = $this->payment();

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(Payment::APPROVED, $payment->status);
        $this->assertSame($this->admin->id, $payment->reviewed_by);
        $this->assertSame('2027-11-11', $payment->premium_until->toDateString());
        $this->assertTrue($this->payer->fresh()->isPremium());
        $this->assertSame('2027-11-11', $this->payer->fresh()->premium_until->toDateString());

        Notification::assertSentTo($this->payer, PaymentReviewed::class);
    }

    public function test_approving_while_premium_extends_from_its_end(): void
    {
        $this->payer->forceFill(['premium_until' => now()->addMonths(2)])->save();
        $end = $this->payer->premium_until;

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $this->payment(['duration_months' => 1])));

        $this->assertSame($end->copy()->addMonthNoOverflow()->toDateString(), $this->payer->fresh()->premium_until->toDateString());
    }

    public function test_expired_premium_restarts_from_today(): void
    {
        $this->payer->forceFill(['premium_until' => now()->subMonths(3)])->save();

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $this->payment(['duration_months' => 1])));

        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $this->payer->fresh()->premium_until->toDateString());
    }

    public function test_a_payment_can_only_be_reviewed_once(): void
    {
        $payment = $this->payment();
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment));
        $until = $this->payer->fresh()->premium_until;

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))->assertSessionHasErrors('payment');
        $this->actingAs($this->admin)->post(route('admin.payments.reject', $payment), ['review_note' => 'x'])->assertSessionHasErrors('payment');

        $this->assertEquals($until, $this->payer->fresh()->premium_until);
        $this->assertSame(Payment::APPROVED, $payment->fresh()->status);
    }

    public function test_rejecting_needs_a_reason_and_tells_the_payer(): void
    {
        $payment = $this->payment();

        $this->actingAs($this->admin)->post(route('admin.payments.reject', $payment), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');

        $this->actingAs($this->admin)->post(route('admin.payments.reject', $payment), ['review_note' => 'No transfer with this reference arrived'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Payment::REJECTED, $payment->fresh()->status);
        $this->assertSame('No transfer with this reference arrived', $payment->fresh()->review_note);
        $this->assertFalse($this->payer->fresh()->isPremium());
        Notification::assertSentTo($this->payer, PaymentReviewed::class);
    }

    public function test_the_payments_list_filters_by_status_and_search(): void
    {
        $this->payment(['reference_number' => 'AAA-1']);
        $this->payment(['reference_number' => 'BBB-2'])->forceFill(['status' => Payment::APPROVED])->save();

        $this->actingAs($this->admin)->get(route('admin.payments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Payments')
                ->has('payments.data', 1)
                ->where('payments.data.0.reference_number', 'AAA-1')
                ->missing('payments.data.0.receipt_path'));

        $this->actingAs($this->admin)->get(route('admin.payments.index', ['status' => 'all', 'search' => 'bbb']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.reference_number', 'BBB-2'));
    }

    public function test_deleting_an_account_keeps_its_payments(): void
    {
        $payment = $this->payment();

        $this->payer->delete();

        $this->assertNull($payment->fresh()->user_id);
        $this->assertSame($this->payer->email, $payment->fresh()->payer_email);

        // Approving it now records the payment without granting anything
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))->assertSessionHasNoErrors();
        $this->assertSame(Payment::APPROVED, $payment->fresh()->status);
    }

    public function test_bank_accounts_can_be_added_with_a_qr_code_and_edited(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bank-accounts.store'), [
            'region' => 'international',
            'bank_name' => 'Community Federal Savings Bank',
            'account_name' => 'BloodlineHub',
            'account_number' => '8312345678',
            'account_type' => 'checking',
            'routing_number' => '026073150',
            'bank_address' => '89-16 Jamaica Ave, Woodhaven, NY 11421, United States',
            'is_active' => true,
            'qr' => UploadedFile::fake()->image('qr.png', 400, 400),
        ])->assertSessionHasNoErrors();

        $account = PaymentAccount::sole();
        $this->assertSame('USD', $account->currency);
        $this->assertSame('checking', $account->account_type);
        $this->assertSame('026073150', $account->routing_number);
        $this->assertEquals([
            'account_name' => 'BloodlineHub',
            'account_number' => '8312345678',
            'account_type' => 'checking',
            'routing_number' => '026073150',
            'bank_name' => 'Community Federal Savings Bank',
            'bank_address' => '89-16 Jamaica Ave, Woodhaven, NY 11421, United States',
        ], array_intersect_key($account->toPayerArray(), array_flip([
            'account_name', 'account_number', 'account_type', 'routing_number', 'bank_name', 'bank_address',
        ])));
        Storage::disk('r2')->assertExists($account->qr_path);
        $oldQr = $account->qr_path;

        $this->actingAs($this->admin)->post(route('admin.bank-accounts.update', $account), [
            'region' => 'local',
            'bank_name' => 'GCash',
            'account_name' => 'BloodlineHub',
            'account_number' => '0917 000 0000',
            // Left over from the international form; a local account doesn't keep them
            'account_type' => 'savings',
            'routing_number' => '026073150',
            'is_active' => false,
            'remove_qr' => true,
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('PHP', $account->currency);
        $this->assertNull($account->account_type);
        $this->assertNull($account->routing_number);
        $this->assertNull($account->bank_address);
        $this->assertSame('GCash', $account->bank_name);
        $this->assertFalse($account->is_active);
        $this->assertNull($account->qr_path);
        Storage::disk('r2')->assertMissing($oldQr);

        $this->actingAs($this->admin)->delete(route('admin.bank-accounts.destroy', $account));
        $this->assertSame(0, PaymentAccount::count());
    }

    public function test_paypal_is_an_international_account_with_an_email(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bank-accounts.store'), [
            'region' => 'international', 'method' => 'paypal', 'account_name' => 'BloodlineHub', 'account_number' => 'not-an-email',
        ])->assertSessionHasErrors('account_number');

        $this->actingAs($this->admin)->post(route('admin.bank-accounts.store'), [
            'region' => 'international',
            'method' => 'paypal',
            'account_name' => 'BloodlineHub',
            'account_number' => 'pay@bloodlinehub.test',
            // Bank details left over in the form are dropped
            'routing_number' => '026073150',
            'swift_code' => 'TRWIBEB1XXX',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $paypal = PaymentAccount::sole();
        $this->assertSame('paypal', $paypal->method);
        $this->assertSame('PayPal', $paypal->bank_name);
        $this->assertSame('international', $paypal->region);
        $this->assertSame('USD', $paypal->currency);
        $this->assertNull($paypal->routing_number);
        $this->assertNull($paypal->swift_code);
        $this->assertSame('PayPal · pay@bloodlinehub.test', $paypal->label());
        $this->assertSame('paypal', $paypal->toPayerArray()['method']);

        // Switching it back to a bank account needs a bank name
        $this->actingAs($this->admin)->post(route('admin.bank-accounts.update', $paypal), [
            'region' => 'international', 'method' => 'bank', 'account_name' => 'BloodlineHub', 'account_number' => '123',
        ])->assertSessionHasErrors('bank_name');
    }

    public function test_the_account_type_must_be_checking_or_savings(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bank-accounts.store'), [
            'region' => 'international', 'bank_name' => 'Bank', 'account_name' => 'X', 'account_number' => '1',
            'account_type' => 'brokerage',
        ])->assertSessionHasErrors('account_type');
    }

    public function test_premium_starts_off_sale_until_it_has_a_price(): void
    {
        $premium = Plan::premium();
        $this->assertNotNull($premium);
        $this->assertFalse($premium->isOnSale());
        $this->assertSame(14, $premium->trial_days);

        $this->actingAs($this->admin)->get(route('admin.plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Plans')
                ->where('premium.price_php', null)
                ->where('premium.on_sale', false)
                ->where('premium.trial_days', 14));

        $this->actingAs($this->payer)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('plans', []));
    }

    public function test_only_premium_prices_and_trial_can_be_changed(): void
    {
        $premium = Plan::premium();
        $payment = $this->payment(['plan_id' => $premium->id, 'plan_name' => 'Premium', 'duration_months' => 12, 'amount' => 1500]);

        $this->actingAs($this->admin)->patch(route('admin.plans.update'), [
            'price_php' => '',
            'duration_months' => 5,
            'trial_days' => -1,
        ])->assertSessionHasErrors(['price_php', 'duration_months', 'trial_days']);

        $this->actingAs($this->admin)->patch(route('admin.plans.update'), [
            'price_php' => 249, 'price_usd' => '', 'duration_months' => 1, 'trial_days' => 7,
            // Not editable here
            'name' => 'Gold',
        ])->assertSessionHasNoErrors();

        $premium->refresh();
        $this->assertSame('249.00', $premium->price_php);
        $this->assertNull($premium->price_usd);
        $this->assertSame(1, $premium->duration_months);
        $this->assertSame(7, $premium->trial_days);
        $this->assertSame('Premium', $premium->name);
        $this->assertTrue($premium->isOnSale());

        // Past payments keep what they paid for
        $this->assertSame(12, $payment->fresh()->duration_months);
        $this->assertSame('1500.00', $payment->fresh()->amount);

        // There is no adding or deleting plans
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.plans.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.plans.destroy'));
        $this->assertSame(1, Plan::count());
    }

    public function test_approving_during_the_trial_adds_the_paid_time_after_it(): void
    {
        $this->payer->forceFill(['trial_ends_at' => now()->addDays(10)])->save();
        $trialEnd = $this->payer->trial_ends_at;

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $this->payment(['duration_months' => 12])));

        $this->assertSame($trialEnd->copy()->addMonthsNoOverflow(12)->toDateString(), $this->payer->fresh()->premium_until->toDateString());
    }

    public function test_the_users_list_shows_trials(): void
    {
        $this->payer->forceFill(['trial_ends_at' => now()->addDays(5)])->save();

        $this->actingAs($this->admin)->get(route('admin.users.index', ['plan' => 'trial']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.on_trial', true)
                ->where('users.data.0.premium', false));

        $this->actingAs($this->admin)->get(route('admin.users.index', ['plan' => 'free']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $this->admin->id));

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.trial', 1));
    }

    public function test_premium_can_be_set_by_hand(): void
    {
        $date = now()->addMonths(6)->toDateString();

        $this->actingAs($this->admin)->patch(route('admin.users.premium', $this->payer), ['premium_until' => $date])
            ->assertSessionHasNoErrors();
        $this->assertSame($date, $this->payer->fresh()->premium_until->toDateString());
        $this->assertTrue($this->payer->fresh()->isPremium());

        $this->actingAs($this->admin)->patch(route('admin.users.premium', $this->payer), ['premium_until' => null]);
        $this->assertNull($this->payer->fresh()->premium_until);
    }

    public function test_the_users_list_searches_and_filters_by_plan(): void
    {
        $this->payer->forceFill(['premium_until' => now()->addMonth(), 'name' => 'Pedro Penduko'])->save();

        $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'pendu']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.premium', true));

        $this->actingAs($this->admin)->get(route('admin.users.index', ['plan' => 'free']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $this->admin->id));
    }

    public function test_admin_access_is_granted_from_the_command_line(): void
    {
        $this->artisan('admin:grant', ['email' => strtoupper($this->payer->email)])->assertSuccessful();
        $this->assertTrue($this->payer->fresh()->is_admin);

        $this->artisan('admin:grant', ['email' => $this->payer->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->payer->fresh()->is_admin);

        $this->artisan('admin:grant', ['email' => 'nobody@example.test'])->assertFailed();
    }
}
