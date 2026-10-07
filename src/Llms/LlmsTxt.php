<?php

namespace Vulpo\Seo\Llms;

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Vulpo\Seo\Contracts\LlmsFullProvider;
use Vulpo\Seo\Support\ProviderResults;
use Vulpo\Seo\Support\Router;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

/**
 * Builds an llms.txt: a plain markdown summary of the site for AI assistants,
 * following the llmstxt.org convention of a title, a short summary and a list of
 * links with one-line descriptions.
 */
class LlmsTxt
{
    private const CACHE_KEY = 'vulpo-seo:llms';

    public function __construct(private readonly string $site) {}

    public static function forCurrentSite(): self
    {
        return new self(Site::current()->handle());
    }

    public static function flushCache(): void
    {
        $registry = app('vulpo-seo.providers.llms');

        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());
            Cache::forget(self::CACHE_KEY.':full:'.$site->handle());

            // Not the last-good copies: those are what keep the file whole
            // while a source is unreachable.
            ProviderResults::forgetAll(self::CACHE_KEY, $site->handle(), $registry);
            ProviderResults::forgetAll(self::CACHE_KEY.':full', $site->handle(), $registry);
        }
    }

    public static function flushProvider(string $key): void
    {
        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());
            Cache::forget(self::CACHE_KEY.':full:'.$site->handle());
            ProviderResults::forget(self::CACHE_KEY, $site->handle(), $key);
            ProviderResults::forget(self::CACHE_KEY.':full', $site->handle(), $key);
        }
    }

    public function render(): string
    {
        return $this->cached(
            self::CACHE_KEY.':'.$this->site,
            fn (ProviderResults $r) => $this->build($r, full: false),
            full: false,
        );
    }

    /**
     * The unabridged companion.
     *
     * llms.txt is meant to be read whole, so it stays a short curated map. A
     * catalogue of thousands of products belongs here instead, where nothing is
     * competing with it for a reader's attention or for the 200-link budget.
     */
    public function renderFull(): string
    {
        return $this->cached(
            self::CACHE_KEY.':full:'.$this->site,
            fn (ProviderResults $r) => $this->build($r, full: true),
            full: true,
        );
    }

    /**
     * @param  \Closure(ProviderResults): string  $build
     */
    private function cached(string $key, \Closure $build, bool $full): string
    {
        $minutes = (int) config('seo-and-geo.llms.cache_minutes', 60);

        if ($minutes < 1) {
            return $build($this->providerResults($full));
        }

        if (is_string($cached = Cache::get($key))) {
            return $cached;
        }

        $results = $this->providerResults($full);
        $rendered = $build($results);

        Cache::put($key, $rendered, now()->addMinutes($results->ttlMinutes($minutes)));

        return $rendered;
    }

    /**
     * The two files ask a provider different questions, so they cannot share a
     * cache entry -- llms.txt would otherwise answer llms-full.txt with its
     * own short list, and nobody would be any the wiser.
     */
    private function providerResults(bool $full): ProviderResults
    {
        return new ProviderResults(
            app('vulpo-seo.providers.llms'),
            self::CACHE_KEY.($full ? ':full' : ''),
            'llms',
            $this->site,
        );
    }

    private function build(ProviderResults $results, bool $full): string
    {
        $name = Settings::string('business_name')
            ?: Settings::string('site_name')
            ?: (string) Site::current()->name();

        $lines = ['# '.$name];

        if ($summary = Settings::string('llms_summary') ?: Settings::string('business_description')) {
            $lines[] = '';
            $lines[] = '> '.$this->oneLine($summary);
        }

        if ($intro = Settings::string('llms_intro')) {
            $lines[] = '';
            $lines[] = trim($intro);
        }

        if ($expertise = Settings::list('knows_about')) {
            $lines[] = '';
            $lines[] = '## Expertise';
            $lines[] = '';

            foreach ($expertise as $topic) {
                $lines[] = '- '.$this->oneLine((string) $topic);
            }
        }

        $groups = $this->pagesByCollection();

        foreach ($this->providerGroups($results, $full, array_sum(array_map('count', $groups))) as $heading => $links) {
            $groups[$heading] = array_merge($groups[$heading] ?? [], $links);
        }

        foreach ($groups as $title => $pages) {
            $lines[] = '';
            $lines[] = '## '.$title;
            $lines[] = '';

            foreach ($pages as $page) {
                $lines[] = $page['description']
                    ? '- ['.$page['title'].']('.$page['url'].'): '.$page['description']
                    : '- ['.$page['title'].']('.$page['url'].')';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Links contributed by other packages, grouped under their own headings.
     *
     * Entries fill the budget first, so adding a product catalogue can never
     * push the shop's own pages out of its llms.txt.
     *
     * @return array<string, array<int, array{title: string, url: string, description: string|null}>>
     */
    private function providerGroups(ProviderResults $results, bool $full, int $alreadyListed): array
    {
        $budget = ($full
            ? (int) config('seo-and-geo.llms.full_max_urls', 20000)
            : (int) config('seo-and-geo.llms.max_urls', 200)) - $alreadyListed;

        if ($budget < 1) {
            return [];
        }

        $perGroup = $full ? PHP_INT_MAX : max(1, (int) config('seo-and-geo.llms.max_per_group', 50));
        $default = Settings::string('llms_default_group') ?: 'Pages';

        $links = $results->collect(fn (object $provider, string $site) => $full && $provider instanceof LlmsFullProvider
            ? $provider->llmsFullLinks($site)
            : $provider->llmsLinks($site));

        $grouped = [];

        foreach ($links as $link) {
            if (! $link instanceof LlmsLink || $budget < 1) {
                continue;
            }

            $heading = $this->oneLine($link->group ?? $default);

            if (count($grouped[$heading] ?? []) >= $perGroup) {
                continue;
            }

            $grouped[$heading][] = [
                'title' => $this->oneLine($link->title),
                'url' => $link->url,
                'description' => $link->description === null ? null : $this->oneLine($link->description),
            ];

            $budget--;
        }

        return $grouped;
    }

    /**
     * @return array<string, array<int, array{title: string, url: string, description: string|null}>>
     */
    private function pagesByCollection(): array
    {
        $excluded = Settings::list('llms_exclude_collections') ?: Settings::list('sitemap_exclude_collections');
        $limit = (int) config('seo-and-geo.llms.max_urls', 200);
        $grouped = [];
        $count = 0;

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
                if ($count >= $limit) {
                    break 2;
                }

                $values = ValueReader::fromData($entry);

                if ($values->bool('noindex') || $values->bool('sitemap_exclude') || Router::isNotAPage($entry)) {
                    continue;
                }

                if (! $url = $entry->absoluteUrl()) {
                    continue;
                }

                $grouped[$collection->title()][] = [
                    'title' => $this->oneLine((string) ($values->string('title') ?: $entry->value('title'))),
                    'url' => $url,
                    'description' => ($description = $values->string('description')) ? $this->oneLine($description) : null,
                ];

                $count++;
            }
        }

        return $grouped;
    }

    private function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }
}
