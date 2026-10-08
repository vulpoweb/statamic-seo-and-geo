<?php

use Vulpo\Seo\Schema\Exceptions\InvalidSchemaValue;
use Vulpo\Seo\Schema\Nodes\AggregateOfferNode;
use Vulpo\Seo\Schema\Nodes\BreadcrumbListNode;
use Vulpo\Seo\Schema\Nodes\CollectionPageNode;
use Vulpo\Seo\Schema\Nodes\FaqPageNode;
use Vulpo\Seo\Schema\Nodes\ItemListNode;
use Vulpo\Seo\Schema\Nodes\OfferNode;
use Vulpo\Seo\Schema\Nodes\ProductNode;
use Vulpo\Seo\Schema\ReturnPolicy;
use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\ShippingDetails;
use Vulpo\Seo\Support\Settings;

function ctx(?string $canonical = null): SchemaContext
{
    return SchemaContext::for($canonical ?? url('/product/badmat'), 'nl');
}

it('builds a product with everything a rich result wants', function () {
    Settings::swap([]);

    $node = ProductNode::make('Badmat 50x80 - beige')
        ->description('<p>Zacht  katoen.</p>')
        ->sku('BM-5080-BEI')
        ->gtin('5407003880625')
        ->brand('Emotion')
        ->image(['/img/badmat.jpg', 'https://cdn.example.com/badmat-2.jpg'])
        ->condition('new')
        ->category('Badkamer')
        ->offer(OfferNode::fromMinorUnits(1995)->availability('in_stock')->priceValidUntil(null))
        ->toArray(ctx());

    expect($node['@type'])->toBe('Product')
        ->and($node['@id'])->toBe(url('/product/badmat').'#product')
        ->and($node['name'])->toBe('Badmat 50x80 - beige')
        ->and($node['description'])->toBe('Zacht katoen.')
        ->and($node['sku'])->toBe('BM-5080-BEI')
        ->and($node['gtin13'])->toBe('5407003880625')
        ->and($node['gtin'])->toBe('5407003880625')
        ->and($node['brand'])->toBe(['@type' => 'Brand', 'name' => 'Emotion'])
        ->and($node['itemCondition'])->toBe('https://schema.org/NewCondition')
        ->and($node['image'])->toBe([url('/img/badmat.jpg'), 'https://cdn.example.com/badmat-2.jpg'])
        ->and($node['offers']['@type'])->toBe('Offer')
        ->and($node['offers']['price'])->toBe('19.95')
        ->and($node['offers']['priceCurrency'])->toBe('EUR')
        ->and($node['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('is dropped entirely without a name', function () {
    expect(ProductNode::make('')->toArray(ctx()))->toBeNull();
});

it('leaves out a gtin it cannot verify rather than emitting it', function () {
    config()->set('seo-and-geo.schema.strict', false);

    $node = ProductNode::make('Badmat')->gtin('5407003880652')->toArray(ctx());

    expect($node)->not->toHaveKey('gtin13')->not->toHaveKey('gtin');
});

it('refuses a bad gtin outright in strict mode', function () {
    expect(fn () => ProductNode::make('Badmat')->gtin('5407003880652'))
        ->toThrow(InvalidSchemaValue::class);
});

it('names the site organisation as the seller by default', function () {
    Settings::swap([]);

    $offer = OfferNode::make('19.95')->toArray(ctx());

    expect($offer['seller'])->toBe(['@id' => rtrim(url('/'), '/').'/#organization']);
});

it('is not an offer without a price', function () {
    config()->set('seo-and-geo.schema.strict', false);

    expect(OfferNode::make('op aanvraag')->toArray(ctx()))->toBeNull();
});

it('supplies an end date for a price that has none', function () {
    // Google warns about an offer with no priceValidUntil.
    Settings::swap(['shop_price_valid_days' => 30]);

    expect(OfferNode::make('19.95')->toArray(ctx())['priceValidUntil'])
        ->toBe(now()->addDays(30)->toDateString());
});

it('respects a deliberate "this price has no end"', function () {
    Settings::swap(['shop_price_valid_days' => 30]);

    expect(OfferNode::make('19.95')->priceValidUntil(null)->toArray(ctx()))
        ->not->toHaveKey('priceValidUntil');
});

it('attaches the shop shipping and returns without being asked', function () {
    Settings::swap([
        'shop_shipping_rate' => '5.95',
        'shop_shipping_countries' => ['BE'],
        'shop_handling_days_min' => 0,
        'shop_handling_days_max' => 1,
        'shop_transit_days_min' => 1,
        'shop_transit_days_max' => 3,
        'shop_return_days' => 14,
        'shop_return_countries' => ['BE'],
        'shop_return_fees' => 'ReturnShippingFees',
    ]);

    $offer = OfferNode::make('19.95')->toArray(ctx());

    expect($offer['shippingDetails']['shippingRate'])
        ->toBe(['@type' => 'MonetaryAmount', 'value' => '5.95', 'currency' => 'EUR']);

    expect($offer['shippingDetails']['shippingDestination'])
        ->toBe([['@type' => 'DefinedRegion', 'addressCountry' => 'BE']]);

    expect($offer['shippingDetails']['deliveryTime']['handlingTime']['maxValue'])->toBe(1)
        ->and($offer['shippingDetails']['deliveryTime']['transitTime']['maxValue'])->toBe(3);

    expect($offer['hasMerchantReturnPolicy'])->toBe([
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => ['BE'],
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 14,
        'returnMethod' => 'https://schema.org/ReturnByMail',
        'returnFees' => 'https://schema.org/ReturnShippingFees',
    ]);
});

it('says so plainly when a shop takes no returns', function () {
    Settings::swap(['shop_return_days' => 0]);

    expect(ReturnPolicy::fromSettings()->toArray(ctx())['returnPolicyCategory'])
        ->toBe('https://schema.org/MerchantReturnNotPermitted');
});

it('offers nothing when the shop settings are empty', function () {
    Settings::swap([]);

    expect(ShippingDetails::fromSettings())->toBeNull()
        ->and(ReturnPolicy::fromSettings())->toBeNull();
});

it('works out a price range across the variants', function () {
    Settings::swap([]);

    $node = AggregateOfferNode::fromOffers(
        OfferNode::fromMinorUnits(1995)->availability('out_of_stock'),
        OfferNode::fromMinorUnits(4995)->availability('in_stock'),
        OfferNode::fromMinorUnits(2995)->availability('out_of_stock'),
    )->toArray(ctx());

    expect($node['@type'])->toBe('AggregateOffer')
        ->and($node['lowPrice'])->toBe('19.95')
        ->and($node['highPrice'])->toBe('49.95')
        ->and($node['offerCount'])->toBe(3)
        // One size being sold out does not make the product unbuyable.
        ->and($node['availability'])->toBe('https://schema.org/InStock')
        ->and($node['offers'])->toHaveCount(3);
});

it('puts a hand-set range the right way round', function () {
    Settings::swap([]);

    $node = AggregateOfferNode::make()->lowPrice(50)->highPrice(10)->toArray(ctx());

    expect($node['lowPrice'])->toBe('10.00')->and($node['highPrice'])->toBe('50.00');
});

it('is not an aggregate offer without a price', function () {
    expect(AggregateOfferNode::make()->toArray(ctx()))->toBeNull();
});

it('numbers a listing from where the page starts', function () {
    $node = ItemListNode::make()
        ->name('Badkamer')
        ->item('/product/a', 'A')
        ->item('/product/b', 'B')
        ->numberOfItems(60)
        ->page(page: 2, perPage: 24)
        ->toArray(ctx(url('/badkamer')));

    expect(array_column($node['itemListElement'], 'position'))->toBe([25, 26])
        // The total is the whole listing, not this page of it.
        ->and($node['numberOfItems'])->toBe(60);
});

it('drops a listing entry with an unusable url', function () {
    config()->set('seo-and-geo.schema.strict', false);

    $node = ItemListNode::make()->item('/product/a', 'A')->item('', 'B')->toArray(ctx());

    expect($node['itemListElement'])->toHaveCount(1)
        ->and($node['numberOfItems'])->toBe(1);
});

it('is not a list with nothing in it', function () {
    expect(ItemListNode::make()->name('Badkamer')->toArray(ctx()))->toBeNull();
});

it('replaces the webpage when the page is a listing', function () {
    $node = CollectionPageNode::make('Badkamer')
        ->mainEntity(ItemListNode::make()->item('/product/a', 'A'))
        ->toArray(ctx(url('/badkamer')));

    // Same @id as the WebPage it stands in for: a page cannot be both.
    expect($node['@id'])->toBe(url('/badkamer').'#webpage')
        ->and($node['@type'])->toBe('CollectionPage')
        ->and($node['mainEntity']['@type'])->toBe('ItemList');
});

it('puts home in front of a trail', function () {
    $node = BreadcrumbListNode::fromArray([
        ['name' => 'Badkamer', 'url' => '/badkamer'],
        ['name' => 'Badmat'],
    ])->toArray(ctx());

    expect(array_column($node['itemListElement'], 'name'))->toBe(['Home', 'Badkamer', 'Badmat'])
        ->and(end($node['itemListElement'])['item'])->toBe(url('/product/badmat'));
});

it('is not a trail with one stop', function () {
    expect(BreadcrumbListNode::make()->withHome(false)->crumb('Badmat')->toArray(ctx()))->toBeNull();
});

it('reads faq rows off a grid field', function () {
    $node = FaqPageNode::fromRows([
        ['question' => 'Is hij machinewasbaar?', 'answer' => '<p>Ja, op  40 graden.</p>'],
        ['question' => 'Krimpt hij?', 'answer' => ''],
        ['question' => '', 'answer' => 'Niet veel.'],
    ])->toArray(ctx());

    // Half a pair is not a question.
    expect($node['mainEntity'])->toHaveCount(1)
        ->and($node['mainEntity'][0]['name'])->toBe('Is hij machinewasbaar?')
        ->and($node['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Ja, op 40 graden.');
});

it('is not an faq page with no questions', function () {
    expect(FaqPageNode::fromRows([])->toArray(ctx()))->toBeNull();
});

it('refuses to invent a rating', function () {
    // Google treats a fabricated rating as spam, and there is no version of
    // this that is worth it.
    $node = ProductNode::make('Badmat')
        ->offer(OfferNode::make('19.95'))
        ->aggregateRating(4.8, 0)
        ->toArray(ctx());

    expect($node)->not->toHaveKey('aggregateRating');
});
