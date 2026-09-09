<?php

namespace Vulpo\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Site;
use Symfony\Component\HttpFoundation\Response;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\RedirectRepository;

/**
 * Turns 404s into redirects when a matching rule exists, and logs the rest.
 *
 * Running after the response means normal page loads never pay for a redirect
 * lookup, and requests handled by Statamic itself keep priority over redirects.
 */
class HandleNotFound
{
    public function __construct(
        private readonly RedirectRepository $redirects,
        private readonly NotFoundLog $log,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! config('seo-and-geo.redirects.enabled', true)) {
            return $response;
        }

        $path = '/'.trim($request->decodedPath(), '/');
        $site = Site::current()->handle();

        if ($redirect = $this->redirects->resolve($path, $site, $request->getQueryString())) {
            if ($redirect['status'] === 410) {
                abort(410);
            }

            return redirect($this->target($redirect, $request), $redirect['status']);
        }

        $this->log->record($path, $request->headers->get('referer'), $site);

        return $response;
    }

    /**
     * Keep the original query string, unless the target brings its own or the
     * rule matched on the query string in the first place.
     *
     * @param  array{to: string, status: int, consumed_query: bool}  $redirect
     */
    private function target(array $redirect, Request $request): string
    {
        $to = $redirect['to'];
        $query = $request->getQueryString();

        if (! $query || $redirect['consumed_query'] || str_contains($to, '?')) {
            return $to;
        }

        return $to.'?'.$query;
    }
}
