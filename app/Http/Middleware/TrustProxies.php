<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Behind a load balancer or CDN, set TRUSTED_PROXIES (the proxy addresses, comma-separated, or "*" when the app is
 * only reachable through the proxy) so visitor IPs, and the per-visitor rate limits, use the real client address.
 * Left unset, forwarded headers are ignored, so they can't be used to fake an address.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $configured = trim((string) config('app.trusted_proxies'));

        if ($configured === '') {
            return parent::proxies();
        }

        return $configured === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
