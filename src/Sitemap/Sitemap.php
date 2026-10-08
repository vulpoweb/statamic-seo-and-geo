<?php

namespace Vulpo\Seo\Sitemap;

use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Vulpo\Seo\Support\Assets;
use Vulpo\Seo\Support\ProviderResults;
use Vulpo\Seo\Support\Router;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

/**
 * Collects the URLs that belong in the XML sitemap.
 *
 * Everything published and routable is included, minus the collections and
 * taxonomies excluded in the control panel, minus pages marked "hide from
 * sitemap" or "noindex", minus entries that only redirect somewhere else.
 */
class Sitemap
{
    private const CACHE_KEY = 'vulpo-seo:sitemap';

    public function __construct(private readonly string $site) {}

    public static function forCurrentSite(): self
    {
        return new self(Site::current()->handle());
    }

    public static function flushCache(): void
    {
        $registry = app('vulpo-seo.providers.sitemap');

        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());

            // The working copies, not the last-good ones: saving a page must
            // not throw away what keeps the sitemap whole when an API is down.
            ProviderResults::forgetAll(self::CACHE_KEY, $site->handle(), $registry);
        }
    }

    /**
     * Drop one provider's slice, for a package invalidating its own data from
     * its own webhook.
     */
    public static function flushProvider(string $key): void
    {
        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());
            ProviderResults::forget(self::CACHE_KEY, $site->handle(), $key);
        }
    }

    /**
     * The URLs for one page of the sitemap. Page 1 is the whole thing unless
     * there are more than chunkSize() URLs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function page(int $page): array
    {
        return array_slice($this->urls(), ($page - 1) * $this->chunkSize(), $this->chunkSize());
    }

    /**
     * How many sitemap files the URLs need. More than one means /sitemap.xml
     * serves an index pointing at them, rather than dropping the overflow.
     */
    public function pages(): int
    {
        return max(1, (int) ceil(count($this->urls()) / $this->chunkSize()));
    }

    public function chunkSize(): int
    {
        // Google's own limit is 50.000 URLs or 50MB per file.
        return max(1, min((int) config('seo-and-geo.sitemap.max_urls', 5000), 50000));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function urls(): array
    {
        $minutes = (int) config('seo-and-geo.sitemap.cache_minutes', 60);

        if ($minutes < 1) {
            return $this->buildUrls();
        }

        if (is_array($cached = Cache::get(self::CACHE_KEY.':'.$this->site))) {
            return $cached;
        }

        $results = $this->providerResults();
        $urls = $this->buildUrls($results);

        // A build that fell back to a provider's last known answer is cached
        // for minutes rather than an hour, so the sitemap repairs itself
        // shortly after the source recovers.
        Cache::put(
            self::CACHE_KEY.':'.$this->site,
            $urls,
            now()->addMinutes($results->ttlMinutes($minutes)),
        );

        return $urls;
    }

    /**
     * URLs from packages that serve pages the addon cannot see: a product on a
     * Laravel route, a listing generated from an API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function providerUrls(ProviderResults $results): array
    {
        return array_values(array_map(
            fn (SitemapUrl $url) => $url->toArray(),
            array_filter(
                $results->collect(fn (object $provider, string $site) => $provider->sitemapUrls($site)),
                fn (mixed $url) => $url instanceof SitemapUrl,
            ),
        ));
    }

    private function providerResults(): ProviderResults
    {
        return new ProviderResults(
            app('vulpo-seo.providers.sitemap'),
            self::CACHE_KEY,
            'sitemap',
            $this->site,
        );
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string|null, priority: string|null}>
     */
    private function buildUrls(?ProviderResults $results = null): array
    {
        $urls = array_merge(
            $this->entryUrls(),
            $this->termUrls(),
            $this->providerUrls($results ?? $this->providerResults()),
        );

        $unique = [];

        foreach ($urls as $url) {
            $unique[$url['loc']] ??= $url;
        }

        $urls = array_values($unique);

        usort($urls, fn (array $a, array $b) => strcmp($a['loc'], $b['loc']));

        return $urls;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function entryUrls(): array
    {
        $excluded = Settings::list('sitemap_exclude_collections');
        $urls = [];

        foreach (CollectionFacade::all() as $collection) {
            if (in_array($collection->handle(), $excluded, true) || ! $collection->route($this->site)) {
                continue;
            }

            $entries = Entry::query()
                ->where('collection', $collection->handle())
                ->where('site', $this->site)
                ->where('published', true)
                ->get();

            foreach ($entries as $entry) {
                if ($url = $this->url($entry)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function termUrls(): array
    {
        if (! Settings::bool('sitemap_include_terms')) {
            return [];
        }

        $excluded = Settings::list('sitemap_exclude_taxonomies');
        $urls = [];

        foreach (Taxonomy::all() as $taxonomy) {
            if (in_array($taxonomy->handle(), $excluded, true)) {
                continue;
            }

            $terms = Term::query()->where('taxonomy', $taxonomy->handle())->get();

            foreach ($terms as $term) {
                $localized = $term->in($this->site);

                if ($localized && $url = $this->url($localized)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function url(Augmentable $data): ?array
    {
        if (! method_exists($data, 'absoluteUrl') || Router::isNotAPage($data) || ! $loc = $data->absoluteUrl()) {
            return null;
        }

        $values = ValueReader::fromData($data);

        if ($values->bool('sitemap_exclude') || $values->bool('noindex')) {
            return null;
        }

        return [
            'loc' => $loc,
            'lastmod' => method_exists($data, 'lastModified')
                ? $data->lastModified()?->toAtomString()
                : null,
            'changefreq' => $values->string('sitemap_changefreq')
                ?: Settings::string('sitemap_changefreq'),
            'priority' => $values->string('sitemap_priority')
                ?: Settings::string('sitemap_priority'),
            'alternates' => $this->alternates($data),
            'images' => $this->images($values),
        ];
    }

    /**
     * The page's own images, so Google Images has something to find.
     *
     * @return array<int, array{loc: string, title: string|null, caption: string|null}>
     */
    private function images(ValueReader $values): array
    {
        if (! Settings::bool('sitemap_images', true)) {
            return [];
        }

        $images = [];

        foreach ((array) config('seo-and-geo.sitemap.image_fields', ['seo_image']) as $field) {
            if ($url = Assets::url($values->handle((string) $field))) {
                $images[] = ['loc' => $url, 'title' => null, 'caption' => null];
            }
        }

        return $images;
    }

    /**
     * The same page in the site's other locales, which is what search engines
     * read to serve the right language. Only emitted when there is more than one
     * published localisation to point at.
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function alternates(Augmentable $data): array
    {
        if (Site::all()->count() < 2 || ! Settings::bool('hreflang', true) || ! method_exists($data, 'in')) {
            return [];
        }

        $alternates = [];

        foreach (Site::all() as $site) {
            $localized = $data->in($site->handle());

            if (! $localized || ! $localized->absoluteUrl()) {
                continue;
            }

            if (method_exists($localized, 'published') && ! $localized->published()) {
                continue;
            }

            $alternates[] = [
                'hreflang' => $site->shortLocale(),
                'href' => $localized->absoluteUrl(),
            ];
        }

        return count($alternates) > 1 ? $alternates : [];
    }
}
