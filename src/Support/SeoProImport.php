<?php

namespace Vulpo\Seo\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Translates statamic/seo-pro data into this addon's handles.
 *
 * SEO Pro keeps every per-page value in a single `seo` array on the entry, and
 * its site defaults in `resources/addons/seo-pro.yaml` (older versions used
 * `content/seo.yaml`). This class only converts values; writing them is the
 * migrate command's job.
 */
class SeoProImport
{
    /**
     * Where SEO Pro keeps its site defaults, newest location first.
     *
     * @var array<int, string>
     */
    public const DEFAULTS_PATHS = [
        'resources/addons/seo-pro.yaml',
        'content/seo.yaml',
        'content/seo-pro/site_defaults.yaml',
    ];

    /**
     * The per-page values, keyed by this addon's field handles.
     *
     * @param  array<string, mixed>  $seo  the entry's `seo` array
     * @return array<string, mixed>
     */
    public function fields(array $seo): array
    {
        $robots = $this->robots($seo);

        // `enabled: false` hides a page from the sitemap and stops SEO Pro
        // rendering any tags for it, which is closest to noindex + excluded.
        $disabled = array_key_exists('enabled', $seo) && $this->isFalse($seo['enabled']);

        return array_filter([
            Fields::handle('title') => $this->text($seo['title'] ?? null),
            Fields::handle('description') => $this->text($seo['description'] ?? null),
            Fields::handle('canonical') => $this->text($seo['canonical_url'] ?? null),
            Fields::handle('image') => $seo['image'] ?? null,
            Fields::handle('noindex') => in_array('noindex', $robots, true) || $disabled ?: null,
            Fields::handle('nofollow') => in_array('nofollow', $robots, true) ?: null,
            Fields::handle('sitemap_exclude') => $this->excludedFromSitemap($seo) || $disabled ?: null,
            Fields::handle('sitemap_priority') => $this->text($seo['priority'] ?? null),
            Fields::handle('sitemap_changefreq') => $this->text($seo['change_frequency'] ?? null),
            Fields::handle('custom_schema') => $this->schema($seo['json_ld_schema'] ?? null),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * The site defaults, keyed by this addon's setting handles.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function settings(array $defaults): array
    {
        $robots = $this->robots($defaults);

        return array_filter([
            'site_name' => $this->text($defaults['site_name'] ?? null),
            'default_description' => $this->text($defaults['description'] ?? null),
            'default_image' => $defaults['image'] ?? null,
            'twitter_handle' => $this->text($defaults['twitter_handle'] ?? null),
            'verify_google' => $this->text($defaults['google_verification'] ?? null),
            'verify_bing' => $this->text($defaults['bing_verification'] ?? null),
            'business_name' => $this->text($defaults['json_ld_organization_name'] ?? $defaults['json_ld_person_name'] ?? null),
            'business_type' => ($defaults['json_ld_entity'] ?? null) === 'person' ? 'Person' : null,
            'logo' => $defaults['json_ld_organization_logo'] ?? null,
            'schema_breadcrumbs' => array_key_exists('json_ld_breadcrumbs', $defaults)
                ? (bool) $defaults['json_ld_breadcrumbs']
                : null,
            'sitemap_priority' => $this->text($defaults['priority'] ?? null),
            'sitemap_changefreq' => $this->text($defaults['change_frequency'] ?? null),
            'noindex_site' => in_array('noindex', $robots, true) ?: null,
            'title_separator' => $this->text($defaults['site_name_separator'] ?? null),
            'append_site_name' => array_key_exists('site_name_position', $defaults)
                ? $defaults['site_name_position'] !== 'before'
                : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * SEO Pro's robots directives, from either the current pair of selects or
     * the older single `robots` list.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function robots(array $data): array
    {
        $robots = Arr::wrap($data['robots'] ?? []);

        foreach (['robots_indexing', 'robots_following'] as $key) {
            if ($value = $data[$key] ?? null) {
                $robots[] = $value;
            }
        }

        return array_values(array_filter($robots, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function excludedFromSitemap(array $seo): bool
    {
        return array_key_exists('sitemap', $seo) && $this->isFalse($seo['sitemap']);
    }

    /**
     * SEO Pro lets a value point at another field with `@seo:handle`, or contain
     * Antlers. Neither travels, and both would end up rendered literally in a
     * meta tag, so they are dropped in favour of this addon's own fallbacks.
     */
    private function text(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, '@seo:') || Str::contains($value, '{{')) {
            return null;
        }

        return $value;
    }

    /**
     * @return string|null valid JSON only, since ours is a plain JSON-LD field
     */
    private function schema(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '' || Str::contains($value, '{{')) {
            return null;
        }

        return json_decode($value, true) === null ? null : $value;
    }

    private function isFalse(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) === false;
    }
}
