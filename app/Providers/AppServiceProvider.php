<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }
}
