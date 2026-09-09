<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use Statamic\Statamic;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Fieldtypes\SeoPreview;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

function preview(?object $parent = null): array
{
    $field = new Field('seo_preview', ['type' => 'seo_preview']);

    if ($parent) {
        $field->setParent($parent);
    }

    return (new SeoPreview)->setField($field)->preload();
}

it('is registered as a fieldtype', function () {
    expect(SeoPreview::handle())->toBe('seo_preview');
    expect(app('statamic.fieldtypes')->has('seo_preview'))->toBeTrue();
});

it('registers the control panel script', function () {
    expect(Statamic::availableScripts(request()))->toHaveKey('seo-and-geo');
});

it('hands the browser the site defaults it cannot know', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'title_separator' => '·',
        'default_description' => 'A default description',
    ]);

    $preloaded = preview();

    expect($preloaded['site_name'])->toBe('Vulpo');
    expect($preloaded['separator'])->toBe('·');
    expect($preloaded['append_site_name'])->toBeTrue();
    expect($preloaded['default_description'])->toBe('A default description');
    expect($preloaded['handles'])->toBe([
        'title' => 'seo_title',
        'description' => 'seo_description',
        'noindex' => 'seo_noindex',
        'image' => 'seo_image',
    ]);
});

it('uses the entry URL and title when there is an entry', function () {
    CollectionFacade::make('pages')->routes('/{slug}')->save();

    $entry = Entry::make()->collection('pages')->slug('about')->data(['title' => 'About us']);
    $entry->save();

    $preloaded = preview($entry);

    expect($preloaded['url'])->toBe($entry->absoluteUrl());
    expect($preloaded['fallback_title'])->toBe('About us');
});

it('reports when the page is hidden from search engines', function () {
    Settings::swap(['noindex_site' => true]);

    expect(preview()['noindex'])->toBeTrue();
});

it('stores nothing of its own', function () {
    $fieldtype = new SeoPreview;

    expect($fieldtype->process('anything'))->toBeNull();
    expect($fieldtype->preProcess('anything'))->toBeNull();
});

it('sits at the top of the injected SEO tab', function () {
    $blueprint = YAML::file(__DIR__.'/../resources/blueprints/entry-fields.yaml')->parse();

    $firstSection = $blueprint['tabs']['vulpo_seo']['sections'][0];

    expect($firstSection['fields'][0]['handle'])->toBe('seo_preview');
    expect($firstSection['fields'][0]['field']['type'])->toBe('seo_preview');
});

it('waits for Statamic instead of giving up', function () {
    // Addon scripts are emitted before the control panel's Vite modules, so
    // window.Statamic does not exist when the script first runs. Bailing out
    // there left the field rendering "Component seo_preview-fieldtype does not
    // exist", which no PHP test can catch — hence this check on the source.
    $script = file_get_contents(__DIR__.'/../resources/js/cp.js');

    expect($script)
        ->toContain('whenReady')
        ->not->toContain('if (!Statamic || !Vue) return');
});

it('reads the picked image from the publish form meta', function () {
    // The image has to come from meta, not from the value: the value is an asset
    // ID, and only meta carries the URL needed to render it before a save.
    $script = file_get_contents(__DIR__.'/../resources/js/cp.js');

    expect($script)
        ->toContain('container.meta')
        ->toContain('picked.permalink || picked.url');
});

it('says that Google does not use the sharing image', function () {
    expect(file_get_contents(__DIR__.'/../resources/js/cp.js'))
        ->toContain('Google picks any thumbnail from the page content itself');
});

it('keeps the Google mock readable in dark mode', function () {
    // Google's palette only works on Google's surface, so the mock carries its
    // own white background rather than inheriting the control panel's.
    $script = file_get_contents(__DIR__.'/../resources/js/cp.js');

    expect($script)->toContain('background:#fff');

    // Anything using the CP's palette must cover both modes.
    preg_match_all('/class="(text-gray-\d00[^"]*)"/', $script, $matches);

    foreach ($matches[1] as $classes) {
        expect($classes)->toContain('dark:');
    }
});
