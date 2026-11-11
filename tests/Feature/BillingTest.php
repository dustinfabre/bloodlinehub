<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Plan & billing: paying for Premium by bank transfer and uploading the receipt. R2 is faked.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected Plan $plan;

    protected PaymentAccount $local;

    protected PaymentAccount $international;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('r2');

        $this->me = User::factory()->create(['name' => 'Juan dela Cruz', 'email' => 'juan@example.test']);
        // Premium (made by the migration) put on sale
        $this->plan = Plan::premium();
        $this->plan->update(['duration_months' => 12, 'price_php' => 1500, 'price_usd' => 30, 'is_active' => true]);
        $this->local = PaymentAccount::create([
            'region' => 'local', 'bank_name' => 'BDO', 'account_name' => 'BloodlineHub', 'account_number' => '0012 3456 7890',
        ]);
        $this->international = PaymentAccount::create([
            'region' => 'international', 'bank_name' => 'Wise', 'account_name' => 'BloodlineHub', 'account_number' => '9988776655',
            'swift_code' => 'TRWIBEB1XXX',
        ]);
    }

    private function submit(array $overrides = [])
    {
        return $this->actingAs($this->me)->post(route('billing.payments.store'), [
            'plan_id' => $this->plan->id,
            'payment_account_id' => $this->local->id,
            'amount' => '1500',
            'reference_number' => 'REF-123',
            'paid_on' => now()->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 1200),
            ...$overrides,
        ]);
    }

    public function test_currency_follows_the_account_region(): void
    {
        $this->assertSame('PHP', $this->local->currency);
        $this->assertSame('USD', $this->international->currency);

        $this->international->update(['region' => 'local']);
        $this->assertSame('PHP', $this->international->fresh()->currency);
    }

    public function test_the_billing_page_lists_active_plans_and_accounts_only(): void
    {
        Plan::create(['name' => 'Old plan', 'duration_months' => 1, 'price_php' => 100, 'is_active' => false]);
        PaymentAccount::create(['region' => 'local', 'bank_name' => 'Closed', 'account_name' => 'X', 'account_number' => '1', 'is_active' => false]);

        $this->actingAs($this->me)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/Index')
                ->where('premium.active', false)
                ->has('plans', 1)
                ->where('plans.0.name', 'Premium')
                ->has('accounts', 2)
                ->where('accounts.0.bank_name', 'BDO')
                ->where('accounts.0.has_qr', false)
                ->missing('accounts.0.qr_path'));
    }

    public function test_a_receipt_is_stored_privately_and_waits_for_review(): void
    {
        $this->submit()->assertSessionHasNoErrors()->assertRedirect();

        $payment = Payment::sole();
        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertSame($this->me->id, $payment->user_id);
        $this->assertSame('Juan dela Cruz', $payment->payer_name);
        $this->assertSame('juan@example.test', $payment->payer_email);
        $this->assertSame('Premium', $payment->plan_name);
        $this->assertSame(12, $payment->duration_months);
        $this->assertSame('BDO · 0012 3456 7890', $payment->paid_to);
        $this->assertSame('PHP', $payment->currency);
        $this->assertSame('image/webp', $payment->receipt_mime);
        Storage::disk('r2')->assertExists($payment->receipt_path);
        $this->assertStringStartsWith('receipts/', $payment->receipt_path);

        // Nothing is switched on until an admin approves it
        $this->assertFalse($this->me->fresh()->isPremium());
    }

    public function test_a_paypal_payment_records_the_paypal_account(): void
    {
        $paypal = PaymentAccount::create([
            'region' => 'international', 'method' => 'paypal', 'account_name' => 'BloodlineHub', 'account_number' => 'pay@bloodlinehub.test',
        ]);

        $this->submit(['payment_account_id' => $paypal->id, 'amount' => '30', 'reference_number' => '8AB12345CD678901E'])
            ->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('PayPal · pay@bloodlinehub.test', $payment->paid_to);
        $this->assertSame('USD', $payment->currency);
    }

    public function test_a_pdf_receipt_is_kept_as_a_pdf(): void
    {
        $this->submit(['receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n%fake\n")])
            ->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('application/pdf', $payment->receipt_mime);
        $this->assertStringEndsWith('.pdf', $payment->receipt_path);
    }

    public function test_other_file_types_are_refused(): void
    {
        $this->submit(['receipt' => UploadedFile::fake()->createWithContent('receipt.html', '<script>alert(1)</script>')])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_broken_image_is_a_form_error_not_a_crash(): void
    {
        $this->submit(['receipt' => UploadedFile::fake()->createWithContent('receipt.jpg', "\xFF\xD8\xFF\xE0 not really a jpeg")])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_plan_without_a_dollar_price_cant_be_paid_abroad(): void
    {
        $this->plan->update(['price_usd' => null]);

        $this->submit(['payment_account_id' => $this->international->id])->assertSessionHasErrors('payment_account_id');
    }

    public function test_inactive_plans_and_accounts_are_refused(): void
    {
        $this->local->update(['is_active' => false]);
        $this->submit()->assertSessionHasErrors('payment_account_id');

        $this->plan->update(['is_active' => false]);
        $this->submit(['payment_account_id' => $this->international->id])->assertSessionHasErrors('plan_id');
    }

    public function test_future_payment_dates_are_refused(): void
    {
        $this->submit(['paid_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_on');
    }

    public function test_a_user_can_have_only_a_few_payments_waiting(): void
    {
        foreach (range(1, 3) as $i) {
            $this->submit()->assertSessionHasNoErrors();
        }

        $this->submit()->assertSessionHasErrors('receipt');
        $this->assertSame(3, Payment::count());
    }

    public function test_the_payer_and_admins_can_open_a_receipt_but_no_one_else(): void
    {
        $this->submit();
        $payment = Payment::sole();
        $url = route('billing.payments.receipt', $payment);

        $this->actingAs($this->me)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get($url)->assertOk();

        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    }

    public function test_pdf_receipts_are_downloaded_not_opened(): void
    {
        $this->submit(['receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n%fake\n")]);

        $response = $this->actingAs($this->me)->get(route('billing.payments.receipt', Payment::sole()));

        $response->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_qr_codes_are_shown_for_active_accounts(): void
    {
        Storage::disk('r2')->put('payment-qr/qr.webp', 'qr');
        $this->local->forceFill(['qr_path' => 'payment-qr/qr.webp'])->save();

        $this->actingAs($this->me)->get(route('billing.accounts.qr', $this->local))->assertOk();

        $this->local->update(['is_active' => false]);
        $this->actingAs($this->me)->get(route('billing.accounts.qr', $this->local))->assertNotFound();
        $this->actingAs($this->me)->get(route('billing.accounts.qr', $this->international))->assertNotFound();
    }

    public function test_the_billing_page_shows_the_users_own_payments_and_rejection_reasons(): void
    {
        $this->submit();
        Payment::sole()->forceFill(['status' => Payment::REJECTED, 'review_note' => 'No transfer arrived'])->save();

        $other = User::factory()->create();
        $other->payments()->create(Payment::sole()->only([
            'payer_name', 'payer_email', 'plan_name', 'duration_months', 'paid_to', 'currency', 'amount', 'paid_on',
            'receipt_path', 'receipt_mime',
        ]));

        $this->actingAs($this->me)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('payments', 1)
                ->where('payments.0.status', 'rejected')
                ->where('payments.0.review_note', 'No transfer arrived'));
    }

    public function test_guests_cant_open_billing(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    }
}
