<?php

namespace Vulpo\Seo\Widgets;

use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;
use Vulpo\Seo\AiCrawlers\CrawlerLog;

/**
 * Dashboard widget showing which AI assistants have been reading the site.
 */
class AiCrawlersWidget extends Widget
{
    protected static $handle = 'vulpo_seo_ai_crawlers';

    protected static $title = 'AI crawlers';

    public function component()
    {
        if (! User::current()?->can('view vulpo seo')) {
            return null;
        }

        $days = (int) $this->config('days', 7);
        $since = now()->subDays($days)->toDateString();

        $log = app(CrawlerLog::class);

        $totals = $log->all()
            ->filter(fn (array $row) => $row['date'] >= $since)
            ->groupBy('bot')
            ->map(fn ($rows) => (int) $rows->sum('hits'))
            ->sortDesc()
            ->take((int) $this->config('limit', 8));

        return VueComponent::render('dynamic-html-renderer', [
            'html' => view('vulpo-seo::widgets.ai-crawlers', [
                'totals' => $totals,
                'days' => $days,
                'title' => $this->config('title', __('AI crawlers')),
            ])->render(),
        ]);
    }
}
