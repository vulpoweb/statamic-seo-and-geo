<?php

namespace Vulpo\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vulpo\Seo\Facades\Seo;

/**
 * Keeps a route out of the index:
 *
 *     Route::get('/cart', …)->middleware('seo.noindex');
 *
 * Deliberately not a robots.txt Disallow. A crawler has to be allowed to fetch
 * a URL in order to read the instruction not to index it; disallowing instead
 * leaves the address in the index forever, with no title and no description.
 */
class Noindex
{
    public function handle(Request $request, Closure $next, string $nofollow = ''): Response
    {
        Seo::noindex(nofollow: $nofollow === 'nofollow');

        return $next($request);
    }
}
