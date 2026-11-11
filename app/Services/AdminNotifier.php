<?php

namespace App\Services;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the admins (config('mail.admin_notifications'), from ADMIN_NOTIFICATION_EMAIL) about something that needs
 * them: a payment to review, new feedback. Sent in the background (MailInBackground); nothing is sent when no
 * address is set.
 */
class AdminNotifier
{
    public function __construct(private MailInBackground $mail) {}

    public function notify(BaseNotification $notification): void
    {
        $addresses = config('mail.admin_notifications', []);

        if ($addresses !== []) {
            $this->mail->send(Notification::route('mail', $addresses), $notification);
        }
    }
}
