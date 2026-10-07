<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

/**
 * What is on a listing page, in the order it appears.
 *
 * Positions are global rather than per page, so page two starts at 25. A
 * crawler reading both pages otherwise sees two things claiming position 1.
 */
final class ItemListNode implements SchemaNode
{
    private ?string $id = null;

    private ?string $name = null;

    private ?int $total = null;

    private int $start = 1;

    /** @var array<int, array{url: string, name: ?string, image: ?string}> */
    private array $items = [];

    public static function make(): self
    {
        return new self;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function name(?string $name): self
    {
        $this->name = Normalize::text($name);

        return $this;
    }

    public function item(string $url, ?string $name = null, ?string $image = null): self
    {
        if (! $absolute = Normalize::url($url)) {
            return $this;
        }

        $this->items[] = [
            'url' => $absolute,
            'name' => Normalize::text($name),
            'image' => Normalize::url($image),
        ];

        return $this;
    }

    /**
     * @param  iterable<int, array{url?: string, name?: string, image?: string}>  $items
     */
    public function items(iterable $items): self
    {
        foreach ($items as $item) {
            if (is_array($item) && isset($item['url'])) {
                $this->item((string) $item['url'], $item['name'] ?? null, $item['image'] ?? null);
            }
        }

        return $this;
    }

    /** The unpaginated total, when it differs from what is on this page. */
    public function numberOfItems(?int $total): self
    {
        $this->total = $total;

        return $this;
    }

    /** Page two of a 24-per-page listing starts at 25. */
    public function startPosition(int $position): self
    {
        $this->start = max(1, $position);

        return $this;
    }

    /**
     * Convenience for the common case: page number and page size rather than
     * an offset worked out at the call site.
     */
    public function page(int $page, int $perPage): self
    {
        return $this->startPosition((max(1, $page) - 1) * $perPage + 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if ($this->items === []) {
            return null;
        }

        $elements = [];

        foreach ($this->items as $offset => $item) {
            $elements[] = Normalize::compact([
                '@type' => 'ListItem',
                'position' => $this->start + $offset,
                'url' => $item['url'],
                'name' => $item['name'],
                'image' => $item['image'],
            ]);
        }

        return Normalize::compact([
            '@type' => 'ItemList',
            '@id' => $this->id ?? $context->fragment('list'),
            'name' => $this->name,
            'numberOfItems' => $this->total ?? count($elements),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $elements,
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
