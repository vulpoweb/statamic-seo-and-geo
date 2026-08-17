<?php

use Statamic\Tags\Context;
use Vulpo\Seo\Seo\Meta;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

function meta(array $values = []): Meta
{
    return Meta::forContext(new Context($values));
}

it('builds a title from the page and the site name', function () {
    Settings::swap(['site_name' => 'Vulpo', 'title_separator' => '·']);

    expect(meta(['title' => 'About us'])->title())->toBe('About us · Vulpo');
});

it('prefers the SEO title over the page title', function () {
    Settings::swap(['site_name' => 'Vulpo', 'append_site_name' => false]);

    expect(meta(['title' => 'About us', 'seo_title' => 'About our team'])->title())->toBe('About our team');
});

it('falls back to the legacy alt-seo handles', function () {
    Settings::swap(['append_site_name' => false]);

    expect(meta(['title' => 'Page', 'alt_seo_meta_title' => 'Legacy title'])->title())->toBe('Legacy title');
    expect(meta(['alt_seo_meta_description' => 'Legacy description'])->description())->toBe('Legacy description');
});

it('ignores legacy handles when the fallback is switched off', function () {
    config()->set('vulpo-seo.legacy_fallbacks', false);
    Settings::swap(['append_site_name' => false]);

    expect(meta(['title' => 'Page', 'alt_seo_meta_title' => 'Legacy title'])->title())->toBe('Page');
});

it('uses the default description when the page has none', function () {
    Settings::swap(['default_description' => 'A default description']);

    expect(meta()->description())->toBe('A default description');
    expect(meta(['seo_description' => 'Page description'])->description())->toBe('Page description');
});

it('renders robots directives from the page fields', function () {
    expect(meta()->robots())->toBe('index, follow, max-image-preview:large');
    expect(meta(['seo_noindex' => true])->robots())->toBe('noindex, follow');
    expect(meta(['seo_nofollow' => true])->robots())->toBe('index, nofollow, max-image-preview:large');
});

it('hides the whole site when the setting says so', function () {
    Settings::swap(['noindex_site' => true]);

    expect(meta()->robots())->toBe('noindex, nofollow');
});

it('renders the head tags', function () {
    Settings::swap(['site_name' => 'Vulpo', 'twitter_handle' => 'vulpo']);

    $html = meta(['title' => 'About', 'seo_description' => 'Who we are'])->render();

    expect($html)
        ->toContain('<title>About | Vulpo</title>')
        ->toContain('<meta name="description" content="Who we are">')
        ->toContain('<meta property="og:title" content="About | Vulpo">')
        ->toContain('<meta name="twitter:site" content="@vulpo">')
        ->toContain('<link rel="canonical"');
});

it('escapes values in tags', function () {
    Settings::swap(['append_site_name' => false]);

    expect(meta(['title' => 'A "quoted" <title>'])->render())
        ->toContain('<title>A &quot;quoted&quot; &lt;title&gt;</title>');
});

it('leaves out open graph and twitter tags when disabled', function () {
    Settings::swap(['open_graph' => false, 'twitter_cards' => false]);

    expect(meta(['title' => 'About'])->render())
        ->not->toContain('og:title')
        ->not->toContain('twitter:card');
});

it('reads nothing from an empty value reader', function () {
    expect(ValueReader::empty()->string('title'))->toBeNull();
});
