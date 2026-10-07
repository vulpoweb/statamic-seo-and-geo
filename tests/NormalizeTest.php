<?php

use Vulpo\Seo\Schema\Exceptions\InvalidSchemaValue;
use Vulpo\Seo\Schema\Support\Normalize;

/*
 * Strict mode is on for the whole suite (see TestCase), so the "rejects"
 * expectations below are the strict half. The lenient half is asserted
 * explicitly by turning it off, because production runs lenient and a node
 * silently losing a key is exactly the behaviour worth pinning.
 */

it('reads a price however it was typed', function (int|float|string|null $input, ?string $expected) {
    expect(Normalize::price($input))->toBe($expected);
})->with([
    'comma decimal' => ['49,95', '49.95'],
    'dot decimal' => ['49.95', '49.95'],
    'one decimal place' => [49.9, '49.90'],
    'integer' => [50, '50.00'],
    'dotted thousands, comma decimal' => ['1.299,00', '1299.00'],
    'spaced thousands' => ['1 299,00', '1299.00'],
    'comma thousands, dot decimal' => ['1,299.00', '1299.00'],
    'currency symbol' => ['€ 49,95', '49.95'],
    'zero' => [0, '0.00'],
    'empty' => ['', null],
    'null' => [null, null],
]);

it('refuses a price that is not a number', function () {
    expect(fn () => Normalize::price('op aanvraag'))->toThrow(InvalidSchemaValue::class);
});

it('reads cents without guessing', function () {
    expect(Normalize::priceFromMinorUnits(4995))->toBe('49.95')
        ->and(Normalize::priceFromMinorUnits(5))->toBe('0.05')
        ->and(Normalize::priceFromMinorUnits(0))->toBe('0.00')
        ->and(Normalize::priceFromMinorUnits(null))->toBeNull();
});

it('keeps the precision a date was written with', function () {
    // A date field means a day. Inventing midnight would be a claim the
    // content never made.
    expect(Normalize::date('2026-09-01'))->toBe('2026-09-01')
        ->and(Normalize::date('2026-09-01 19:00'))->toStartWith('2026-09-01T19:00:00')
        ->and(Normalize::date(new DateTimeImmutable('2026-09-01 19:00')))->toStartWith('2026-09-01T19:00:00')
        ->and(Normalize::date(null))->toBeNull();
});

it('reduces a date to a day for priceValidUntil', function () {
    expect(Normalize::day('2026-09-01 19:00'))->toBe('2026-09-01')
        ->and(Normalize::day(null))->toBeNull();
});

it('refuses something that is not a date', function () {
    expect(fn () => Normalize::date('binnenkort'))->toThrow(InvalidSchemaValue::class);
});

it('makes every url absolute', function () {
    expect(Normalize::url('/og.jpg'))->toBe(url('/og.jpg'))
        ->and(Normalize::url('https://cdn.example.com/a.jpg'))->toBe('https://cdn.example.com/a.jpg')
        ->and(Normalize::url('//cdn.example.com/a.jpg'))->toEndWith('//cdn.example.com/a.jpg')
        ->and(Normalize::url(''))->toBeNull()
        ->and(Normalize::url(null))->toBeNull();
});

it('validates a gtin by its check digit, not just its length', function () {
    // A real EAN-13 off a Gloed product.
    expect(Normalize::gtin('5407003880625'))->toBe([
        'gtin' => '5407003880625',
        'gtin13' => '5407003880625',
    ]);

    expect(Normalize::gtin('  540 7003 880625 '))->toBe([
        'gtin' => '5407003880625',
        'gtin13' => '5407003880625',
    ]);

    expect(Normalize::gtin(null))->toBeNull()
        ->and(Normalize::gtin(''))->toBeNull();
});

it('refuses a gtin with a transposed digit', function () {
    // Right length, wrong checksum — the failure a format check would miss.
    expect(fn () => Normalize::gtin('5407003880652'))->toThrow(InvalidSchemaValue::class);
});

it('refuses a gtin of an impossible length', function () {
    expect(fn () => Normalize::gtin('12345678901'))->toThrow(InvalidSchemaValue::class);
});

it('maps availability and condition onto schema.org urls', function () {
    expect(Normalize::availability('in_stock'))->toBe('https://schema.org/InStock')
        ->and(Normalize::availability('BackOrder'))->toBe('https://schema.org/BackOrder')
        ->and(Normalize::availability('https://schema.org/OutOfStock'))->toBe('https://schema.org/OutOfStock')
        ->and(Normalize::condition('new'))->toBe('https://schema.org/NewCondition')
        ->and(Normalize::availability(null))->toBeNull();
});

it('validates currency and country codes', function () {
    expect(Normalize::currency('eur'))->toBe('EUR')
        ->and(Normalize::country('be'))->toBe('BE');

    expect(fn () => Normalize::currency('euro'))->toThrow(InvalidSchemaValue::class);
    expect(fn () => Normalize::country('BEL'))->toThrow(InvalidSchemaValue::class);
});

it('flattens text and shortens it on a word', function () {
    expect(Normalize::text("  <p>Zacht   katoen</p>\n\n"))->toBe('Zacht katoen')
        ->and(Normalize::text('Een handdoek van zware badstof', 14))->toBe('Een handdoek')
        ->and(Normalize::text('<p> </p>'))->toBeNull()
        ->and(Normalize::text(null))->toBeNull();
});

it('drops absent values but keeps a deliberate false', function () {
    expect(Normalize::compact([
        'name' => 'Badmat',
        'description' => null,
        'sku' => '',
        'image' => [],
        'isAccessibleForFree' => false,
        'position' => 0,
        'offers' => ['price' => '19.95', 'seller' => null],
    ]))->toBe([
        'name' => 'Badmat',
        'isAccessibleForFree' => false,
        'position' => 0,
        'offers' => ['price' => '19.95'],
    ]);
});

it('reindexes a list once its holes are removed', function () {
    expect(Normalize::compact(['a', null, 'b']))->toBe(['a', 'b']);
});

it('drops rather than throws when strict mode is off', function () {
    config()->set('seo-and-geo.schema.strict', false);

    expect(Normalize::gtin('5407003880652'))->toBeNull()
        ->and(Normalize::price('op aanvraag'))->toBeNull()
        ->and(Normalize::currency('euro'))->toBeNull()
        ->and(Normalize::date('binnenkort'))->toBeNull();
});
