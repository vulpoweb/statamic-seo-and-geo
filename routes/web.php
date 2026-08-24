<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Vulpo\Seo\Http\Controllers\LlmsController;
use Vulpo\Seo\Http\Controllers\RobotsController;
use Vulpo\Seo\Http\Controllers\SitemapController;

if (config('seo.sitemap.enabled', true)) {
    $sitemap = (string) config('seo.sitemap.route', 'sitemap.xml');

    Route::get($sitemap, SitemapController::class)
        ->name('vulpo-seo.sitemap');

    // Past the per-file limit the sitemap becomes an index pointing at
    // /sitemap-1.xml, /sitemap-2.xml and so on.
    Route::get(
        Str::beforeLast($sitemap, '.').'-{page}.'.Str::afterLast($sitemap, '.'),
        SitemapController::class,
    )
        ->whereNumber('page')
        ->name('vulpo-seo.sitemap.page');
}

if (config('seo.robots.enabled', true)) {
    Route::get(config('seo.robots.route', 'robots.txt'), RobotsController::class)
        ->name('vulpo-seo.robots');
}

if (config('seo.llms.enabled', true)) {
    Route::get(config('seo.llms.route', 'llms.txt'), LlmsController::class)
        ->name('vulpo-seo.llms');
}
