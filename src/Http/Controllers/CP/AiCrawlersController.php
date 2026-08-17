<?php

namespace Vulpo\Seo\Http\Controllers\CP;

use Illuminate\View\View;
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
        ]);
    }

    public function clear()
    {
        $this->log->clear();

        return back();
    }
}
