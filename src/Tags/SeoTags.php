<?php

namespace Vulpo\Seo\Tags;

use Statamic\Tags\Tags;
use Vulpo\Seo\Seo\Meta;
use Vulpo\Seo\Seo\Schema;

/**
 * Template tags:
 *
 *  {{ vulpo_seo }}              meta tags and JSON-LD, everything for the <head>
 *  {{ vulpo_seo:meta }}         meta tags only
 *  {{ vulpo_seo:schema }}       JSON-LD only
 *  {{ vulpo_seo:title }}        the resolved page title, as text
 *  {{ vulpo_seo:description }}  the resolved page description, as text
 *  {{ vulpo_seo:image }}        the resolved social image URL
 */
class SeoTags extends Tags
{
    protected static $handle = 'vulpo_seo';

    public function index(): string
    {
        return implode("\n", array_filter([$this->meta(), $this->schema()]));
    }

    public function meta(): string
    {
        return Meta::forContext($this->context)->render();
    }

    public function schema(): string
    {
        return Schema::forContext($this->context)->render();
    }

    public function title(): string
    {
        return Meta::forContext($this->context)->title();
    }

    public function description(): ?string
    {
        return Meta::forContext($this->context)->description();
    }

    public function image(): ?string
    {
        return Meta::forContext($this->context)->imageUrl();
    }
}
