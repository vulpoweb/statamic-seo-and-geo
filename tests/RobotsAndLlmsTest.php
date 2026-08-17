<?php

use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Robots\RobotsTxt;
use Vulpo\Seo\Support\Settings;

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
