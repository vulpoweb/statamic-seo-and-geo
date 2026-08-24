<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

uses(PreventsSavingStacheItemsToDisk::class);

beforeEach(function () {
    CollectionFacade::make('pages')->routes('/{slug}')->sites(['default'])->save();
});

it('moves seo-pro page values onto our handles', function () {
    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'seo' => ['title' => 'About Vulpo', 'description' => 'Who we are.', 'sitemap' => false],
    ]);
    $entry->save();

    $this->artisan('vulpo:seo:migrate')->assertSuccessful();

    $fresh = Entry::find($entry->id());

    expect($fresh->get('seo_title'))->toBe('About Vulpo');
    expect($fresh->get('seo_description'))->toBe('Who we are.');
    expect($fresh->get('seo_sitemap_exclude'))->toBeTrue();
    // SEO Pro's own array is left alone, in case the addon is still installed.
    expect($fresh->get('seo'))->toBeArray();
});

it('renames legacy alt-seo handles', function () {
    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'alt_seo_meta_title' => 'About us',
        'alt_seo_noindex' => 'true',
    ]);
    $entry->save();

    $this->artisan('vulpo:seo:migrate')->assertSuccessful();

    $fresh = Entry::find($entry->id());

    expect($fresh->get('seo_title'))->toBe('About us');
    expect($fresh->get('seo_noindex'))->toBeTrue();
    expect($fresh->has('alt_seo_meta_title'))->toBeFalse();
});

it('never overwrites a value already entered on the new field', function () {
    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'seo_title' => 'Ours',
        'seo' => ['title' => 'Theirs'],
        'alt_seo_meta_description' => 'Theirs too',
        'seo_description' => 'Ours too',
    ]);
    $entry->save();

    $this->artisan('vulpo:seo:migrate')->assertSuccessful();

    $fresh = Entry::find($entry->id());

    expect($fresh->get('seo_title'))->toBe('Ours');
    expect($fresh->get('seo_description'))->toBe('Ours too');
});

it('writes nothing on a dry run', function () {
    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'seo' => ['title' => 'About Vulpo'],
    ]);
    $entry->save();

    $this->artisan('vulpo:seo:migrate --dry-run')->assertSuccessful();

    expect(Entry::find($entry->id())->get('seo_title'))->toBeNull();
});
