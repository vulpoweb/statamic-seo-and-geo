<?php

namespace Vulpo\Seo\Http\Controllers\CP;

use Illuminate\View\View;
use Statamic\Facades\User;
use Vulpo\Seo\AiCrawlers\CrawlerLog;

class AiCrawlersController
{
    public function __construct(private readonly CrawlerLog $log) {}

    public function index(): View
    {
        return view('vulpo-seo::cp.ai-crawlers', [
            'title' => __('AI crawlers'),
            'totals' => $this->log->totals(),
            'rows' => $this->log->all(),
            'canEdit' => (bool) User::current()?->can('edit vulpo seo'),
        ]);
    }

    public function clear()
    {
        $this->log->clear();

        return back();
    }
}
