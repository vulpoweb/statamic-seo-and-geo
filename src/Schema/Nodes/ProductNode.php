<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

final class ProductNode implements SchemaNode
{
    private ?string $id = null;

    private ?string $description = null;

    private ?string $sku = null;

    private ?string $mpn = null;

    /** @var array<string, string>|null */
    private ?array $gtin = null;

    private ?string $brand = null;

    private ?string $brandUrl = null;

    /** @var array<int, string> */
    private array $images = [];

    private ?string $url = null;

    private ?string $condition = null;

    private ?string $category = null;

    private ?string $variantOf = null;

    /** @var array<int, OfferNode|AggregateOfferNode> */
    private array $offers = [];

    /** @var array<string, mixed>|null */
    private ?array $rating = null;

    /** @var array<int, array{name: string, value: string}> */
    private array $properties = [];

    private function __construct(private readonly ?string $name) {}

    public static function make(string $name): self
    {
        return new self(Normalize::text($name));
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = Normalize::text($description, 5000);

        return $this;
    }

    public function sku(?string $sku): self
    {
        $this->sku = $sku === null ? null : (trim($sku) ?: null);

        return $this;
    }

    public function mpn(?string $mpn): self
    {
        $this->mpn = $mpn === null ? null : (trim($mpn) ?: null);

        return $this;
    }

    /** Validated against its check digit; a malformed one is left out entirely. */
    public function gtin(?string $gtin): self
    {
        $this->gtin = Normalize::gtin($gtin);

        return $this;
    }

    public function brand(?string $name, ?string $url = null): self
    {
        $this->brand = Normalize::text($name);
        $this->brandUrl = Normalize::url($url);

        return $this;
    }

    /**
     * @param  string|array<int, string|null>|null  $urls
     */
    public function image(string|array|null $urls): self
    {
        foreach ((array) $urls as $url) {
            if (is_string($url) && $absolute = Normalize::url($url)) {
                $this->images[] = $absolute;
            }
        }

        $this->images = array_values(array_unique($this->images));

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = Normalize::url($url);

        return $this;
    }

    public function condition(string $condition = 'new'): self
    {
        $this->condition = Normalize::condition($condition);

        return $this;
    }

    public function category(?string $category): self
    {
        $this->category = Normalize::text($category);

        return $this;
    }

    public function isVariantOf(string $productGroupId): self
    {
        $this->variantOf = $productGroupId;

        return $this;
    }

    public function offer(OfferNode $offer): self
    {
        $this->offers[] = $offer;

        return $this;
    }

    public function offers(OfferNode|AggregateOfferNode ...$offers): self
    {
        foreach ($offers as $offer) {
            $this->offers[] = $offer;
        }

        return $this;
    }

    /**
     * Only call this with a rating a customer actually left. Google treats an
     * invented one as spam, and there is no version of this that is worth it.
     */
    public function aggregateRating(float $value, int $count, float $best = 5.0): self
    {
        if ($count < 1) {
            return $this;
        }

        $this->rating = [
            '@type' => 'AggregateRating',
            'ratingValue' => round($value, 2),
            'reviewCount' => $count,
            'bestRating' => $best,
        ];

        return $this;
    }

    public function additionalProperty(string $name, string $value): self
    {
        $this->properties[] = ['name' => $name, 'value' => $value];

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if ($this->name === null) {
            return null;
        }

        $offers = array_values(array_filter(array_map(
            fn (OfferNode|AggregateOfferNode $offer) => $offer->toArray($context),
            $this->offers,
        )));

        return Normalize::compact(array_merge([
            '@type' => 'Product',
            '@id' => $this->id ?? $context->fragment('product'),
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'mpn' => $this->mpn,
        ], $this->gtin ?? [], [
            'image' => $this->images,
            'url' => $this->url ?? $context->canonical(),
            'category' => $this->category,
            'itemCondition' => $this->condition,
            'brand' => $this->brand === null ? null : Normalize::compact([
                '@type' => 'Brand',
                'name' => $this->brand,
                'url' => $this->brandUrl,
            ]),
            'isVariantOf' => $this->variantOf === null ? null : ['@id' => $this->variantOf],
            'aggregateRating' => $this->rating,
            'additionalProperty' => array_map(fn (array $p) => [
                '@type' => 'PropertyValue',
                'name' => $p['name'],
                'value' => $p['value'],
            ], $this->properties),
            // One offer is emitted bare; several are a list.
            'offers' => count($offers) === 1 ? $offers[0] : $offers,
        ]));
    }
}
