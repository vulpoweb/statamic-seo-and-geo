<?php

namespace Vulpo\Seo\Robots;

use Statamic\Facades\Site;
use Vulpo\Seo\Support\Settings;

/**
 * Builds a robots.txt from the control panel settings, including an AI crawler
 * policy and a link to the sitemap.
 */
class RobotsTxt
{
    public function render(): string
    {
        $lines = ['User-agent: *'];

        foreach ($this->disallowedPaths() as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        if (Settings::bool('noindex_site')) {
            $lines = ['User-agent: *', 'Disallow: /'];
        }

        $lines = array_merge($lines, $this->aiCrawlerLines());

        if ($extra = Settings::string('robots_extra')) {
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $extra) ?: []);
        }

        if (config('seo.sitemap.enabled', true) && Settings::bool('sitemap_enabled', true)) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->sitemapUrl();
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<int, string>
     */
    private function disallowedPaths(): array
    {
        $paths = Settings::string('robots_disallow');

        $paths = $paths
            ? array_filter(array_map('trim', preg_split('/\R/', $paths) ?: []))
            : ['/'.trim((string) config('statamic.cp.route', 'cp'), '/').'/'];

        return array_values($paths);
    }

    /**
     * @return array<int, string>
     */
    private function aiCrawlerLines(): array
    {
        $policy = Settings::string('ai_crawler_policy', 'allow');

        if ($policy === 'allow' || Settings::bool('noindex_site')) {
            return [];
        }

        /** @var array<string, string> $agents */
        $agents = config('seo.ai_crawlers.agents', []);

        $blocked = $policy === 'block'
            ? array_values($agents)
            : Settings::list('blocked_ai_crawlers');

        $lines = [];

        foreach (array_unique($blocked) as $agent) {
            $lines[] = '';
            $lines[] = 'User-agent: '.$agent;
            $lines[] = 'Disallow: /';
        }

        return $lines;
    }

    private function sitemapUrl(): string
    {
        $route = trim((string) config('seo.sitemap.route', 'sitemap.xml'), '/');

        return rtrim(Site::current()->absoluteUrl(), '/').'/'.$route;
    }
}
