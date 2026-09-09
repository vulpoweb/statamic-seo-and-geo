<?php

namespace Vulpo\Seo\Tests;

use Statamic\Testing\AddonTestCase;
use Vulpo\Seo\ServiceProvider;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Support\Settings;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::swap([]);

        config()->set('seo-and-geo.redirects.path', 'storage/framework/testing/redirects.yaml');
        config()->set('seo-and-geo.redirects.not_found_log_path', 'testing/not-found.yaml');
        config()->set('seo-and-geo.ai_crawlers.log_path', 'testing/ai-crawlers.yaml');
        config()->set('seo-and-geo.redirects.uri_ledger_path', 'testing/uris.yaml');
        config()->set('seo-and-geo.sitemap.cache_minutes', 0);
        config()->set('seo-and-geo.llms.cache_minutes', 0);

        // Lets the whole suite be run against database storage with
        // VULPO_SEO_STORAGE_DRIVER=eloquent, which is what CI does.
        if (app(StorageManager::class)->isEloquent()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->artisan('migrate')->run();
        }

        $this->cleanUpFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanUpFiles();

        Settings::flush();

        parent::tearDown();
    }

    private function cleanUpFiles(): void
    {
        foreach ([
            base_path((string) config('seo-and-geo.redirects.path')),
            storage_path('app/'.config('seo-and-geo.redirects.not_found_log_path')),
            storage_path('app/'.config('seo-and-geo.ai_crawlers.log_path')),
            storage_path('app/'.config('seo-and-geo.redirects.uri_ledger_path')),
        ] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
