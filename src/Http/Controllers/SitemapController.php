<?php

namespace Vulpo\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Support\Settings;

class SitemapController
{
    public function __invoke(): Response
    {
        abort_unless(Settings::bool('sitemap_enabled', true), 404);

        return response()
            ->view('vulpo-seo::sitemap', ['urls' => Sitemap::forCurrentSite()->urls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
