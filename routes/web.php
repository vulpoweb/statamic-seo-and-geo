<?php

use Illuminate\Support\Facades\Route;
use Vulpo\Seo\Http\Controllers\LlmsController;
use Vulpo\Seo\Http\Controllers\RobotsController;
use Vulpo\Seo\Http\Controllers\SitemapController;

if (config('vulpo-seo.sitemap.enabled', true)) {
    Route::get(config('vulpo-seo.sitemap.route', 'sitemap.xml'), SitemapController::class)
        ->name('vulpo-seo.sitemap');
}

if (config('vulpo-seo.robots.enabled', true)) {
    Route::get(config('vulpo-seo.robots.route', 'robots.txt'), RobotsController::class)
        ->name('vulpo-seo.robots');
}

if (config('vulpo-seo.llms.enabled', true)) {
    Route::get(config('vulpo-seo.llms.route', 'llms.txt'), LlmsController::class)
        ->name('vulpo-seo.llms');
}
