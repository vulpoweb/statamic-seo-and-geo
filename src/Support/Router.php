<?php

namespace Vulpo\Seo\Support;

/**
 * Questions about whether a piece of content is a real, indexable page.
 */
class Router
{
    /**
     * Whether the content has no page of its own: Statamic's `redirect` field
     * turns an entry into either a link elsewhere or a hard 404, and neither
     * belongs in a sitemap or an llms.txt.
     *
     * Note that `absoluteUrl()` on such an entry returns the *destination*, so
     * without this check the listing would advertise another page's URL.
     */
    public static function isNotAPage(mixed $data): bool
    {
        if (! is_object($data) || ! method_exists($data, 'redirectUrl')) {
            return false;
        }

        return (bool) $data->redirectUrl();
    }
}
