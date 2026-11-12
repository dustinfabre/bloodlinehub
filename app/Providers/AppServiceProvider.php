<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Image requests (/media/...) by visitor IP: a pedigree page shows a few dozen images at most. Keyed by IP
        // because the media route has no session to find a user in.
        RateLimiter::for('media', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        // The "verify your email" message sent on sign-up (see User::sendEmailVerificationNotification)
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Verify your email for BloodlineHub')
            ->greeting('Hi '.Str::before((string) ($notifiable->name ?? ''), ' ').',')
            ->line('Thanks for signing up. Click the button below to confirm this is your email address.')
            ->action('Verify email address', $url)
            ->line('The link works for '.config('auth.verification.expire', 60).' minutes. If it has expired, sign in and ask for a new one.')
            ->line('If you didn\'t create a BloodlineHub account, you can ignore this email.'));
    }
}
