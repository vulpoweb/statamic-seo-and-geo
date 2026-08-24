<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

beforeEach(function () {
    CollectionFacade::make('pages')->routes('/{slug}')->sites(['default'])->save();
});

it('serves a single sitemap while the URLs fit', function () {
    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();

    Sitemap::flushCache();

    expect(Sitemap::forCurrentSite()->pages())->toBe(1);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertDontSee('<sitemapindex', false);
});

it('turns into an index once the URLs no longer fit', function () {
    config()->set('seo.sitemap.max_urls', 2);

    foreach (range(1, 5) as $i) {
        Entry::make()->collection('pages')->slug('page-'.$i)->data(['title' => 'Page '.$i])->save();
    }

    Sitemap::flushCache();

    expect(Sitemap::forCurrentSite()->pages())->toBe(3);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<sitemapindex', false)
        ->assertSee('/sitemap-1.xml', false)
        ->assertSee('/sitemap-3.xml', false);
});

it('serves each page of the index', function () {
    config()->set('seo.sitemap.max_urls', 2);

    foreach (range(1, 5) as $i) {
        Entry::make()->collection('pages')->slug('page-'.$i)->data(['title' => 'Page '.$i])->save();
    }

    Sitemap::flushCache();

    $this->get('/sitemap-1.xml')->assertOk()->assertSee('<urlset', false);
    $this->get('/sitemap-3.xml')->assertOk();
    // Nothing beyond the last page.
    $this->get('/sitemap-4.xml')->assertNotFound();
});

it('no longer drops URLs past the limit', function () {
    config()->set('seo.sitemap.max_urls', 2);

    foreach (range(1, 5) as $i) {
        Entry::make()->collection('pages')->slug('page-'.$i)->data(['title' => 'Page '.$i])->save();
    }

    Sitemap::flushCache();

    // Five entries across three pages, all of them present.
    $total = collect(range(1, 3))->flatMap(fn ($page) => Sitemap::forCurrentSite()->page($page));

    expect($total)->toHaveCount(5);
});

it('lists hreflang alternates on a multisite install', function () {
    // Statamic keeps collections single-site unless Pro is on, so a multisite
    // test has to say so before the sites are registered.
    // Statamic keeps everything single-site until multisite is switched on.
    config()->set('statamic.editions.pro', true);
    config()->set('statamic.system.multisite', true);

    Site::setSites([
        'default' => ['name' => 'Dutch', 'locale' => 'nl_BE', 'url' => 'http://localhost/'],
        'fr' => ['name' => 'French', 'locale' => 'fr_BE', 'url' => 'http://localhost/fr/'],
    ]);

    CollectionFacade::make('pages')
        ->sites(['default', 'fr'])
        ->routes(['default' => '/{slug}', 'fr' => '/fr/{slug}'])
        ->save();

    $entry = Entry::make()->collection('pages')->slug('about')->data(['title' => 'About']);
    $entry->save();
    $entry->makeLocalization('fr')->slug('a-propos')->data(['title' => 'À propos'])->save();

    Sitemap::flushCache();

    $url = collect(Sitemap::forCurrentSite()->urls())->firstWhere('loc', $entry->absoluteUrl());

    expect(collect($url['alternates'])->pluck('hreflang')->all())->toBe(['nl', 'fr']);

    $this->get('/sitemap.xml')
        ->assertSee('xmlns:xhtml', false)
        ->assertSee('hreflang="fr"', false);
});

it('leaves alternates out on a single site', function () {
    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();

    Sitemap::flushCache();

    expect(Sitemap::forCurrentSite()->urls()[0]['alternates'])->toBe([]);
    $this->get('/sitemap.xml')->assertDontSee('hreflang', false);
});

it('leaves out entries that only redirect somewhere else', function () {
    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();
    Entry::make()->collection('pages')->slug('old')->data(['title' => 'Old', 'redirect' => 'https://vulpo.be/new'])->save();
    Entry::make()->collection('pages')->slug('gone')->data(['title' => 'Gone', 'redirect' => 404])->save();

    Sitemap::flushCache();

    $locations = collect(Sitemap::forCurrentSite()->urls())->pluck('loc');

    expect($locations)->toHaveCount(1);
    expect($locations->first())->toEndWith('/about');
    // absoluteUrl() on a redirect entry returns the destination, so without the
    // check the sitemap would advertise vulpo.be/new as one of our own pages.
    expect($locations->first())->not->toContain('vulpo.be');
});

it('sends cache headers on the sitemap', function () {
    config()->set('seo.sitemap.cache_minutes', 15);

    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();
    Sitemap::flushCache();

    $this->get('/sitemap.xml')->assertHeader('Cache-Control', 'max-age=900, public, stale-while-revalidate=900');
});

it('tells caches not to store the sitemap when caching is off', function () {
    config()->set('seo.sitemap.cache_minutes', 0);

    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();
    Sitemap::flushCache();

    $this->get('/sitemap.xml')->assertHeader('Cache-Control', 'no-store, private');
});

it('includes taxonomy terms when the setting is on', function () {
    Settings::swap(['sitemap_include_terms' => true]);

    Taxonomy::make('topics')->sites(['default'])->save();
    CollectionFacade::find('pages')->taxonomies(['topics'])->save();
    Term::make('statamic')->taxonomy('topics')->data(['title' => 'Statamic'])->save();

    Sitemap::flushCache();

    $locations = collect(Sitemap::forCurrentSite()->urls())->pluck('loc')->implode(' ');

    expect($locations)->toContain('/topics/statamic');
});

it('leaves terms out by default, and honours the excluded taxonomies', function () {
    Taxonomy::make('topics')->sites(['default'])->save();
    CollectionFacade::find('pages')->taxonomies(['topics'])->save();
    Term::make('statamic')->taxonomy('topics')->data(['title' => 'Statamic'])->save();

    Settings::swap([]);
    Sitemap::flushCache();

    expect(collect(Sitemap::forCurrentSite()->urls())->pluck('loc')->implode(' '))->not->toContain('/topics/');

    Settings::swap(['sitemap_include_terms' => true, 'sitemap_exclude_taxonomies' => ['topics']]);
    Sitemap::flushCache();

    expect(collect(Sitemap::forCurrentSite()->urls())->pluck('loc')->implode(' '))->not->toContain('/topics/');
});

it('leaves out a term marked noindex', function () {
    Settings::swap(['sitemap_include_terms' => true]);

    Taxonomy::make('topics')->sites(['default'])->save();
    CollectionFacade::find('pages')->taxonomies(['topics'])->save();
    Term::make('hidden')->taxonomy('topics')->data(['title' => 'Hidden', 'seo_noindex' => true])->save();
    Term::make('shown')->taxonomy('topics')->data(['title' => 'Shown'])->save();

    Sitemap::flushCache();

    $locations = collect(Sitemap::forCurrentSite()->urls())->pluck('loc')->implode(' ');

    expect($locations)->toContain('/topics/shown');
    expect($locations)->not->toContain('/topics/hidden');
});
