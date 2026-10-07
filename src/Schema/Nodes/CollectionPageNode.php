<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

/**
 * A listing page, as opposed to a page about one thing.
 *
 * Takes the WebPage's @id so it replaces it rather than sitting beside it --
 * a page cannot be both.
 */
final class CollectionPageNode implements SchemaNode
{
    private ?string $description = null;

    private ?string $url = null;

    private ?ItemListNode $list = null;

    private function __construct(private readonly ?string $name) {}

    public static function make(string $name): self
    {
        return new self(Normalize::text($name));
    }

    public function description(?string $description): self
    {
        $this->description = Normalize::text($description, 5000);

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = Normalize::url($url);

        return $this;
    }

    public function mainEntity(ItemListNode $list): self
    {
        $this->list = $list;

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

        return Normalize::compact([
            '@type' => 'CollectionPage',
            '@id' => $context->webPageId(),
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url ?? $context->canonical(),
            'inLanguage' => $context->language(),
            'isPartOf' => ['@id' => $context->websiteId()],
            'mainEntity' => $this->list?->toArray($context),
        ]);
    }
}
