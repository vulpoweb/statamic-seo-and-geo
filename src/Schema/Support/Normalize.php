<?php

namespace Vulpo\Seo\Schema\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Vulpo\Seo\Schema\Exceptions\InvalidSchemaValue;

/**
 * The validation every schema node runs its values through.
 *
 * Google is unforgiving in a particular way: a property it cannot parse does
 * not warn, it disqualifies the whole rich result. So each method here returns
 * null rather than a half-valid value, and the nodes drop null keys — a Product
 * without a gtin still earns a result, a Product with a malformed one does not.
 *
 * `seo-and-geo.schema.strict` flips the same rejections into exceptions. Keep it
 * on in a test suite and off in production: you want a bad GTIN to fail CI, not
 * to take down a product page.
 */
final class Normalize
{
    /** Lengths GS1 actually issues. Anything else is not a GTIN. */
    private const GTIN_LENGTHS = [8, 12, 13, 14];

    private const AVAILABILITY = [
        'instock' => 'InStock',
        'in_stock' => 'InStock',
        'outofstock' => 'OutOfStock',
        'out_of_stock' => 'OutOfStock',
        'preorder' => 'PreOrder',
        'pre_order' => 'PreOrder',
        'backorder' => 'BackOrder',
        'back_order' => 'BackOrder',
        'discontinued' => 'Discontinued',
        'soldout' => 'SoldOut',
        'sold_out' => 'SoldOut',
        'limitedavailability' => 'LimitedAvailability',
        'instoreonly' => 'InStoreOnly',
    ];

    private const CONDITION = [
        'new' => 'NewCondition',
        'used' => 'UsedCondition',
        'refurbished' => 'RefurbishedCondition',
        'damaged' => 'DamagedCondition',
    ];

    /**
     * A price in major units, as a dot-decimal string with two places.
     *
     * Takes the comma decimal a Dutch editor types as readily as a float, but
     * refuses anything it cannot read as a number — "op aanvraag" is not a
     * price, and emitting it as one invalidates the offer.
     */
    public static function price(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            // Thousands separators and currency symbols are noise; the last
            // comma or dot is the decimal point.
            $cleaned = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');

            if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }

            $value = $cleaned;
        }

        if (! is_numeric($value)) {
            return self::reject('price', $value);
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * The unambiguous companion for an API that speaks cents.
     *
     * Worth its own method: price(1995) and priceFromMinorUnits(1995) differ by
     * a factor of a hundred, and only one of them is what a cents-based
     * storefront means.
     */
    public static function priceFromMinorUnits(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }

    /**
     * ISO-8601, keeping the precision the source had.
     *
     * A date field holding '2026-09-01' should stay a date; inventing midnight
     * in the server's timezone would be a claim the content never made.
     */
    public static function date(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toAtomString();
        }

        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1;

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return self::reject('date', $value);
        }

        return $dateOnly ? $parsed->toDateString() : $parsed->toAtomString();
    }

    /** Y-m-d only. priceValidUntil is rejected by Google when it carries a time. */
    public static function day(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return self::reject('date', $value);
        }
    }

    /** Absolute, because a relative URL in JSON-LD resolves against nothing. */
    public static function url(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        // Protocol-relative is still absolute, just underspecified.
        if (str_starts_with($value, '//')) {
            return (request()->isSecure() ? 'https:' : 'http:').$value;
        }

        return url($value);
    }

    public static function currency(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : self::reject('currency', $value);
    }

    public static function country(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) === 1 ? $value : self::reject('country code', $value);
    }

    /**
     * A GTIN, and the length-specific key that goes with it.
     *
     * The check digit matters: a transposed pair still has the right length and
     * would sail past a format check, then quietly fail to match any product in
     * Google's catalogue.
     *
     * @return array<string, string>|null e.g. ['gtin' => '…', 'gtin13' => '…']
     */
    public static function gtin(?string $value): ?array
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! in_array(strlen($digits), self::GTIN_LENGTHS, true) || ! self::hasValidCheckDigit($digits)) {
            return self::reject('GTIN', $value);
        }

        return ['gtin' => $digits, 'gtin'.strlen($digits) => $digits];
    }

    /** Plain text: no markup, no runs of whitespace, optionally shortened on a word. */
    public static function text(?string $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        if ($text === '') {
            return null;
        }

        if ($max !== null && mb_strlen($text) > $max) {
            $cut = mb_substr($text, 0, $max);
            $lastSpace = mb_strrpos($cut, ' ');
            $text = rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, " \t\n\r\0\x0B.,;:");
        }

        return $text;
    }

    public static function availability(?string $value): ?string
    {
        return self::enum($value, self::AVAILABILITY, 'availability');
    }

    public static function condition(?string $value): ?string
    {
        return self::enum($value, self::CONDITION, 'item condition');
    }

    /**
     * Drop what schema.org reads as noise, all the way down.
     *
     * `false` and `0` survive deliberately — `isAccessibleForFree: false` is a
     * statement, not an absence.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    public static function compact(array $node): array
    {
        $compacted = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $value = self::compact($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $compacted[$key] = $value;
        }

        return array_is_list($node) ? array_values($compacted) : $compacted;
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function enum(?string $value, array $map, string $what): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // A caller handing back what we gave it should get it back unchanged.
        $token = str_replace('https://schema.org/', '', $value);
        $resolved = $map[strtolower(str_replace([' ', '-'], '_', $token))]
            ?? (in_array($token, $map, true) ? $token : null);

        return $resolved === null
            ? self::reject($what, $value)
            : 'https://schema.org/'.$resolved;
    }

    /** The GS1 mod-10 checksum, right-weighted so it works at every valid length. */
    private static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $check = (int) substr($digits, -1);
        $sum = 0;

        foreach (array_reverse(str_split($body)) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $check;
    }

    private static function reject(string $what, mixed $value): null
    {
        if (config('seo-and-geo.schema.strict', false)) {
            throw InvalidSchemaValue::for($what, $value);
        }

        return null;
    }
}
