<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Statamic\Yaml\ParseException;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Widgets\AiCrawlersWidget;

/**
 * The YAML logs are written on every crawler visit and every 404. Requests
 * racing each other used to leave them unparseable, which then took down the
 * dashboard and every later crawler request with them.
 */
uses(PreventsSavingStacheItemsToDisk::class);

beforeEach(function () {
    config()->set('seo-and-geo.storage.driver', 'file');

    app(StorageManager::class)->flush();

    foreach ([RedirectRepository::class, NotFoundLog::class, CrawlerLog::class] as $class) {
        app()->forgetInstance($class);
    }

    $this->crawlerLogPath = storage_path('app/'.config('seo-and-geo.ai_crawlers.log_path'));

    // What two overlapping writes actually left behind on a live site: one
    // request's "{}" on top of the other's "-\n".
    File::ensureDirectoryExists(dirname($this->crawlerLogPath));
    File::put($this->crawlerLogPath, "{}  date: '2026-10-12'\n  bot: Claude\n  hits: 3\n  last_path: /\n  last_seen: '2026-10-12 08:00:00'\n");
});

it('reads a corrupted crawler log as empty', function () {
    expect(app(CrawlerLog::class)->all())->toBeEmpty();
});

it('still renders the dashboard widget over a corrupted crawler log', function () {
    $this->actingAs(User::make()->email('widgets@example.com')->makeSuper()->save());

    $html = (new AiCrawlersWidget)->component()->toArray()['props']['html'];

    expect($html)->toBeString();
});

it('replaces a corrupted crawler log on the next visit', function () {
    app(CrawlerLog::class)->record('Claude', '/');

    expect(app(CrawlerLog::class)->totals())->toBe(['Claude' => 1]);
});

it('still throws on a corrupted redirects file', function () {
    $path = base_path((string) config('seo-and-geo.redirects.path'));
    File::ensureDirectoryExists(dirname($path));
    File::put($path, "{}  from: /old\n  to: /new\n");

    app(RedirectRepository::class)->all();
})->throws(ParseException::class);

it('leaves no temporary files behind', function () {
    app(CrawlerLog::class)->record('Claude', '/');
    app(CrawlerLog::class)->record('Perplexity', '/');

    expect(File::glob(dirname($this->crawlerLogPath).'/*.tmp'))->toBeEmpty();
});

it('serves a crawler the page when its visit cannot be logged', function () {
    $this->partialMock(CrawlerLog::class)
        ->shouldReceive('record')
        ->andThrow(new RuntimeException('Disk full'));

    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; ClaudeBot/1.0)')
        ->get('/robots.txt')
        ->assertOk();
});

it('still answers 404 when the 404 cannot be logged', function () {
    Route::fallback(fn () => abort(404))->middleware('web');

    $this->partialMock(NotFoundLog::class)
        ->shouldReceive('record')
        ->andThrow(new RuntimeException('Disk full'));

    $this->get('/nowhere')->assertNotFound();
});

it('counts every visit when requests race each other', function () {
    File::delete($this->crawlerLogPath);

    $children = 8;
    $visits = 25;
    $pids = [];

    for ($child = 0; $child < $children; $child++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                for ($visit = 0; $visit < $visits; $visit++) {
                    app(CrawlerLog::class)->record('Claude', '/');
                }
            } finally {
                // Never fall back into the test runner, and skip the shutdown
                // handlers, which belong to the parent.
                posix_kill(getmypid(), SIGKILL);
            }
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    expect(app(CrawlerLog::class)->totals())->toBe(['Claude' => $children * $visits]);
})->skip(! function_exists('pcntl_fork'), 'Needs pcntl to run requests in parallel.');
