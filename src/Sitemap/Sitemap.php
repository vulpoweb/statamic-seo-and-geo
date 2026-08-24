<?php

namespace Vulpo\Seo\Sitemap;

use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
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
        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());
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
        return max(1, min((int) config('seo.sitemap.max_urls', 5000), 50000));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function urls(): array
    {
        $minutes = (int) config('seo.sitemap.cache_minutes', 60);

        if ($minutes < 1) {
            return $this->buildUrls();
        }

        return Cache::remember(
            self::CACHE_KEY.':'.$this->site,
            now()->addMinutes($minutes),
            fn () => $this->buildUrls(),
        );
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string|null, priority: string|null}>
     */
    private function buildUrls(): array
    {
        $urls = array_merge($this->entryUrls(), $this->termUrls());

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
        ];
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
