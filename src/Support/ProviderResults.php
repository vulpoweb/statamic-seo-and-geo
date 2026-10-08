<?php

namespace Vulpo\Seo\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Collects what the registered providers have to say, and keeps saying it when
 * they stop answering.
 *
 * A sitemap that shrinks is worse than one that is stale. The missing URLs do
 * not read as "the API is down", they read as "these pages are gone", and a
 * plain Cache::remember would pin that answer for the next hour. So every
 * provider's last successful result is kept well past its normal cache, and a
 * build that had to fall back is itself only cached for a few minutes -- the
 * sitemap then repairs itself shortly after the source comes back, rather than
 * at the end of the hour.
 */
class ProviderResults
{
    private bool $degraded = false;

    /**
     * @param  string  $cacheKey  e.g. vulpo-seo:sitemap
     * @param  string  $configKey  e.g. sitemap
     */
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly string $cacheKey,
        private readonly string $configKey,
        private readonly string $site,
    ) {}

    /**
     * @param  Closure(object|Closure, string): iterable<mixed>  $call
     * @return array<int, mixed>
     */
    public function collect(Closure $call): array
    {
        $results = [];

        foreach (array_keys($this->registry->all()) as $key) {
            foreach ($this->fromProvider($key, $call) as $item) {
                $results[] = $item;
            }
        }

        return $results;
    }

    /**
     * True when at least one provider fell back to its last known answer, which
     * is what shortens the cache on the combined result.
     */
    public function degraded(): bool
    {
        return $this->degraded;
    }

    public function ttlMinutes(int $healthy): int
    {
        return $this->degraded
            ? max(1, (int) config("seo-and-geo.{$this->configKey}.retry_minutes", 5))
            : $healthy;
    }

    /**
     * Forget one provider's slice, so a package can invalidate its own data
     * from its own webhook without flushing everyone else's.
     */
    public static function forget(string $cacheKey, string $site, string $providerKey): void
    {
        Cache::forget("{$cacheKey}:{$site}:p:{$providerKey}");
    }

    /**
     * Forget every provider's working copy for a site. Deliberately leaves the
     * last-good copies alone: a content save must not throw away the thing that
     * keeps the sitemap whole when the API is down.
     */
    public static function forgetAll(string $cacheKey, string $site, ProviderRegistry $registry): void
    {
        foreach (array_keys($registry->all()) as $key) {
            self::forget($cacheKey, $site, $key);
        }
    }

    /**
     * @param  Closure(object|Closure, string): iterable<mixed>  $call
     * @return array<int, mixed>
     */
    private function fromProvider(string $key, Closure $call): array
    {
        $cacheKey = "{$this->cacheKey}:{$this->site}:p:{$key}";
        $lastGood = "{$cacheKey}:last-good";
        $minutes = (int) config("seo-and-geo.{$this->configKey}.cache_minutes", 60);

        if ($minutes > 0 && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        try {
            $provider = $this->registry->resolve($key);

            // A closure provider is called directly: it has no interface to
            // dispatch on, which is the whole point of allowing one.
            $items = $this->take($provider instanceof Closure
                ? $provider($this->site)
                : $call($provider, $this->site));
        } catch (Throwable $e) {
            report($e);
            $this->degraded = true;

            $fallback = Cache::get($lastGood);

            return is_array($fallback) ? $fallback : [];
        }

        if ($minutes > 0) {
            Cache::put($cacheKey, $items, now()->addMinutes($minutes));
        }

        Cache::put($lastGood, $items, now()->addHours(
            (int) config("seo-and-geo.{$this->configKey}.provider_fallback_hours", 24),
        ));

        return $items;
    }

    /**
     * Consume the generator, up to a ceiling. A source that never stops
     * yielding should not be able to exhaust memory mid-request.
     *
     * @param  iterable<mixed>  $items
     * @return array<int, mixed>
     */
    private function take(iterable $items): array
    {
        $max = (int) config("seo-and-geo.{$this->configKey}.max_provider_urls", 50000);
        $collected = [];

        foreach ($items as $item) {
            $collected[] = $item;

            if ($max > 0 && count($collected) >= $max) {
                report(new \RuntimeException(
                    "A vulpo/seo-and-geo provider yielded more than {$max} items and was cut short."
                ));

                break;
            }
        }

        return $collected;
    }
}
