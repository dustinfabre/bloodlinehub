<?php

namespace App\Services;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Sends a mail notification right after the response, so the person who caused it doesn't wait for the mail
 * server, and without a queue. A failure is logged, never shown: what they did is already saved.
 */
class MailInBackground
{
    /**
     * @param  mixed  $notifiables  One or more users, or an on-demand route (Notification::route('mail', ...))
     */
    public function send(mixed $notifiables, BaseNotification $notification): void
    {
        defer(function () use ($notifiables, $notification) {
            try {
                Notification::sendNow($notifiables, $notification);
            } catch (Throwable $e) {
                Log::warning('Could not send an email', [
                    'notification' => $notification::class,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}
