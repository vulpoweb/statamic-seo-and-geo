<?php

namespace Vulpo\Seo\Tests;

use Statamic\Testing\AddonTestCase;
use Vulpo\Seo\ServiceProvider;
use Vulpo\Seo\Support\Settings;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::swap([]);

        config()->set('seo.redirects.path', 'storage/framework/testing/redirects.yaml');
        config()->set('seo.redirects.not_found_log_path', 'testing/not-found.yaml');
        config()->set('seo.ai_crawlers.log_path', 'testing/ai-crawlers.yaml');
        config()->set('seo.redirects.uri_ledger_path', 'testing/uris.yaml');
        config()->set('seo.sitemap.cache_minutes', 0);
        config()->set('seo.llms.cache_minutes', 0);

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
            base_path((string) config('seo.redirects.path')),
            storage_path('app/'.config('seo.redirects.not_found_log_path')),
            storage_path('app/'.config('seo.ai_crawlers.log_path')),
            storage_path('app/'.config('seo.redirects.uri_ledger_path')),
        ] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
