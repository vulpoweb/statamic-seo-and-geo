<?php

use Illuminate\Support\Facades\DB;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Redirects\UriLedger;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Support\YamlFile;

/**
 * A site without statamic/eloquent-driver, or with it left on the file driver,
 * must keep everything in flat files and touch no database at all.
 */
beforeEach(function () {
    config()->set('seo.storage.driver', 'auto');

    foreach (['entries', 'taxonomies', 'globals', 'addon_settings'] as $repository) {
        config()->set("statamic.eloquent-driver.{$repository}.driver", 'file');
    }

    app(StorageManager::class)->flush();

    foreach ([RedirectRepository::class, NotFoundLog::class, CrawlerLog::class, UriLedger::class] as $class) {
        app()->forgetInstance($class);
    }
});

it('writes redirects to the project YAML file', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));

    $path = base_path((string) config('seo.redirects.path'));

    expect($path)->toBeFile();
    expect(file_get_contents($path))->toContain('from: /old');
});

it('writes the 404 log to storage', function () {
    app(NotFoundLog::class)->record('/missing', 'https://example.com');

    $path = storage_path('app/'.config('seo.redirects.not_found_log_path'));

    expect($path)->toBeFile();
    expect(file_get_contents($path))->toContain('/missing');
});

it('writes the crawler log to storage', function () {
    app(CrawlerLog::class)->record('Claude', '/');

    expect(storage_path('app/'.config('seo.ai_crawlers.log_path')))->toBeFile();
});

it('writes the URL index to storage', function () {
    app(UriLedger::class)->remember('123', 'default', '/about');

    $path = storage_path('app/'.config('seo.redirects.uri_ledger_path'));

    expect($path)->toBeFile();
    expect(app(UriLedger::class)->get('123', 'default'))->toBe('/about');
});

it('never queries its tables on a flat file site', function () {
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));
    app(NotFoundLog::class)->record('/missing');
    app(CrawlerLog::class)->record('Claude', '/');
    app(UriLedger::class)->remember('123', 'default', '/about');

    expect(app(RedirectRepository::class)->resolve('/old'))->toBe(['to' => '/new', 'status' => 301, 'consumed_query' => false]);
    expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'vulpo_seo')))->toBeEmpty();
});

it('loads no migrations on a flat file site', function () {
    // Nothing to migrate means a flat-file project's migration list stays clean.
    $paths = app('migrator')->paths();

    expect(collect($paths)->filter(fn ($path) => str_contains($path, 'vulpo-seo')))->toBeEmpty();
})->skip(
    fn () => env('VULPO_SEO_STORAGE_DRIVER') === 'eloquent',
    'The suite is being run against database storage, where the test harness loads them on purpose.',
);

it('still reads a URL index written in the old map format', function () {
    YamlFile::inStorage((string) config('seo.redirects.uri_ledger_path'))->write([
        'default::abc' => '/about',
    ]);

    app(UriLedger::class)->flush();

    // An upgraded site keeps its automatic redirects instead of silently
    // starting over with an empty index.
    expect(app(UriLedger::class)->get('abc', 'default'))->toBe('/about');
    expect(app(UriLedger::class)->isEmpty())->toBeFalse();
});
