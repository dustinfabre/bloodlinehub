<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\Admin\FeedbackReceived;
use App\Notifications\Admin\PaymentSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admins are emailed (ADMIN_NOTIFICATION_EMAIL) when a payment receipt is submitted or feedback is sent.
 */
class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Deferred work (the email) runs straight away in tests
        $this->withoutDefer();
        Storage::fake('r2');
        Notification::fake();

        config(['mail.admin_notifications' => ['owner@bloodlinehub.test', 'staff@bloodlinehub.test']]);
        $this->me = User::factory()->create(['name' => 'Juan dela Cruz', 'email' => 'juan@example.test']);
    }

    private function submitPayment()
    {
        Plan::premium()->update(['price_php' => 1500, 'is_active' => true]);
        $account = PaymentAccount::create(['region' => 'local', 'bank_name' => 'GCash', 'account_name' => 'X', 'account_number' => '0917']);

        return $this->actingAs($this->me)->post(route('billing.payments.store'), [
            'plan_id' => Plan::premium()->id,
            'payment_account_id' => $account->id,
            'amount' => '1500',
            'reference_number' => 'REF-1',
            'paid_on' => now()->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
    }

    public function test_admins_are_emailed_about_a_new_payment(): void
    {
        $this->submitPayment()->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(PaymentSubmitted::class, function (PaymentSubmitted $notification, array $channels, AnonymousNotifiable $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === ['owner@bloodlinehub.test', 'staff@bloodlinehub.test']
                && $notification->payment->is(Payment::sole())
                && str_contains($mail->subject, 'PHP 1,500.00 from Juan dela Cruz')
                && str_contains((string) $mail->render(), 'REF-1')
                && $mail->replyTo === [['juan@example.test', 'Juan dela Cruz']];
        });
    }

    public function test_admins_are_emailed_about_new_feedback(): void
    {
        $this->actingAs($this->me)->post(route('feedback.store'), [
            'category' => 'bug',
            'message' => "The print cuts off.\nOnly on A4.",
            'page_url' => '/pigeons/1/print-pedigree',
        ])->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(FeedbackReceived::class, function (FeedbackReceived $notification, array $channels, AnonymousNotifiable $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === ['owner@bloodlinehub.test', 'staff@bloodlinehub.test']
                && str_starts_with($mail->subject, 'Bug report from Juan dela Cruz')
                && in_array('Only on A4.', $mail->introLines, true)
                && str_contains((string) $mail->render(), 'The print cuts off.')
                && $mail->replyTo === [['juan@example.test', 'Juan dela Cruz']];
        });
    }

    public function test_nothing_is_sent_without_an_address(): void
    {
        config(['mail.admin_notifications' => []]);

        $this->submitPayment()->assertSessionHasNoErrors();
        $this->actingAs($this->me)->post(route('feedback.store'), ['category' => 'idea', 'message' => 'More colors'])
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_mail_failure_is_logged_and_the_payment_is_still_saved(): void
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake
        {
            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('SMTP is down');
            }
        });
        Log::spy();

        $this->submitPayment()->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, Payment::count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Could not email the admins')->once();
    }
}
