<?php

namespace Vulpo\Seo\Support;

use Illuminate\Support\Collection;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;

class Assets
{
    /**
     * Resolve an absolute URL from whatever an asset field hands us: an
     * augmented Asset, a collection of them, an `container::path` ID, or a
     * plain URL.
     */
    public static function url(mixed $value): ?string
    {
        if ($value instanceof Collection) {
            $value = $value->first();
        }

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value instanceof Asset) {
            return $value->absoluteUrl();
        }

        if (! is_string($value) || ($value = trim($value)) === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_contains($value, '::')) {
            return AssetFacade::find($value)?->absoluteUrl();
        }

        return url($value);
    }

    public static function find(mixed $value): ?Asset
    {
        if ($value instanceof Collection) {
            $value = $value->first();
        }

        if ($value instanceof Asset) {
            return $value;
        }

        return is_string($value) && str_contains($value, '::') ? AssetFacade::find($value) : null;
    }
}
