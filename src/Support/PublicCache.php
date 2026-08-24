<?php

namespace Vulpo\Seo\Support;

use Illuminate\Http\Response;

/**
 * Cache headers for the addon's public text routes.
 *
 * These files change rarely and are read by crawlers and CDNs, so telling them
 * how long the answer stays good saves a render on every hit. `stale-while-
 * revalidate` lets a CDN keep serving the old copy while it fetches a new one,
 * so nobody waits for the rebuild.
 */
class PublicCache
{
    public static function apply(Response $response, string $configKey): Response
    {
        $minutes = (int) config("seo.{$configKey}.cache_minutes", 60);

        if ($minutes < 1) {
            return $response->header('Cache-Control', 'no-store');
        }

        $seconds = $minutes * 60;

        return $response->header(
            'Cache-Control',
            "public, max-age={$seconds}, stale-while-revalidate={$seconds}",
        );
    }
}
