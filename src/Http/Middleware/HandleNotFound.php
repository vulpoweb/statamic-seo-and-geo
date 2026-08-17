<?php

namespace Vulpo\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if ($response->getStatusCode() !== 404 || ! config('vulpo-seo.redirects.enabled', true)) {
            return $response;
        }

        $path = '/'.trim($request->decodedPath(), '/');

        if ($redirect = $this->redirects->resolve($path)) {
            if ($redirect['status'] === 410) {
                abort(410);
            }

            return redirect($this->target($redirect['to'], $request), $redirect['status']);
        }

        $this->log->record($path, $request->headers->get('referer'));

        return $response;
    }

    /**
     * Keep the original query string unless the target brings its own.
     */
    private function target(string $to, Request $request): string
    {
        $query = $request->getQueryString();

        if (! $query || str_contains($to, '?')) {
            return $to;
        }

        return $to.'?'.$query;
    }
}
