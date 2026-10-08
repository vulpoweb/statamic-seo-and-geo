<?php

namespace Vulpo\Seo\Schema;

use Vulpo\Seo\Schema\Support\Normalize;
use Vulpo\Seo\Support\Settings;

/**
 * OfferShippingDetails: what delivery costs and how long it takes.
 *
 * Embedded in an Offer rather than a node of its own. Shipping is one of the
 * two things Google wants before a product qualifies for free listings, the
 * other being a return policy.
 */
final class ShippingDetails
{
    private ?string $rate = null;

    private ?string $currency = null;

    /** @var array<int, string> */
    private array $destinations = [];

    /** @var array{min: int, max: int, unit: string}|null */
    private ?array $handlingTime = null;

    /** @var array{min: int, max: int, unit: string}|null */
    private ?array $transitTime = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Built from the shop settings, which is where a rate that is the same for
     * every product belongs. Null when the shop has not filled them in.
     */
    public static function fromSettings(): ?self
    {
        $rate = Settings::string('shop_shipping_rate');
        $countries = Settings::list('shop_shipping_countries');

        if ($rate === null || $countries === []) {
            return null;
        }

        $details = self::make()
            ->rate($rate)
            ->destination(...array_map('strval', $countries));

        $handlingMin = Settings::get('shop_handling_days_min');
        $handlingMax = Settings::get('shop_handling_days_max');

        if (is_numeric($handlingMin) && is_numeric($handlingMax)) {
            $details->handlingTime((int) $handlingMin, (int) $handlingMax);
        }

        $transitMin = Settings::get('shop_transit_days_min');
        $transitMax = Settings::get('shop_transit_days_max');

        if (is_numeric($transitMin) && is_numeric($transitMax)) {
            $details->transitTime((int) $transitMin, (int) $transitMax);
        }

        return $details;
    }

    public function rate(int|float|string $amount, ?string $currency = null): self
    {
        $this->rate = Normalize::price($amount);
        $this->currency = $currency === null ? null : Normalize::currency($currency);

        return $this;
    }

    public function destination(string ...$countryCodes): self
    {
        foreach ($countryCodes as $code) {
            if ($code = Normalize::country($code)) {
                $this->destinations[] = $code;
            }
        }

        $this->destinations = array_values(array_unique($this->destinations));

        return $this;
    }

    /** Working days before it leaves the warehouse. */
    public function handlingTime(int $min, int $max, string $unit = 'DAY'): self
    {
        $this->handlingTime = ['min' => min($min, $max), 'max' => max($min, $max), 'unit' => $unit];

        return $this;
    }

    /** Working days in the carrier's hands. */
    public function transitTime(int $min, int $max, string $unit = 'DAY'): self
    {
        $this->transitTime = ['min' => min($min, $max), 'max' => max($min, $max), 'unit' => $unit];

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if ($this->rate === null || $this->destinations === []) {
            return null;
        }

        $delivery = Normalize::compact([
            '@type' => 'ShippingDeliveryTime',
            'handlingTime' => $this->quantitativeValue($this->handlingTime),
            'transitTime' => $this->quantitativeValue($this->transitTime),
        ]);

        return Normalize::compact([
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $this->rate,
                'currency' => $this->currency ?? $context->currency(),
            ],
            'shippingDestination' => array_map(
                fn (string $code) => ['@type' => 'DefinedRegion', 'addressCountry' => $code],
                $this->destinations,
            ),
            // Only worth emitting with at least one half of it filled in.
            'deliveryTime' => count($delivery) > 1 ? $delivery : null,
        ]);
    }

    /**
     * @param  array{min: int, max: int, unit: string}|null  $window
     * @return array<string, mixed>|null
     */
    private function quantitativeValue(?array $window): ?array
    {
        return $window === null ? null : [
            '@type' => 'QuantitativeValue',
            'minValue' => $window['min'],
            'maxValue' => $window['max'],
            'unitCode' => $window['unit'],
        ];
    }
}
