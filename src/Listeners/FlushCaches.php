<?php

namespace Vulpo\Seo\Listeners;

use Statamic\Events\AddonSettingsSaved;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Support\Settings;

/**
 * The sitemap and llms.txt are cached, so they need to be dropped whenever
 * content or settings change.
 */
class FlushCaches
{
    public function handleContentSaved(): void
    {
        Sitemap::flushCache();
        LlmsTxt::flushCache();
    }

    public function handleSettingsSaved(AddonSettingsSaved $event): void
    {
        if ($event->settings->addon()->id() !== Settings::PACKAGE) {
            return;
        }

        Settings::flush();

        $this->handleContentSaved();
    }
}
