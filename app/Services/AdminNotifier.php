<?php

namespace App\Services;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Emails the admins (config('mail.admin_notifications'), from ADMIN_NOTIFICATION_EMAIL) about something that needs
 * them: a payment to review, new feedback. Sent right after the response, so the user doesn't wait for the mail
 * server, and not queued. A mail failure is logged and never reaches the user: what they sent is already saved.
 */
class AdminNotifier
{
    public function notify(BaseNotification $notification): void
    {
        $addresses = config('mail.admin_notifications', []);

        if ($addresses === []) {
            return;
        }

        defer(function () use ($addresses, $notification) {
            try {
                Notification::route('mail', $addresses)->notifyNow($notification);
            } catch (Throwable $e) {
                Log::warning('Could not email the admins', [
                    'notification' => $notification::class,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}
