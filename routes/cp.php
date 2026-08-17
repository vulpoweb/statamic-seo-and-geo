<?php

use Illuminate\Support\Facades\Route;
use Vulpo\Seo\Http\Controllers\CP\AiCrawlersController;
use Vulpo\Seo\Http\Controllers\CP\NotFoundLogController;
use Vulpo\Seo\Http\Controllers\CP\RedirectsController;

Route::middleware(['statamic.cp.authenticated', 'can:view vulpo seo'])
    ->prefix('vulpo-seo')
    ->name('vulpo-seo.')
    ->group(function () {
        Route::get('redirects', [RedirectsController::class, 'index'])->name('redirects.index');
        Route::post('redirects', [RedirectsController::class, 'update'])->name('redirects.update');
        Route::post('redirects/create', [RedirectsController::class, 'store'])->name('redirects.store');

        Route::get('404-log', [NotFoundLogController::class, 'index'])->name('not-found.index');
        Route::post('404-log/forget', [NotFoundLogController::class, 'destroy'])->name('not-found.destroy');
        Route::post('404-log/clear', [NotFoundLogController::class, 'clear'])->name('not-found.clear');

        Route::get('ai-crawlers', [AiCrawlersController::class, 'index'])->name('ai-crawlers.index');
        Route::post('ai-crawlers/clear', [AiCrawlersController::class, 'clear'])->name('ai-crawlers.clear');
    });
