<?php

namespace Vulpo\Seo\Contracts;

use Vulpo\Seo\Sitemap\SitemapUrl;

/**
 * Contributes URLs the addon cannot find on its own.
 *
 * The addon enumerates entries and terms. Anything else a site serves -- a
 * product on a Laravel route, a page generated from an API -- has to say so,
 * and this is where.
 */
interface SitemapProvider
{
    /**
     * @return iterable<int, SitemapUrl>
     */
    public function sitemapUrls(string $site): iterable;
}
