<?php

namespace App\Notifications\Admin;

use App\Models\Feedback;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * To the admins: someone sent feedback. Replying to the email answers the sender.
 */
class FeedbackReceived extends Notification
{
    private const CATEGORIES = [
        'bug' => 'Bug report',
        'idea' => 'Idea',
        'question' => 'Question',
        'other' => 'Feedback',
    ];

    public function __construct(public Feedback $feedback) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $feedback = $this->feedback;
        $user = $feedback->user;
        $kind = self::CATEGORIES[$feedback->category] ?? 'Feedback';

        $mail = (new MailMessage)
            ->subject("{$kind} from {$user->name}: ".Str::limit(Str::squish($feedback->message), 60))
            ->greeting("{$kind} from {$user->name}")
            ->line("From: {$user->name} ({$user->email})");

        // The message as written, line by line (MailMessage escapes each line)
        foreach (preg_split('/\R/', trim($feedback->message)) as $line) {
            $mail->line($line === '' ? ' ' : $line);
        }

        return $mail
            ->lineIf(filled($feedback->page_url), 'Sent from: '.url((string) $feedback->page_url))
            ->lineIf($feedback->screenshot_path !== null, 'A screenshot is attached in the inbox.')
            ->action('Open the inbox', route('admin.inbox.index'))
            ->replyTo($user->email, $user->name);
    }
}
