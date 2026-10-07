<?php

namespace Vulpo\Seo\Seo;

use InvalidArgumentException;
use Vulpo\Seo\Schema\SchemaNode;

/**
 * What a route has said about the page it is rendering.
 *
 * Request-scoped and deliberately dumb: SeoManager is the thing people call,
 * ValueReader and Schema are the things that read it.
 */
class SeoOverlay
{
    /**
     * The logical fields a route may speak for. Everything here maps onto a
     * field an entry could have set, which is what lets the two merge.
     */
    public const KEYS = [
        'title',
        'description',
        'canonical',
        'image',
        'noindex',
        'nofollow',
        'og_type',
        'article_author',
        'article_published',
        'locale',
        'sitemap_exclude',
    ];

    /** @var array<string, mixed> */
    private array $overrides = [];

    /** @var array<string, mixed> */
    private array $defaults = [];

    /** @var array<int, SchemaNode|array<string, mixed>> */
    private array $nodes = [];

    /** @var array<int, array{name: string, url?: string|null}>|null */
    private ?array $breadcrumbs = null;

    /** @var array<string, string> */
    private array $alternates = [];

    /** @var array<int, string> */
    private array $removedTypes = [];

    private bool $removeAllSchema = false;

    /**
     * @param  array<string, mixed>  $values
     */
    public function override(array $values): void
    {
        $this->overrides = array_merge($this->overrides, $this->validate($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function defaults(array $values): void
    {
        $this->defaults = array_merge($this->defaults, $this->validate($values));
    }

    /**
     * @param  SchemaNode|array<string, mixed>  ...$nodes
     */
    public function schema(SchemaNode|array ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->nodes[] = $node;
        }
    }

    public function withoutSchema(?string $type = null): void
    {
        if ($type === null) {
            $this->removeAllSchema = true;

            return;
        }

        $this->removedTypes[] = $type;
    }

    /**
     * @param  array<int, array{name: string, url?: string|null}|string>  $crumbs
     */
    public function breadcrumbs(array $crumbs): void
    {
        $this->breadcrumbs = array_values(array_map(
            fn (array|string $crumb) => is_string($crumb) ? ['name' => $crumb] : $crumb,
            $crumbs,
        ));
    }

    /**
     * @param  array<string, string>  $hreflangToUrl
     */
    public function alternates(array $hreflangToUrl): void
    {
        $this->alternates = array_merge($this->alternates, $hreflangToUrl);
    }

    /** @return array<string, mixed> */
    public function overrides(): array
    {
        return $this->overrides;
    }

    /** @return array<string, mixed> */
    public function defaultValues(): array
    {
        return $this->defaults;
    }

    /** @return array<int, SchemaNode|array<string, mixed>> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /** @return array<int, array{name: string, url?: string|null}>|null */
    public function breadcrumbTrail(): ?array
    {
        return $this->breadcrumbs;
    }

    /** @return array<string, string> */
    public function alternateUrls(): array
    {
        return $this->alternates;
    }

    public function removesAllSchema(): bool
    {
        return $this->removeAllSchema;
    }

    /** @return array<int, string> */
    public function removedTypes(): array
    {
        return $this->removedTypes;
    }

    public function isEmpty(): bool
    {
        return $this->overrides === []
            && $this->defaults === []
            && $this->nodes === []
            && $this->breadcrumbs === null
            && $this->alternates === []
            && $this->removedTypes === []
            && ! $this->removeAllSchema;
    }

    public function flush(): void
    {
        $this->overrides = [];
        $this->defaults = [];
        $this->nodes = [];
        $this->breadcrumbs = null;
        $this->alternates = [];
        $this->removedTypes = [];
        $this->removeAllSchema = false;
    }

    /**
     * Loudly, on purpose.
     *
     * This is a developer API with a fixed set of keys, called from a
     * controller. A typo should stop the first request in development, not
     * disappear into a page that quietly stops saying what it meant to.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function validate(array $values): array
    {
        if ($unknown = array_diff(array_keys($values), self::KEYS)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown SEO %s %s. Valid keys are: %s.',
                count($unknown) === 1 ? 'key' : 'keys',
                implode(', ', array_map(fn (string $k) => "[{$k}]", $unknown)),
                implode(', ', self::KEYS),
            ));
        }

        return $values;
    }
}
