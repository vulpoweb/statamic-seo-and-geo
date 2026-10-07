<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

/**
 * One product, several prices.
 *
 * A page selling five sizes of the same towel has five offers and one price
 * range. Advertising only the cheapest -- which is what a single Offer on a
 * multi-variant page does -- is the kind of mismatch Google penalises when the
 * shopper lands on a different number.
 */
final class AggregateOfferNode implements SchemaNode
{
    private ?string $low = null;

    private ?string $high = null;

    private ?int $count = null;

    private ?string $availability = null;

    private ?string $url = null;

    private ?string $currency = null;

    /** @var array<int, OfferNode> */
    private array $offers = [];

    public static function make(?string $currency = null): self
    {
        $node = new self;
        $node->currency = $currency === null ? null : Normalize::currency($currency);

        return $node;
    }

    /**
     * The usual case: hand it the variants and let it work out the range.
     */
    public static function fromOffers(OfferNode ...$offers): self
    {
        $node = new self;
        $node->offers = array_values($offers);

        $prices = array_values(array_filter(
            array_map(fn (OfferNode $offer) => $offer->priceAsFloat(), $node->offers),
            fn (?float $price) => $price !== null,
        ));

        if ($prices !== []) {
            $node->low = Normalize::price(min($prices));
            $node->high = Normalize::price(max($prices));
            $node->count = count($prices);
        }

        // In stock if any variant is. A range where one size is sold out is
        // still a buyable product.
        $availabilities = array_filter(array_map(
            fn (OfferNode $offer) => $offer->availabilityUrl(),
            $node->offers,
        ));

        if (in_array('https://schema.org/InStock', $availabilities, true)) {
            $node->availability = 'https://schema.org/InStock';
        } elseif ($availabilities !== []) {
            $node->availability = reset($availabilities);
        }

        $node->currency = collect($node->offers)->map->currencyCode()->filter()->first();

        return $node;
    }

    public function lowPrice(int|float|string $value): self
    {
        $this->low = Normalize::price($value);

        return $this;
    }

    public function highPrice(int|float|string $value): self
    {
        $this->high = Normalize::price($value);

        return $this;
    }

    public function offerCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function availability(string $value): self
    {
        $this->availability = Normalize::availability($value);

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = Normalize::url($url);

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if ($this->low === null) {
            return null;
        }

        $low = $this->low;
        $high = $this->high ?? $this->low;

        // A caller setting the two by hand can get them the wrong way round.
        if ((float) $high < (float) $low) {
            [$low, $high] = [$high, $low];
        }

        return Normalize::compact([
            '@type' => 'AggregateOffer',
            'lowPrice' => $low,
            'highPrice' => $high,
            'priceCurrency' => $this->currency ?? $context->currency(),
            'offerCount' => $this->count,
            'availability' => $this->availability,
            'url' => $this->url ?? $context->canonical(),
            'offers' => array_values(array_filter(array_map(
                fn (OfferNode $offer) => $offer->toArray($context),
                $this->offers,
            ))),
        ]);
    }
}
