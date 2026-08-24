<?php

use Statamic\Tags\Context;
use Vulpo\Seo\Seo\Schema;
use Vulpo\Seo\Support\Settings;

function schema(array $values = []): Schema
{
    return Schema::forContext(new Context($values));
}

it('outputs an organization and website node', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'business_description' => 'We build websites.',
        'knows_about' => ['Laravel', 'Statamic'],
    ]);

    $nodes = collect(schema()->nodes())->keyBy('@type');

    expect($nodes['Organization']['name'])->toBe('Vulpo');
    expect($nodes['Organization']['description'])->toBe('We build websites.');
    expect($nodes['Organization']['knowsAbout'])->toBe(['Laravel', 'Statamic']);
    expect($nodes['WebSite']['publisher']['@id'])->toBe($nodes['Organization']['@id']);
});

it('adds local business details only for local business types', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'business_type' => 'LocalBusiness',
        'address_street' => 'Kortrijksesteenweg 1',
        'address_locality' => 'Ghent',
        'postal_code' => '9000',
        'country_code' => 'BE',
        'price_range' => '€€',
        'geo_lat' => '51.05',
        'geo_lng' => '3.72',
        'opening_hours' => [
            ['day' => 'Monday', 'opens' => '09:00', 'closes' => '17:00'],
            ['day' => '', 'opens' => '', 'closes' => ''],
        ],
    ]);

    $organization = collect(schema()->nodes())->firstWhere('@type', 'LocalBusiness');

    expect($organization['address']['streetAddress'])->toBe('Kortrijksesteenweg 1');
    expect($organization['geo'])->toBe(['@type' => 'GeoCoordinates', 'latitude' => 51.05, 'longitude' => 3.72]);
    expect($organization['openingHoursSpecification'])->toHaveCount(1);
    expect($organization['priceRange'])->toBe('€€');
});

it('outputs an FAQ node from the page fields', function () {
    $faq = schema([
        'seo_schema_type' => 'faq',
        'seo_schema_faqs' => [
            ['question' => 'Do you build Statamic sites?', 'answer' => '<p>Yes.</p>'],
            ['question' => 'Incomplete row', 'answer' => ''],
        ],
    ])->page();

    expect($faq['@type'])->toBe('FAQPage');
    expect($faq['mainEntity'])->toHaveCount(1);
    expect($faq['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Yes.');
});

it('outputs a person node falling back to the page title', function () {
    $person = schema([
        'title' => 'Jane Doe',
        'seo_schema_type' => 'person',
        'seo_schema_person_job' => 'Founder',
    ])->page();

    expect($person['name'])->toBe('Jane Doe');
    expect($person['jobTitle'])->toBe('Founder');
});

it('reads legacy geo handles', function () {
    $service = schema([
        'geo_schema_type' => 'service',
        'geo_service_name' => 'Statamic development',
        'geo_service_area' => 'Belgium',
    ])->page();

    expect($service['@type'])->toBe('Service');
    expect($service['name'])->toBe('Statamic development');
    expect($service['areaServed'])->toBe('Belgium');
});

it('renders script tags', function () {
    Settings::swap(['site_name' => 'Vulpo']);

    expect(schema()->render())
        ->toContain('<script type="application/ld+json">')
        ->toContain('"@type": "Organization"');
});

it('can be switched off entirely', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'schema_organization' => false,
        'schema_website' => false,
        'schema_breadcrumbs' => false,
    ]);

    expect(schema()->nodes())->toBeEmpty();
});

it('outputs a product node with an offer', function () {
    $product = schema([
        'title' => 'Widget',
        'seo_schema_type' => 'product',
        'seo_schema_product_brand' => 'Vulpo',
        'seo_schema_product_sku' => 'W-1',
        'seo_schema_product_price' => '49.95',
        'seo_schema_product_availability' => 'OutOfStock',
    ])->page();

    expect($product['@type'])->toBe('Product');
    expect($product['name'])->toBe('Widget');
    expect($product['brand'])->toBe(['@type' => 'Brand', 'name' => 'Vulpo']);
    expect($product['offers']['price'])->toBe('49.95');
    expect($product['offers']['priceCurrency'])->toBe('EUR');
    expect($product['offers']['availability'])->toBe('https://schema.org/OutOfStock');
});

it('leaves the offer out of a product without a price', function () {
    $product = schema(['title' => 'Widget', 'seo_schema_type' => 'product'])->page();

    expect($product)->not->toHaveKey('offers');
});

it('outputs an event node', function () {
    $event = schema([
        'title' => 'Laravel meetup',
        'seo_schema_type' => 'event',
        'seo_schema_event_start' => '2026-09-01 19:00',
        'seo_schema_event_location' => 'Ghent',
    ])->page();

    expect($event['@type'])->toBe('Event');
    expect($event['name'])->toBe('Laravel meetup');
    expect($event['startDate'])->toBe('2026-09-01 19:00');
    expect($event['location'])->toBe(['@type' => 'Place', 'name' => 'Ghent']);
});

it('outputs custom JSON-LD as its own node', function () {
    $nodes = schema([
        'seo_schema_custom' => '{"@type":"HowTo","name":"Change a tyre"}',
    ])->nodes();

    $custom = collect($nodes)->firstWhere('@type', 'HowTo');

    expect($custom['name'])->toBe('Change a tyre');
    // The boilerplate is added when the pasted snippet leaves it out.
    expect($custom['@context'])->toBe('https://schema.org');
});

it('keeps a context the snippet already has', function () {
    $custom = schema([
        'seo_schema_custom' => '{"@context":"https://schema.org/","@type":"Recipe","name":"Stoofvlees"}',
    ])->custom();

    expect($custom['@context'])->toBe('https://schema.org/');
});

it('ignores invalid custom JSON instead of breaking the page', function () {
    expect(schema(['seo_schema_custom' => '{not json'])->custom())->toBeNull();
    expect(schema(['seo_schema_custom' => '"just a string"'])->custom())->toBeNull();
    expect(schema(['seo_schema_custom' => '{}'])->custom())->toBeNull();
});
