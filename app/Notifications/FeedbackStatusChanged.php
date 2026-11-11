<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells someone who sent feedback (or said "me too" to it) what happened to it: fixed, planned, done, answered...
 * Sent only when an admin ticks "Email the sender" while changing the status.
 */
class FeedbackStatusChanged extends Notification
{
    /**
     * [subject, first line] per status
     */
    private const TEXT = [
        'in_progress' => ['We\'re working on the problem you reported', 'Thanks for reporting this. We\'re working on it now.'],
        'fixed' => ['Fixed: the problem you reported', 'Thanks for reporting this. It\'s fixed now.'],
        'cant_reproduce' => ['About the problem you reported', 'Thanks for reporting this. We tried, but couldn\'t make it happen on our side. If it still happens, reply with the steps you took or a screenshot and we\'ll look again.'],
        'planned' => ['Your idea is planned', 'Thanks for the idea. We\'ve put it on our plan.'],
        'done' => ['Done: what you asked for', 'Thanks for asking. It\'s now in BloodlineHub.'],
        'not_planned' => ['About your idea', 'Thanks for the idea. We\'ve decided not to build it for now, but we keep every request and may come back to it.'],
        'answered' => ['An answer to your question', 'Thanks for your question.'],
    ];

    /**
     * @param  Feedback  $feedback  The message whose status changed (for "me too" senders, the original)
     * @param  string|null  $note  An optional message from the admin, added to the email
     */
    public function __construct(public Feedback $feedback, public ?string $note = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function canNotify(string $status): bool
    {
        return array_key_exists($status, self::TEXT);
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $intro] = self::TEXT[$this->feedback->status] ?? ['About your feedback', 'Thanks for your feedback.'];

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hi '.Str::before((string) ($notifiable->name ?? ''), ' ').',')
            ->line($intro);

        if (filled($this->note)) {
            foreach (preg_split('/\R/', trim($this->note)) as $line) {
                $mail->line($line === '' ? ' ' : $line);
            }
        }

        $mail->line('You wrote: "'.Str::limit(Str::squish($this->feedback->message), 200).'"')
            ->action('Open BloodlineHub', route('dashboard'))
            ->salutation('Thank you for helping us improve BloodlineHub.');

        // Replies go to the admins rather than the no-reply sender
        $replyTo = config('mail.admin_notifications', [])[0] ?? null;

        return $replyTo ? $mail->replyTo($replyTo) : $mail;
    }
}
