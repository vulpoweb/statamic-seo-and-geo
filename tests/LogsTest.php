<?php

use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;

it('counts 404s per path', function () {
    $log = app(NotFoundLog::class);

    $log->record('/missing', 'https://example.com');
    $log->record('/missing/');
    $log->record('/other');

    expect($log->all())->toHaveCount(2);
    expect($log->all()->firstWhere('path', '/missing')['hits'])->toBe(2);
    expect($log->all()->firstWhere('path', '/missing')['referer'])->toBe('https://example.com');

    $log->forget('/missing');

    expect($log->all())->toHaveCount(1);
});

it('does not log 404s when switched off', function () {
    config()->set('vulpo-seo.redirects.log_not_found', false);

    $log = app(NotFoundLog::class);
    $log->record('/missing');

    expect($log->all())->toBeEmpty();
});

it('identifies known AI crawlers', function () {
    $log = app(CrawlerLog::class);

    expect($log->identify('Mozilla/5.0 (compatible; ClaudeBot/1.0)'))->toBe('Claude');
    expect($log->identify('Mozilla/5.0 (compatible; GPTBot/1.2)'))->toBe('OpenAI (training)');
    expect($log->identify('Mozilla/5.0 (compatible; Googlebot/2.1)'))->toBeNull();
    expect($log->identify(null))->toBeNull();
});

it('counts crawler visits per bot', function () {
    $log = app(CrawlerLog::class);

    $log->record('Claude', '/');
    $log->record('Claude', '/about');
    $log->record('Perplexity', '/');

    expect($log->totals())->toBe(['Claude' => 2, 'Perplexity' => 1]);
    expect($log->all()->firstWhere('bot', 'Claude')['last_path'])->toBe('/about');
});
