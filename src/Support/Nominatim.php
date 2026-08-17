<?php

namespace Vulpo\Seo\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Free, keyless geocoding via OpenStreetMap Nominatim.
 *
 * Results are cached so an editor never has to type coordinates and the site
 * stays within Nominatim's usage policy: one lookup per unique address.
 */
class Nominatim
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function coordinates(string $address): ?array
    {
        $address = trim($address);

        if ($address === '' || ! config('vulpo-seo.geocoder.enabled', true)) {
            return null;
        }

        $key = 'vulpo-seo:coords:'.md5($address);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $coordinates = self::lookup($address);

        $days = $coordinates
            ? (int) config('vulpo-seo.geocoder.cache_days', 30)
            : (int) config('vulpo-seo.geocoder.cache_days_failed', 1);

        Cache::put($key, $coordinates, now()->addDays($days));

        return $coordinates;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function lookup(string $address): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::userAgent()])
                ->timeout((int) config('vulpo-seo.geocoder.timeout', 5))
                ->get((string) config('vulpo-seo.geocoder.endpoint'), [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $hit = $response->json()[0] ?? null;

            if (! isset($hit['lat'], $hit['lon'])) {
                return null;
            }

            return [
                'lat' => round((float) $hit['lat'], 6),
                'lng' => round((float) $hit['lon'], 6),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nominatim's terms of use require a descriptive User-Agent.
     */
    private static function userAgent(): string
    {
        return config('vulpo-seo.geocoder.user_agent')
            ?: config('app.name').' Statamic site (vulpo/seo)';
    }
}
