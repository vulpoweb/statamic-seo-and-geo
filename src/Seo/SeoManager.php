<?php

namespace Vulpo\Seo\Seo;

use Vulpo\Seo\Contracts\ProvidesSeo;
use Vulpo\Seo\Schema\SchemaNode;
use Vulpo\Seo\Support\ProviderRegistry;

/**
 * What a route says about itself.
 *
 * The addon reads a page's SEO off the entry behind it. A product served from
 * an API has no entry, and the workaround -- branching away from {{ vulpo_seo }}
 * in the layout and hand-writing the head -- costs that page the Organization,
 * the WebSite, the Twitter card and the verification tags, which is to say it
 * costs the most valuable page type on a shop the most complete head on the
 * site.
 *
 * So a controller says what an entry would have said, and the layout keeps
 * calling {{ vulpo_seo }} unconditionally:
 *
 *     Seo::override([
 *         'title' => $product->name,
 *         'canonical' => route('shop.product', $product->slug),
 *         'image' => $product->cover,
 *     ])->schema(ProductNode::make($product->name)->…);
 */
class SeoManager
{
    public function __construct(
        private readonly SeoOverlay $overlay,
        private readonly ProviderRegistry $sitemapProviders,
        private readonly ProviderRegistry $llmsProviders,
    ) {}

    /**
     * Values that beat the page's own fields.
     *
     * @param  array<string, mixed>  $values
     */
    public function override(array $values): self
    {
        $this->overlay->override($values);

        return $this;
    }

    /**
     * Values that lose to the page's own fields but beat the site settings.
     *
     * @param  array<string, mixed>  $values
     */
    public function defaults(array $values): self
    {
        $this->overlay->defaults($values);

        return $this;
    }

    /**
     * @param  ProvidesSeo|array<string, mixed>  $source
     */
    public function for(ProvidesSeo|array $source): self
    {
        return $this->override($source instanceof ProvidesSeo ? $source->toSeoArray() : $source);
    }

    /**
     * @param  SchemaNode|array<string, mixed>  ...$nodes
     */
    public function schema(SchemaNode|array ...$nodes): self
    {
        $this->overlay->schema(...$nodes);

        return $this;
    }

    /**
     * Drop the nodes the page's own fields would have produced: all of them, or
     * one @type.
     */
    public function withoutSchema(?string $type = null): self
    {
        $this->overlay->withoutSchema($type);

        return $this;
    }

    /**
     * The trail for this page, innermost last. The final crumb needs no url --
     * it is the page you are on.
     *
     * @param  array<int, array{name: string, url?: string|null}|string>  $crumbs
     */
    public function breadcrumbs(array $crumbs): self
    {
        $this->overlay->breadcrumbs($crumbs);

        return $this;
    }

    public function noindex(bool $nofollow = false): self
    {
        return $this->override(array_filter([
            'noindex' => true,
            'nofollow' => $nofollow ?: null,
        ]));
    }

    /**
     * @param  array<string, string>  $hreflangToUrl
     */
    public function alternates(array $hreflangToUrl): self
    {
        $this->overlay->alternates($hreflangToUrl);

        return $this;
    }

    public function overlay(): SeoOverlay
    {
        return $this->overlay;
    }

    public function sitemap(): ProviderRegistry
    {
        return $this->sitemapProviders;
    }

    public function llms(): ProviderRegistry
    {
        return $this->llmsProviders;
    }

    public function flush(): void
    {
        $this->overlay->flush();
    }
}
