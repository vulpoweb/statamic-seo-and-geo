<?php

namespace Vulpo\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Support\Settings;

class LogAiCrawlers
{
    public function __construct(private readonly CrawlerLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('vulpo-seo.ai_crawlers.enabled', true) || ! Settings::bool('log_ai_crawlers', true)) {
            return $next($request);
        }

        if ($bot = $this->log->identify($request->userAgent())) {
            $this->log->record($bot, '/'.trim($request->decodedPath(), '/'));
        }

        return $next($request);
    }
}
