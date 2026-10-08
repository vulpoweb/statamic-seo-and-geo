<?php

namespace Vulpo\Seo\Schema;

use Statamic\Facades\Site;
use Vulpo\Seo\Schema\Support\Normalize;
use Vulpo\Seo\Support\Settings;

/**
 * What every node needs to know about the page it is being emitted on.
 *
 * The point is that a node never works out a URL or an @id for itself. The
 * canonical has already been through the pagination and trailing-slash rules in
 * Meta, and if a Product derived its own @id from request()->url() the two would
 * disagree the first time either rule fired -- leaving a graph whose nodes point
 * at addresses that are not each other.
 */
final readonly class SchemaContext
{
    public function __construct(
        private string $canonical,
        private ?string $language = null,
    ) {}

    public static function for(string $canonical, ?string $language = null): self
    {
        return new self($canonical, $language ?: (Site::current()->shortLocale() ?: null));
    }

    /** The site root, without a trailing slash. */
    public function base(): string
    {
        return rtrim(url('/'), '/');
    }

    public function canonical(): string
    {
        return $this->canonical;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function currency(): string
    {
        return Normalize::currency(Settings::string('shop_currency')) ?? 'EUR';
    }

    public function organizationId(): string
    {
        return $this->base().'/#organization';
    }

    public function websiteId(): string
    {
        return $this->base().'/#website';
    }

    public function webPageId(): string
    {
        return $this->fragment('webpage');
    }

    public function breadcrumbId(): string
    {
        return $this->fragment('breadcrumb');
    }

    /** An @id on this page, e.g. fragment('product') => https://…/product/x#product */
    public function fragment(string $name): string
    {
        return $this->canonical.'#'.$name;
    }

    public function absolute(?string $url): ?string
    {
        return Normalize::url($url);
    }

    public function strict(): bool
    {
        return (bool) config('seo-and-geo.schema.strict', false);
    }
}
