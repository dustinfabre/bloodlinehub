<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the payer their bank transfer was approved (Premium is on) or rejected (and why).
 */
class PaymentReviewed extends Notification
{
    public function __construct(public Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;
        $amount = $payment->currency.' '.number_format((float) $payment->amount, 2);

        if ($payment->status === Payment::APPROVED) {
            return (new MailMessage)
                ->subject('Your BloodlineHub Premium is active')
                ->greeting('Thank you!')
                ->line("We received your payment of {$amount} for {$payment->plan_name}.")
                ->line('Premium is active until '.$payment->premium_until?->format('F j, Y').'.')
                ->action('Open BloodlineHub', route('dashboard'));
        }

        return (new MailMessage)
            ->subject('We couldn\'t confirm your BloodlineHub payment')
            ->line("We couldn't confirm your payment of {$amount} for {$payment->plan_name}.")
            ->lineIf(filled($payment->review_note), 'Reason: '.$payment->review_note)
            ->line('If you did send the money, reply with a clearer receipt or the reference number and we\'ll check again.')
            ->action('Plan & billing', route('billing.index'));
    }
}
