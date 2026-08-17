<?php

use Illuminate\Support\Facades\Route;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Support\Settings;

beforeEach(function () {
    // Stand in for Statamic's front-end catch-all, which is what turns an
    // unknown URL into a 404 response inside the web middleware group.
    Route::fallback(fn () => abort(404))->middleware('web');
});

it('serves the sitemap as XML', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', false);
});

it('hides the sitemap when it is switched off', function () {
    Settings::swap(['sitemap_enabled' => false]);

    $this->get('/sitemap.xml')->assertNotFound();
});

it('serves robots.txt and llms.txt as plain text', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $this->get('/robots.txt')->assertOk()->assertSee('User-agent: *');
    $this->get('/llms.txt')->assertOk()->assertSee('# Vulpo');
});

it('redirects a 404 that has a matching rule', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/old-page', to: '/new-page'));

    $this->get('/old-page')->assertRedirect('/new-page')->assertStatus(301);
});

it('keeps the query string when redirecting', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/old-page', to: '/new-page'));

    $this->get('/old-page?utm_source=newsletter')
        ->assertRedirect('/new-page?utm_source=newsletter');
});

it('returns 410 for a gone rule', function () {
    app(RedirectRepository::class)->add(new Redirect(from: '/removed', to: '/removed-elsewhere', status: 410));

    $this->get('/removed')->assertStatus(410);
});

it('logs a 404 that has no rule', function () {
    $this->get('/nowhere')->assertNotFound();

    expect(app(NotFoundLog::class)->all()->pluck('path'))->toContain('/nowhere');
});

it('logs AI crawler visits', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; ClaudeBot/1.0)')->get('/robots.txt');

    expect(app(CrawlerLog::class)->totals())->toHaveKey('Claude');
});
