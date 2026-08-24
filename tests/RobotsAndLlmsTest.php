<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Robots\RobotsTxt;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

it('serves a robots.txt with the sitemap and the control panel excluded', function () {
    Settings::swap([]);

    $robots = (new RobotsTxt)->render();

    expect($robots)
        ->toContain('User-agent: *')
        ->toContain('Disallow: /cp/')
        ->toContain('Sitemap: ');
});

it('blocks everything when the site is hidden from search engines', function () {
    Settings::swap(['noindex_site' => true]);

    expect((new RobotsTxt)->render())->toContain("User-agent: *\nDisallow: /");
});

it('blocks all AI crawlers on request', function () {
    Settings::swap(['ai_crawler_policy' => 'block']);

    $robots = (new RobotsTxt)->render();

    expect($robots)
        ->toContain("User-agent: GPTBot\nDisallow: /")
        ->toContain("User-agent: ClaudeBot\nDisallow: /");
});

it('blocks only the chosen AI crawlers', function () {
    Settings::swap(['ai_crawler_policy' => 'custom', 'blocked_ai_crawlers' => ['CCBot']]);

    $robots = (new RobotsTxt)->render();

    expect($robots)->toContain("User-agent: CCBot\nDisallow: /");
    expect($robots)->not->toContain('User-agent: GPTBot');
});

it('appends extra robots lines and custom disallows', function () {
    Settings::swap([
        'robots_disallow' => "/private\n/tmp",
        'robots_extra' => 'Crawl-delay: 10',
    ]);

    expect((new RobotsTxt)->render())
        ->toContain('Disallow: /private')
        ->toContain('Disallow: /tmp')
        ->toContain('Crawl-delay: 10')
        ->not->toContain('Disallow: /cp/');
});

it('builds an llms.txt from the settings', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'llms_summary' => 'A studio building Statamic sites.',
        'llms_intro' => 'We work in Belgium.',
        'knows_about' => ['Laravel', 'Statamic'],
    ]);

    $llms = LlmsTxt::forCurrentSite()->render();

    expect($llms)
        ->toContain('# Vulpo')
        ->toContain('> A studio building Statamic sites.')
        ->toContain('We work in Belgium.')
        ->toContain('## Expertise')
        ->toContain('- Laravel');
});

it('leaves redirect entries out of the llms.txt', function () {
    Settings::swap(['site_name' => 'Vulpo']);
    CollectionFacade::make('pages')->routes('/{slug}')->sites(['default'])->save();

    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();
    Entry::make()->collection('pages')->slug('old')->data(['title' => 'Old', 'redirect' => 'https://vulpo.be/new'])->save();

    LlmsTxt::flushCache();

    $llms = LlmsTxt::forCurrentSite()->render();

    expect($llms)->toContain('[About]');
    expect($llms)->not->toContain('[Old]');
    expect($llms)->not->toContain('vulpo.be/new');
});

it('sends cache headers on robots.txt and llms.txt', function () {
    Settings::swap([]);

    config()->set('seo.robots.cache_minutes', 30);
    config()->set('seo.llms.cache_minutes', 30);

    LlmsTxt::flushCache();

    $this->get('/robots.txt')->assertHeader('Cache-Control', 'max-age=1800, public, stale-while-revalidate=1800');
    $this->get('/llms.txt')->assertHeader('Cache-Control', 'max-age=1800, public, stale-while-revalidate=1800');
});
