<?php

namespace Vulpo\Seo\Schema\Nodes;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Vulpo\Seo\Schema\ReturnPolicy;
use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\ShippingDetails;
use Vulpo\Seo\Schema\Support\Normalize;
use Vulpo\Seo\Support\Settings;

/**
 * One price for one thing.
 *
 * Shipping and returns default to the shop settings rather than being asked for
 * per offer, because they almost never differ per product and leaving them out
 * is what keeps a product out of Google's free listings.
 */
final class OfferNode implements SchemaNode
{
    private ?string $price;

    private ?string $currency = null;

    private ?string $availability = null;

    private ?string $url = null;

    private ?string $sku = null;

    private ?string $condition = null;

    private ?string $priceValidUntil = null;

    private bool $priceValidUntilSet = false;

    private ShippingDetails|false|null $shipping = null;

    private ReturnPolicy|false|null $returnPolicy = null;

    private string|false|null $seller = null;

    private function __construct(?string $price)
    {
        $this->price = $price;
    }

    public static function make(int|float|string $price, ?string $currency = null): self
    {
        $offer = new self(Normalize::price($price));
        $offer->currency = $currency === null ? null : Normalize::currency($currency);

        return $offer;
    }

    /** For an API that speaks cents, where make(1995) would be off by a hundred. */
    public static function fromMinorUnits(int $cents, ?string $currency = null): self
    {
        $offer = new self(Normalize::priceFromMinorUnits($cents));
        $offer->currency = $currency === null ? null : Normalize::currency($currency);

        return $offer;
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

    public function sku(?string $sku): self
    {
        $this->sku = $sku === null ? null : (trim($sku) ?: null);

        return $this;
    }

    public function itemCondition(string $condition): self
    {
        $this->condition = Normalize::condition($condition);

        return $this;
    }

    /** Pass null to say outright that this offer has no end date. */
    public function priceValidUntil(DateTimeInterface|string|null $value): self
    {
        $this->priceValidUntil = Normalize::day($value);
        $this->priceValidUntilSet = true;

        return $this;
    }

    /** Pass false for an offer that genuinely is not shipped -- a service, a download. */
    public function shipping(ShippingDetails|false|null $shipping): self
    {
        $this->shipping = $shipping;

        return $this;
    }

    public function returnPolicy(ReturnPolicy|false|null $policy): self
    {
        $this->returnPolicy = $policy;

        return $this;
    }

    /** Null names the site's own organisation; false leaves the seller out. */
    public function seller(string|false|null $name = null): self
    {
        $this->seller = $name;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        // An offer with no price is not an offer.
        if ($this->price === null) {
            return null;
        }

        return Normalize::compact([
            '@type' => 'Offer',
            'price' => $this->price,
            'priceCurrency' => $this->currency ?? $context->currency(),
            'availability' => $this->availability,
            'itemCondition' => $this->condition,
            'url' => $this->url ?? $context->canonical(),
            'sku' => $this->sku,
            'priceValidUntil' => $this->resolvePriceValidUntil(),
            'seller' => $this->resolveSeller($context),
            'shippingDetails' => $this->resolveShipping($context),
            'hasMerchantReturnPolicy' => $this->resolveReturnPolicy($context),
        ]);
    }

    /**
     * Google warns about an offer with no end date, so one is supplied from the
     * shop settings unless the caller said otherwise -- including saying null,
     * which is a deliberate "this price has no end".
     */
    private function resolvePriceValidUntil(): ?string
    {
        if ($this->priceValidUntilSet) {
            return $this->priceValidUntil;
        }

        $days = Settings::get('shop_price_valid_days');

        return is_numeric($days) && (int) $days > 0
            ? Carbon::now()->addDays((int) $days)->toDateString()
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSeller(SchemaContext $context): ?array
    {
        if ($this->seller === false) {
            return null;
        }

        return $this->seller === null
            ? ['@id' => $context->organizationId()]
            : ['@type' => 'Organization', 'name' => $this->seller];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveShipping(SchemaContext $context): ?array
    {
        if ($this->shipping === false) {
            return null;
        }

        return ($this->shipping ?? ShippingDetails::fromSettings())?->toArray($context);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveReturnPolicy(SchemaContext $context): ?array
    {
        if ($this->returnPolicy === false) {
            return null;
        }

        return ($this->returnPolicy ?? ReturnPolicy::fromSettings())?->toArray($context);
    }

    public function priceAsFloat(): ?float
    {
        return $this->price === null ? null : (float) $this->price;
    }

    public function currencyCode(): ?string
    {
        return $this->currency;
    }

    public function availabilityUrl(): ?string
    {
        return $this->availability;
    }
}
