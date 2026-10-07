<?php

namespace Vulpo\Seo\Facades;

use Illuminate\Support\Facades\Facade;
use Vulpo\Seo\Seo\SeoManager;

/**
 * @method static SeoManager override(array $values)
 * @method static SeoManager defaults(array $values)
 * @method static SeoManager for(\Vulpo\Seo\Contracts\ProvidesSeo|array $source)
 * @method static SeoManager schema(\Vulpo\Seo\Schema\SchemaNode|array ...$nodes)
 * @method static SeoManager withoutSchema(?string $type = null)
 * @method static SeoManager breadcrumbs(array $crumbs)
 * @method static SeoManager noindex(bool $nofollow = false)
 * @method static SeoManager alternates(array $hreflangToUrl)
 * @method static \Vulpo\Seo\Seo\SeoOverlay overlay()
 * @method static \Vulpo\Seo\Support\ProviderRegistry sitemap()
 * @method static \Vulpo\Seo\Support\ProviderRegistry llms()
 * @method static void flush()
 *
 * @see SeoManager
 */
class Seo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SeoManager::class;
    }
}
