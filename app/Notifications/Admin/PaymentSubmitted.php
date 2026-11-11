<?php

namespace App\Notifications\Admin;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the admins: a receipt was uploaded and the payment is waiting for review.
 */
class PaymentSubmitted extends Notification
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

        return (new MailMessage)
            ->subject("Payment to review: {$amount} from {$payment->payer_name}")
            ->greeting('A payment is waiting for review')
            ->line("**{$payment->payer_name}** ({$payment->payer_email}) sent {$amount} for {$payment->plan_name}.")
            ->line("Paid to: {$payment->paid_to}")
            ->line('Paid on: '.$payment->paid_on?->format('F j, Y'))
            ->lineIf(filled($payment->reference_number), "Reference: {$payment->reference_number}")
            ->lineIf(filled($payment->payer_note), "Note: {$payment->payer_note}")
            ->line('Check that the money arrived in that account, then approve or reject it.')
            ->action('Review payment', route('admin.payments.index'))
            ->replyTo($payment->payer_email, $payment->payer_name);
    }
}
