<?php

use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Widgets\AiCrawlersWidget;
use Vulpo\Seo\Widgets\NotFoundWidget;

uses(PreventsSavingStacheItemsToDisk::class);

function widgetHtml(object $widget): ?string
{
    $component = $widget->component();

    return $component ? $component->toArray()['props']['html'] : null;
}

beforeEach(function () {
    $this->actingAs(User::make()->email('widgets@example.com')->makeSuper()->save());
});

it('lists recent 404s', function () {
    app(NotFoundLog::class)->record('/missing-page');

    expect(widgetHtml(new NotFoundWidget))
        ->toContain('/missing-page')
        ->toContain('<ui-card-panel')
        ->toContain(cp_route('vulpo-seo.not-found.index'));
});

it('says so when there are no 404s', function () {
    expect(widgetHtml(new NotFoundWidget))->toContain('No 404s logged.');
});

it('totals AI crawler visits within the window', function () {
    $log = app(CrawlerLog::class);
    $log->record('Claude', '/');
    $log->record('Claude', '/about');
    $log->record('Perplexity', '/');

    expect(widgetHtml(new AiCrawlersWidget))
        ->toContain('Claude')
        ->toContain('prepend="Perplexity"')
        ->toContain('text="2"');
});

it('stays empty for a user without permission', function () {
    $this->actingAs(User::make()->email('nobody@example.com')->save());

    expect(widgetHtml(new NotFoundWidget))->toBeNull();
    expect(widgetHtml(new AiCrawlersWidget))->toBeNull();
});
