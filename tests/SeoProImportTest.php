<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Seo\Meta;
use Vulpo\Seo\Support\SeoProImport;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

uses(PreventsSavingStacheItemsToDisk::class);

it('translates a seo-pro page array to our handles', function () {
    $fields = (new SeoProImport)->fields([
        'title' => 'About us',
        'description' => 'Who we are.',
        'canonical_url' => 'https://vulpo.be/about',
        'robots_indexing' => 'noindex',
        'robots_following' => 'nofollow',
        'sitemap' => false,
        'priority' => '0.8',
        'change_frequency' => 'weekly',
        'json_ld_schema' => '{"@type":"HowTo"}',
    ]);

    expect($fields)->toBe([
        'seo_title' => 'About us',
        'seo_description' => 'Who we are.',
        'seo_canonical' => 'https://vulpo.be/about',
        'seo_noindex' => true,
        'seo_nofollow' => true,
        'seo_sitemap_exclude' => true,
        'seo_sitemap_priority' => '0.8',
        'seo_sitemap_changefreq' => 'weekly',
        'seo_schema_custom' => '{"@type":"HowTo"}',
    ]);
});

it('reads seo-pro legacy robots lists', function () {
    $fields = (new SeoProImport)->fields(['robots' => ['noindex', 'nofollow']]);

    expect($fields['seo_noindex'])->toBeTrue();
    expect($fields['seo_nofollow'])->toBeTrue();
});

it('treats a disabled seo-pro page as hidden', function () {
    $fields = (new SeoProImport)->fields(['enabled' => false]);

    expect($fields['seo_noindex'])->toBeTrue();
    expect($fields['seo_sitemap_exclude'])->toBeTrue();
});

it('drops seo-pro source references and antlers, which would render literally', function () {
    $fields = (new SeoProImport)->fields([
        'title' => '@seo:content/title',
        'description' => 'Read about {{ title }}',
        'json_ld_schema' => '{"name":"{{ title }}"}',
    ]);

    expect($fields)->toBe([]);
});

it('translates seo-pro site defaults to our settings', function () {
    $settings = (new SeoProImport)->settings([
        'site_name' => 'Vulpo',
        'description' => 'We build websites.',
        'twitter_handle' => '@vulpo',
        'google_verification' => 'abc123',
        'json_ld_entity' => 'organization',
        'json_ld_organization_name' => 'Vulpo BV',
        'json_ld_breadcrumbs' => false,
        'robots_indexing' => 'noindex',
        'site_name_position' => 'before',
        'change_frequency' => 'monthly',
    ]);

    expect($settings['site_name'])->toBe('Vulpo');
    expect($settings['default_description'])->toBe('We build websites.');
    expect($settings['twitter_handle'])->toBe('@vulpo');
    expect($settings['verify_google'])->toBe('abc123');
    expect($settings['business_name'])->toBe('Vulpo BV');
    expect($settings['schema_breadcrumbs'])->toBeFalse();
    expect($settings['noindex_site'])->toBeTrue();
    expect($settings['append_site_name'])->toBeFalse();
    expect($settings['sitemap_changefreq'])->toBe('monthly');
    // Nothing invented for keys SEO Pro did not set.
    expect($settings)->not->toHaveKey('default_image');
});

it('renders meta from seo-pro data before anything is migrated', function () {
    Settings::swap([]);
    CollectionFacade::make('pages')->routes('/{slug}')->sites(['default'])->save();

    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'seo' => [
            'title' => 'About Vulpo',
            'description' => 'Who we are.',
            'robots_indexing' => 'noindex',
        ],
    ]);
    $entry->save();

    $values = ValueReader::fromData($entry);

    expect($values->string('title'))->toBe('About Vulpo');
    expect($values->string('description'))->toBe('Who we are.');
    expect($values->bool('noindex'))->toBeTrue();
});

it('ignores seo-pro data when legacy fallbacks are switched off', function () {
    config()->set('seo-and-geo.legacy_fallbacks', false);

    CollectionFacade::make('pages')->routes('/{slug}')->sites(['default'])->save();

    $entry = Entry::make()->collection('pages')->slug('about')->data([
        'title' => 'About',
        'seo' => ['title' => 'About Vulpo'],
    ]);
    $entry->save();

    expect(ValueReader::fromData($entry)->string('title'))->toBeNull();
})->skip(fn () => ! class_exists(Meta::class), 'Meta missing');
