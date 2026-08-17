<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Redirects\UriLedger;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

beforeEach(function () {
    CollectionFacade::make('pages')->routes('/{slug}')->save();

    $this->entry = Entry::make()
        ->collection('pages')
        ->slug('about')
        ->data(['title' => 'About us', 'seo_description' => 'Who we are']);

    $this->entry->save();
});

it('injects the SEO tabs into entry blueprints', function () {
    $tabs = array_keys(CollectionFacade::find('pages')->entryBlueprint()->contents()['tabs']);

    expect($tabs)->toContain('vulpo_seo', 'vulpo_seo_schema');
});

it('lists published entries in the sitemap', function () {
    $urls = collect(Sitemap::forCurrentSite()->urls())->pluck('loc');

    expect($urls)->toContain($this->entry->absoluteUrl());
});

it('leaves entries out of the sitemap when asked', function () {
    $this->entry->set('seo_sitemap_exclude', true)->save();

    Sitemap::flushCache();

    expect(collect(Sitemap::forCurrentSite()->urls())->pluck('loc'))
        ->not->toContain($this->entry->absoluteUrl());
});

it('creates a redirect when a slug changes', function () {
    app(UriLedger::class)->prime();

    $this->entry->slug('about-us')->save();

    expect(app(RedirectRepository::class)->resolve('/about'))
        ->toBe(['to' => '/about-us', 'status' => 301]);
});

it('does not create a redirect when the setting is off', function () {
    Settings::swap(['auto_create_redirects' => false]);
    app(UriLedger::class)->prime();

    $this->entry->slug('about-us')->save();

    expect(app(RedirectRepository::class)->all())->toBeEmpty();
});

it('records the URL of a saved entry', function () {
    expect(app(UriLedger::class)->get((string) $this->entry->id(), (string) $this->entry->locale()))
        ->toBe('/about');
});

it('forgets an entry that was deleted', function () {
    app(UriLedger::class)->prime();

    $id = (string) $this->entry->id();
    $locale = (string) $this->entry->locale();

    $this->entry->delete();

    expect(app(UriLedger::class)->get($id, $locale))->toBeNull();
});

it('lists entries in llms.txt with their description', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    expect(LlmsTxt::forCurrentSite()->render())
        ->toContain('- [About us]')
        ->toContain(': Who we are');
});
