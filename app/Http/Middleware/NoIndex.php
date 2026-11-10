<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a page out of search engines, images included. Shared pedigrees use it so that whoever finds a lost bird
 * can't look up its pedigree by searching for the ring number.
 *
 * Deliberately not blocked in robots.txt as well: a crawler that may not fetch a page never sees this header, and
 * the bare URL can then still turn up in results when another site links to it.
 */
class NoIndex
{
    public const DIRECTIVES = 'noindex, nofollow, noarchive, noimageindex';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', self::DIRECTIVES);

        return $response;
    }
}
