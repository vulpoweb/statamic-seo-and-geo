<?php

use Illuminate\Support\Facades\Route;
use Vulpo\Seo\Http\Controllers\CP\AiCrawlersController;
use Vulpo\Seo\Http\Controllers\CP\NotFoundLogController;
use Vulpo\Seo\Http\Controllers\CP\RedirectsController;

Route::middleware(['statamic.cp.authenticated', 'can:view vulpo seo'])
    ->prefix('vulpo-seo')
    ->name('vulpo-seo.')
    ->group(function () {
        // Reading the screens.
        Route::get('redirects', [RedirectsController::class, 'index'])->name('redirects.index');
        Route::get('404-log', [NotFoundLogController::class, 'index'])->name('not-found.index');
        Route::get('ai-crawlers', [AiCrawlersController::class, 'index'])->name('ai-crawlers.index');

        // Changing anything, which a read-only role should not be able to do.
        Route::middleware('can:edit vulpo seo')->group(function () {
            Route::post('redirects', [RedirectsController::class, 'update'])->name('redirects.update');
            Route::post('redirects/create', [RedirectsController::class, 'store'])->name('redirects.store');

            Route::post('404-log/forget', [NotFoundLogController::class, 'destroy'])->name('not-found.destroy');
            Route::post('404-log/clear', [NotFoundLogController::class, 'clear'])->name('not-found.clear');

            Route::post('ai-crawlers/clear', [AiCrawlersController::class, 'clear'])->name('ai-crawlers.clear');
        });
    });
