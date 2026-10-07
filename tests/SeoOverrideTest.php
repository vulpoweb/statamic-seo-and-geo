<?php

use Statamic\Tags\Context;
use Vulpo\Seo\Contracts\ProvidesSeo;
use Vulpo\Seo\Facades\Seo;
use Vulpo\Seo\Seo\Meta;
use Vulpo\Seo\Seo\Schema;
use Vulpo\Seo\Seo\SeoOverlay;
use Vulpo\Seo\Support\Settings;

/*
 * The regression this whole feature exists for: a route with no entry behind it
 * used to mean branching away from {{ vulpo_seo }} and hand-writing the head,
 * which cost that page the Organization, the WebSite, the Twitter card and the
 * verification tags. A controller says what an entry would have said instead.
 */

beforeEach(function () {
    Seo::flush();
});

function overriddenMeta(array $context = []): Meta
{
    return Meta::forContext(new Context($context), app(SeoOverlay::class));
}

function overriddenSchema(array $context = []): Schema
{
    return Schema::forContext(new Context($context), app(SeoOverlay::class));
}

it('gives an entryless route the same head an entry would get', function () {
    Settings::swap([
        'site_name' => 'Gloed interieur',
        'business_name' => 'Gloed interieur',
        'twitter_handle' => 'gloed',
        'verify_google' => 'abc123',
        'default_image' => '/og.jpg',
    ]);

    Seo::override([
        'title' => 'Badmat 50x80',
        'description' => 'Zacht katoen, zware kwaliteit.',
        'og_type' => 'product',
    ]);

    $rendered = overriddenMeta()->render();

    expect($rendered)
        ->toContain('<title>Badmat 50x80 | Gloed interieur</title>')
        ->toContain('<meta name="description" content="Zacht katoen, zware kwaliteit.">')
        ->toContain('<meta property="og:type" content="product">')
        ->toContain('<meta property="og:site_name" content="Gloed interieur">')
        ->toContain('<meta property="og:locale"')
        ->toContain('<meta name="twitter:card"')
        ->toContain('<meta name="twitter:site" content="@gloed">')
        ->toContain('<meta name="google-site-verification" content="abc123">');

    // And the site-level nodes come along, which is what the hand-written
    // version dropped.
    expect(collect(overriddenSchema()->nodes())->pluck('@type'))
        ->toContain('Organization')
        ->toContain('WebSite');
});

it('beats a field the page already sets', function () {
    Seo::override(['title' => 'From the route']);

    expect(overriddenMeta(['seo_title' => 'From the entry'])->title())->toContain('From the route');
});

it('loses to the page when it is only a default', function () {
    Seo::defaults(['title' => 'From the route']);

    expect(overriddenMeta(['seo_title' => 'From the entry'])->title())->toContain('From the entry');
});

it('uses a default when the page says nothing', function () {
    Settings::swap(['default_description' => 'Site wide']);
    Seo::defaults(['description' => 'From the route']);

    // Beats the settings, loses to a field.
    expect(overriddenMeta()->description())->toBe('From the route');
});

it('keeps the last word when called twice', function () {
    Seo::override(['title' => 'First', 'description' => 'Kept']);
    Seo::override(['title' => 'Second']);

    expect(overriddenMeta()->title())->toContain('Second')
        ->and(overriddenMeta()->description())->toBe('Kept');
});

it('refuses a key it does not know', function () {
    // A typo in a controller should stop the first request in development,
    // not quietly stop saying what it meant to.
    expect(fn () => Seo::override(['titel' => 'Badmat']))
        ->toThrow(InvalidArgumentException::class, '[titel]');
});

it('takes the values off an object that offers them', function () {
    $product = new class implements ProvidesSeo
    {
        public function toSeoArray(): array
        {
            return ['title' => 'Badmat 50x80', 'description' => 'Zacht katoen.'];
        }
    };

    Seo::for($product);

    expect(overriddenMeta()->title())->toContain('Badmat 50x80')
        ->and(overriddenMeta()->description())->toBe('Zacht katoen.');
});

it('treats a null the same as nothing at all', function () {
    // A DTO with a nullable property should not have to filter before calling.
    Settings::swap(['default_description' => 'Site wide']);
    Seo::override(['description' => null]);

    expect(overriddenMeta()->description())->toBe('Site wide');
});

it('forgets everything on flush', function () {
    Seo::override(['title' => 'Badmat']);
    Seo::flush();

    expect(app(SeoOverlay::class)->isEmpty())->toBeTrue();
});

it('marks a route noindex', function () {
    Seo::noindex();

    expect(overriddenMeta()->robots())->toBe('noindex, follow');
});

it('marks a route noindex and nofollow', function () {
    Seo::noindex(nofollow: true);

    expect(overriddenMeta()->robots())->toBe('noindex, nofollow');
});

it('marks whole paths noindex from the settings', function () {
    Settings::swap(['noindex_paths' => "winkelmand\nafrekenen/*"]);

    $this->get('/afrekenen/bedankt');

    expect(overriddenMeta()->robots())->toBe('noindex, follow');
});

it('unions the settings paths with the config ones', function () {
    // Neither side silently deletes the other's work.
    Settings::swap(['noindex_paths' => 'winkelmand']);
    config()->set('seo-and-geo.robots.noindex_paths', ['zoeken']);

    $this->get('/zoeken');

    expect(overriddenMeta()->robots())->toBe('noindex, follow');
});

it('leaves an unlisted path alone', function () {
    Settings::swap(['noindex_paths' => 'winkelmand']);

    $this->get('/badkamer');

    expect(overriddenMeta()->robots())->toStartWith('index, follow');
});

it('takes a breadcrumb trail from the route', function () {
    Settings::swap(['site_name' => 'Gloed']);

    Seo::breadcrumbs([
        ['name' => 'Badkamer', 'url' => '/badkamer'],
        ['name' => 'Badmat 50x80'],
    ]);

    $crumbs = collect(overriddenSchema()->nodes())->firstWhere('@type', 'BreadcrumbList');

    expect(array_column($crumbs['itemListElement'], 'name'))
        ->toBe(['Home', 'Badkamer', 'Badmat 50x80']);

    // The last crumb is the page you are on, so it needs no url of its own.
    expect(end($crumbs['itemListElement'])['item'])->toBe(url('/'));
});

it('drops a crumb that has nowhere to point', function () {
    // A ListItem without an item is only valid last, and a trail that links to
    // a page which does not exist is worse than a shorter trail.
    Settings::swap(['site_name' => 'Gloed']);

    Seo::breadcrumbs([
        ['name' => 'Gloed', 'url' => null],
        ['name' => 'Badkamer', 'url' => '/badkamer'],
        ['name' => 'Badmat 50x80'],
    ]);

    $crumbs = collect(overriddenSchema()->nodes())->firstWhere('@type', 'BreadcrumbList');

    expect(array_column($crumbs['itemListElement'], 'name'))
        ->toBe(['Home', 'Badkamer', 'Badmat 50x80']);
});

it('numbers the positions after the drop, not before', function () {
    Settings::swap(['site_name' => 'Gloed']);

    Seo::breadcrumbs([
        ['name' => 'Gloed', 'url' => null],
        ['name' => 'Badkamer', 'url' => '/badkamer'],
        ['name' => 'Badmat'],
    ]);

    $crumbs = collect(overriddenSchema()->nodes())->firstWhere('@type', 'BreadcrumbList');

    expect(array_column($crumbs['itemListElement'], 'position'))->toBe([1, 2, 3]);
});

it('adds a node a route built by hand', function () {
    Settings::swap(['site_name' => 'Gloed']);

    Seo::schema(['@type' => 'Product', 'name' => 'Badmat 50x80']);

    expect(collect(overriddenSchema()->nodes())->pluck('@type'))->toContain('Product');
});

it('drops the nodes the fields would have produced', function () {
    Settings::swap(['site_name' => 'Gloed']);

    Seo::withoutSchema();

    expect(collect(overriddenSchema([
        'seo_schema_type' => 'product',
        'seo_schema_product_name' => 'From the fields',
        'seo_schema_product_price' => '19.95',
    ])->nodes())->pluck('@type'))->not->toContain('Product');
});

it('drops one type and leaves the rest', function () {
    Settings::swap(['site_name' => 'Gloed']);

    Seo::withoutSchema('WebSite');

    $types = collect(overriddenSchema()->nodes())->pluck('@type');

    expect($types)->not->toContain('WebSite')->toContain('Organization');
});

it('lets a route supply its own hreflang alternates', function () {
    // There is no entry to localise and ask.
    Seo::alternates([
        'nl' => 'https://example.com/nl/badmat',
        'fr' => 'https://example.com/fr/tapis-de-bain',
    ]);

    expect(overriddenMeta()->render())
        ->toContain('<link rel="alternate" hreflang="nl" href="https://example.com/nl/badmat">')
        ->toContain('<link rel="alternate" hreflang="fr" href="https://example.com/fr/tapis-de-bain">');
});

it('does not leak one request into the next', function () {
    // Scoped, not singleton: under Octane a singleton would carry one product's
    // title onto the next request the same worker served.
    Seo::override(['title' => 'Badmat']);

    app()->forgetScopedInstances();

    expect(app(SeoOverlay::class)->isEmpty())->toBeTrue();
});
