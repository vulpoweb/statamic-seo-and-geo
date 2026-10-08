<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Vulpo\Seo\Http\Controllers\LlmsController;
use Vulpo\Seo\Http\Controllers\LlmsFullController;
use Vulpo\Seo\Http\Controllers\RobotsController;
use Vulpo\Seo\Http\Controllers\SitemapController;

if (config('seo-and-geo.sitemap.enabled', true)) {
    $sitemap = (string) config('seo-and-geo.sitemap.route', 'sitemap.xml');

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

if (config('seo-and-geo.robots.enabled', true)) {
    Route::get(config('seo-and-geo.robots.route', 'robots.txt'), RobotsController::class)
        ->name('vulpo-seo.robots');
}

if (config('seo-and-geo.llms.enabled', true)) {
    Route::get(config('seo-and-geo.llms.route', 'llms.txt'), LlmsController::class)
        ->name('vulpo-seo.llms');

    // The unabridged companion, for a catalogue too long to belong in a file
    // meant to be read whole.
    if (config('seo-and-geo.llms.full_enabled', true)) {
        Route::get(config('seo-and-geo.llms.full_route', 'llms-full.txt'), LlmsFullController::class)
            ->name('vulpo-seo.llms.full');
    }
}
