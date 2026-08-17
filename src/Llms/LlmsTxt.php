<?php

namespace Vulpo\Seo\Llms;

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
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
        foreach (Site::all() as $site) {
            Cache::forget(self::CACHE_KEY.':'.$site->handle());
        }
    }

    public function render(): string
    {
        $minutes = (int) config('seo.llms.cache_minutes', 60);

        if ($minutes < 1) {
            return $this->build();
        }

        return Cache::remember(self::CACHE_KEY.':'.$this->site, now()->addMinutes($minutes), fn () => $this->build());
    }

    private function build(): string
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

        foreach ($this->pagesByCollection() as $title => $pages) {
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
     * @return array<string, array<int, array{title: string, url: string, description: string|null}>>
     */
    private function pagesByCollection(): array
    {
        $excluded = Settings::list('llms_exclude_collections') ?: Settings::list('sitemap_exclude_collections');
        $limit = (int) config('seo.llms.max_urls', 200);
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

                if ($values->bool('noindex') || $values->bool('sitemap_exclude') || ! $url = $entry->absoluteUrl()) {
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
