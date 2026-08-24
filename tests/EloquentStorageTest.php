<?php

use Illuminate\Support\Facades\Schema;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Redirects\UriLedger;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Support\YamlFile;

/**
 * The same behaviour as the flat-file tests, but with everything in a database.
 * Anything that only works against YAML shows up here.
 */
beforeEach(function () {
    config()->set('seo.storage.driver', 'eloquent');

    app(StorageManager::class)->flush();
    app()->forgetInstance(RedirectRepository::class);
    app()->forgetInstance(NotFoundLog::class);
    app()->forgetInstance(CrawlerLog::class);
    app()->forgetInstance(UriLedger::class);

    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    $this->artisan('migrate')->run();
});

it('creates the tables', function () {
    expect(Schema::hasTable('vulpo_seo_redirects'))->toBeTrue();
    expect(Schema::hasTable('vulpo_seo_not_found'))->toBeTrue();
    expect(Schema::hasTable('vulpo_seo_ai_crawlers'))->toBeTrue();
    expect(Schema::hasTable('vulpo_seo_uris'))->toBeTrue();
});

it('keeps redirects in the database', function () {
    $repository = app(RedirectRepository::class);

    $repository->add(new Redirect(from: '/old', to: '/new'));
    $repository->flush();

    expect(app('db')->table('vulpo_seo_redirects')->count())->toBe(1);
    expect(app('db')->table('vulpo_seo_redirects')->first()->from_path)->toBe('/old');
    expect($repository->resolve('/old'))->toBe(['to' => '/new', 'status' => 301, 'consumed_query' => false]);

    $repository->remove('/old');

    expect(app('db')->table('vulpo_seo_redirects')->count())->toBe(0);
});

it('round-trips every redirect column', function () {
    $repository = app(RedirectRepository::class);

    $repository->save([
        ['from' => '/blog/*', 'to' => '/news/*', 'status' => 302, 'match' => 'wildcard', 'active' => false, 'source' => 'auto'],
    ]);
    $repository->flush();

    $redirect = $repository->all()->first();

    expect($redirect->from)->toBe('/blog/*');
    expect($redirect->to)->toBe('/news/*');
    expect($redirect->status)->toBe(302);
    expect($redirect->match)->toBe('wildcard');
    expect($redirect->active)->toBeFalse();
    expect($redirect->source)->toBe('auto');
});

it('counts 404s in the database', function () {
    $log = app(NotFoundLog::class);

    $log->record('/missing', 'https://example.com');
    $log->record('/missing');
    $log->record('/other');

    expect(app('db')->table('vulpo_seo_not_found')->count())->toBe(2);

    $row = $log->all()->firstWhere('path', '/missing');

    expect($row['hits'])->toBe(2);
    // A later hit without a referer must not wipe the one already recorded.
    expect($row['referer'])->toBe('https://example.com');

    $log->forget('/missing');

    expect($log->all())->toHaveCount(1);

    $log->clear();

    expect(app('db')->table('vulpo_seo_not_found')->count())->toBe(0);
});

it('caps the 404 log', function () {
    config()->set('seo.redirects.not_found_log_max', 3);

    $log = app(NotFoundLog::class);

    foreach (range(1, 6) as $i) {
        $log->record('/missing-'.$i);
    }

    expect(app('db')->table('vulpo_seo_not_found')->count())->toBe(3);
});

it('counts AI crawler visits in the database', function () {
    $log = app(CrawlerLog::class);

    $log->record('Claude', '/');
    $log->record('Claude', '/about');
    $log->record('Perplexity', '/');

    expect(app('db')->table('vulpo_seo_ai_crawlers')->count())->toBe(2);
    expect($log->totals())->toBe(['Claude' => 2, 'Perplexity' => 1]);
    expect($log->all()->firstWhere('bot', 'Claude')['last_path'])->toBe('/about');
});

it('prunes crawler rows past the retention window', function () {
    config()->set('seo.ai_crawlers.retention_days', 7);

    app('db')->table('vulpo_seo_ai_crawlers')->insert([
        'date' => now()->subDays(30)->toDateString(),
        'bot' => 'Old bot',
        'hits' => 3,
        'last_seen' => now()->subDays(30)->toDateTimeString(),
    ]);

    app(CrawlerLog::class)->record('Claude', '/');

    expect(app(CrawlerLog::class)->all()->pluck('bot'))->not->toContain('Old bot');
});

it('keeps the URL index in the database', function () {
    $ledger = app(UriLedger::class);

    $ledger->remember('123', 'default', '/about');

    expect(app('db')->table('vulpo_seo_uris')->count())->toBe(1);
    expect($ledger->get('123', 'default'))->toBe('/about');

    $ledger->remember('123', 'default', '/about-us');
    $ledger->flush();

    expect(app('db')->table('vulpo_seo_uris')->count())->toBe(1);
    expect($ledger->get('123', 'default'))->toBe('/about-us');

    $ledger->remember('123', 'default', null);
    $ledger->flush();

    expect($ledger->get('123', 'default'))->toBeNull();
    expect(app('db')->table('vulpo_seo_uris')->count())->toBe(0);
});

it('writes nothing to the flat files', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));
    app(NotFoundLog::class)->record('/missing');

    expect(base_path((string) config('seo.redirects.path')))->not->toBeFile();
    expect(storage_path('app/'.config('seo.redirects.not_found_log_path')))->not->toBeFile();
});

it('imports flat file data into the database', function () {
    // Write the flat files the way a file-driver site would have left them.
    config()->set('seo.storage.driver', 'file');
    app(StorageManager::class)->flush();
    app()->forgetInstance(RedirectRepository::class);
    app()->forgetInstance(NotFoundLog::class);

    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));
    app(NotFoundLog::class)->record('/missing', 'https://example.com');

    // Then switch over and import.
    config()->set('seo.storage.driver', 'eloquent');
    app(StorageManager::class)->flush();
    app()->forgetInstance(RedirectRepository::class);
    app()->forgetInstance(NotFoundLog::class);

    $this->artisan('vulpo:seo:import-to-database')->assertSuccessful();

    expect(app('db')->table('vulpo_seo_redirects')->first()->from_path)->toBe('/old');
    expect(app('db')->table('vulpo_seo_not_found')->first()->referer)->toBe('https://example.com');
});

it('imports without duplicating on a second run', function () {
    config()->set('seo.storage.driver', 'file');
    app(StorageManager::class)->flush();
    app()->forgetInstance(RedirectRepository::class);
    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));

    config()->set('seo.storage.driver', 'eloquent');
    app(StorageManager::class)->flush();
    app()->forgetInstance(RedirectRepository::class);

    $this->artisan('vulpo:seo:import-to-database')->assertSuccessful();
    $this->artisan('vulpo:seo:import-to-database')->assertSuccessful();

    expect(app('db')->table('vulpo_seo_redirects')->count())->toBe(1);
});

it('refuses to import while the site is on flat files', function () {
    config()->set('seo.storage.driver', 'file');
    app(StorageManager::class)->flush();

    $this->artisan('vulpo:seo:import-to-database')->assertFailed();
});

it('imports the URL index written in the old map format', function () {
    // Before the storage layer, the index was `site::id: /uri` rather than rows.
    YamlFile::inStorage((string) config('seo.redirects.uri_ledger_path'))->write([
        'default::abc' => '/about',
        'default::def' => '/contact',
    ]);

    $this->artisan('vulpo:seo:import-to-database')->assertSuccessful();

    expect(app('db')->table('vulpo_seo_uris')->count())->toBe(2);
    expect(app(UriLedger::class)->get('abc', 'default'))->toBe('/about');
});

it('exports database rows back to flat files', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/old', to: '/new'));
    app(NotFoundLog::class)->record('/missing');

    $this->artisan('vulpo:seo:export-to-files')->assertSuccessful();

    expect(base_path((string) config('seo.redirects.path')))->toBeFile();
    expect(file_get_contents(base_path((string) config('seo.redirects.path'))))->toContain('/old');
    expect(file_get_contents(storage_path('app/'.config('seo.redirects.not_found_log_path'))))->toContain('/missing');
});

it('refuses to export while the site is on flat files', function () {
    config()->set('seo.storage.driver', 'file');
    app(StorageManager::class)->flush();

    $this->artisan('vulpo:seo:export-to-files')->assertFailed();
});
