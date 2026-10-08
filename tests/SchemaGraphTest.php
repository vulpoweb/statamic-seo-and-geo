<?php

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Tags\Context;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Seo\Schema;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

function graphSchema(array $values = []): Schema
{
    return Schema::forContext(new Context($values));
}

function aboutPage(): string
{
    CollectionFacade::make('pages')->routes('{slug}')->structureContents(['root' => true])->save();

    $home = tap(Entry::make()->collection('pages')->slug('home')->data(['title' => 'Home']))->save();
    $about = tap(Entry::make()->collection('pages')->slug('about')->data(['title' => 'About']))->save();

    // With root: true the first node *is* the site root, so its siblings are
    // its children as far as a URL and a breadcrumb are concerned.
    CollectionFacade::find('pages')->structure()->in('default')->tree([
        ['entry' => $home->id()],
        ['entry' => $about->id()],
    ])->save();

    return $about->id();
}

it('emits one script holding a graph', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $rendered = graphSchema()->render();

    expect(substr_count($rendered, '<script type="application/ld+json">'))->toBe(1)
        ->and($rendered)->toContain('"@graph"');

    // One @context for the whole graph, not one per node.
    expect(substr_count($rendered, '"@context"'))->toBe(1);
});

it('still hands back self-contained nodes', function () {
    // nodes() is what anything built against this addon reads. The graph strips
    // the duplicate @context when it assembles, not before.
    Settings::swap(['site_name' => 'Vulpo']);

    foreach (graphSchema()->nodes() as $node) {
        expect($node['@context'])->toBe('https://schema.org');
    }
});

it('goes back to one script per node on request', function () {
    config()->set('seo-and-geo.schema.graph', false);
    Settings::swap(['site_name' => 'Vulpo']);

    $rendered = graphSchema()->render();

    expect(substr_count($rendered, '<script type="application/ld+json">'))->toBe(2)
        ->and($rendered)->not->toContain('"@graph"');
});

it('ties the page to the site through matching ids', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $nodes = collect(graphSchema(['id' => aboutPage()])->nodes())->keyBy('@type');

    expect($nodes)->toHaveKeys(['Organization', 'WebSite', 'BreadcrumbList', 'WebPage']);

    expect($nodes['WebPage']['isPartOf']['@id'])->toBe($nodes['WebSite']['@id'])
        ->and($nodes['WebPage']['breadcrumb']['@id'])->toBe($nodes['BreadcrumbList']['@id'])
        ->and($nodes['WebSite']['publisher']['@id'])->toBe($nodes['Organization']['@id']);
});

it('points the webpage at whatever the page is about', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $nodes = collect(graphSchema([
        'seo_schema_type' => 'product',
        'seo_schema_product_name' => 'Badmat',
        'seo_schema_product_price' => '19.95',
    ])->nodes())->keyBy('@type');

    expect($nodes['WebPage']['mainEntity']['@id'])->toBe($nodes['Product']['@id'] ?? null);
});

it('leaves out a webpage with nothing to link to', function () {
    // The homepage of a brochure site: no breadcrumb, no page node. A WebPage
    // carrying only its own url says nothing worth the bytes.
    Settings::swap(['site_name' => 'Vulpo']);

    expect(collect(graphSchema()->nodes())->pluck('@type')->all())
        ->toBe(['Organization', 'WebSite']);
});

it('can still be switched off entirely', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'schema_organization' => false,
        'schema_website' => false,
        'schema_breadcrumbs' => false,
    ]);

    expect(graphSchema()->nodes())->toBeEmpty()
        ->and(graphSchema()->render())->toBe('');
});

it('lets a later node replace an earlier one with the same id', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $nodes = graphSchema([
        'id' => aboutPage(),
        // Custom JSON-LD is emitted last, so it wins the @id it shares.
        'seo_schema_custom' => json_encode([
            '@type' => 'CollectionPage',
            '@id' => rtrim(url('/'), '/').'/about#webpage',
            'name' => 'Replaced',
        ]),
    ])->nodes();

    $byId = collect($nodes)->keyBy('@id');
    $webPageId = rtrim(url('/'), '/').'/about#webpage';

    expect($byId[$webPageId]['name'])->toBe('Replaced')
        ->and($byId[$webPageId]['@type'])->toBe('CollectionPage')
        ->and(collect($nodes)->where('@id', $webPageId))->toHaveCount(1);
});

it('accepts a list of custom nodes, not just one', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $types = collect(graphSchema([
        'seo_schema_custom' => json_encode([
            ['@type' => 'HowTo', 'name' => 'Een bed opmaken'],
            ['@type' => 'VideoObject', 'name' => 'Hoe je linnen wast'],
        ]),
    ])->nodes())->pluck('@type')->all();

    expect($types)->toContain('HowTo')->toContain('VideoObject');
});

it('lets faq rows stand alongside another page type', function () {
    // These used to be mutually exclusive: a product page with a questions
    // block had to choose which of the two true things to say.
    Settings::swap(['site_name' => 'Vulpo']);

    $types = collect(graphSchema([
        'seo_schema_type' => 'product',
        'seo_schema_product_name' => 'Badmat',
        'seo_schema_product_price' => '19.95',
        'seo_schema_faqs' => [
            ['question' => 'Is hij machinewasbaar?', 'answer' => 'Ja, op 40 graden.'],
        ],
    ])->nodes())->pluck('@type')->all();

    expect($types)->toContain('Product')->toContain('FAQPage');
});

it('does not emit the faq twice when it is the chosen type', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    $types = collect(graphSchema([
        'seo_schema_type' => 'faq',
        'seo_schema_faqs' => [
            ['question' => 'Is hij machinewasbaar?', 'answer' => 'Ja, op 40 graden.'],
        ],
    ])->nodes())->pluck('@type')->all();

    expect(collect($types)->filter(fn (string $t) => $t === 'FAQPage'))->toHaveCount(1);
});
