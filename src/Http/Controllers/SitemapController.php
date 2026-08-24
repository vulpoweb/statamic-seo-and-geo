<?php

namespace Vulpo\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Support\PublicCache;
use Vulpo\Seo\Support\Settings;

class SitemapController
{
    /**
     * Serves the sitemap. Past the per-file limit, /sitemap.xml becomes an index
     * pointing at /sitemap-1.xml, /sitemap-2.xml and so on, rather than dropping
     * the URLs that do not fit.
     */
    public function __invoke(?int $page = null): Response
    {
        abort_unless(Settings::bool('sitemap_enabled', true), 404);

        $sitemap = Sitemap::forCurrentSite();
        $pages = $sitemap->pages();

        if ($page === null && $pages > 1) {
            return $this->xml('vulpo-seo::sitemap-index', ['pages' => $pages]);
        }

        $page ??= 1;

        abort_if($page < 1 || $page > $pages, 404);

        return $this->xml('vulpo-seo::sitemap', ['urls' => $sitemap->page($page)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function xml(string $view, array $data): Response
    {
        return PublicCache::apply(
            response()
                ->view($view, $data)
                ->header('Content-Type', 'application/xml; charset=UTF-8'),
            'sitemap',
        );
    }
}
