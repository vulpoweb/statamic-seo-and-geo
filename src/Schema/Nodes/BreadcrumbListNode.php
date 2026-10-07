<?php

namespace Vulpo\Seo\Schema\Nodes;

use Vulpo\Seo\Schema\SchemaContext;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Schema\Support\Normalize;

final class BreadcrumbListNode implements SchemaNode
{
    /** @var array<int, array{name: string, url: ?string}> */
    private array $crumbs = [];

    private bool $withHome = true;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<int, array{name: string, url?: string|null}|string>  $crumbs
     */
    public static function fromArray(array $crumbs): self
    {
        $node = new self;

        foreach ($crumbs as $crumb) {
            is_string($crumb)
                ? $node->crumb($crumb)
                : $node->crumb($crumb['name'] ?? '', $crumb['url'] ?? null);
        }

        return $node;
    }

    public function crumb(string $name, ?string $url = null): self
    {
        $this->crumbs[] = ['name' => $name, 'url' => $url];

        return $this;
    }

    public function withHome(bool $withHome = true): self
    {
        $this->withHome = $withHome;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        $crumbs = $this->crumbs;
        $home = rtrim($context->base(), '/').'/';

        if ($this->withHome && ($crumbs[0]['url'] ?? null) !== $home) {
            array_unshift($crumbs, ['name' => __('Home'), 'url' => $home]);
        }

        $last = array_key_last($crumbs);
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $name = Normalize::text($crumb['name']);

            // A ListItem with no item is only valid as the last one, so an
            // unlinkable crumb in the middle is dropped rather than emitted.
            $url = $context->absolute($crumb['url'])
                ?? ($index === $last ? $context->canonical() : null);

            if ($name === null || $url === null) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $name,
                'item' => $url,
            ];
        }

        if (count($items) < 2) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $context->breadcrumbId(),
            'itemListElement' => $items,
        ];
    }
}
