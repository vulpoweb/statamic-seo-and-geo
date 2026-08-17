<?php

namespace Vulpo\Seo\Support;

/**
 * Maps a logical field to the handles it can be read from.
 *
 * The first handle is the one this addon writes. The rest are handles used by
 * the addons Vulpo SEO replaces, so a site keeps working before (or without)
 * running `php please vulpo:seo:migrate`.
 */
class Fields
{
    /**
     * @var array<string, array<int, string>>
     */
    public const MAP = [
        'title' => ['seo_title', 'alt_seo_meta_title'],
        'description' => ['seo_description', 'alt_seo_meta_description'],
        'canonical' => ['seo_canonical', 'alt_seo_canonical_url'],
        'noindex' => ['seo_noindex', 'alt_seo_noindex'],
        'nofollow' => ['seo_nofollow', 'alt_seo_nofollow'],
        'image' => ['seo_image', 'alt_seo_og_image', 'alt_social_image'],
        'sitemap_exclude' => ['seo_sitemap_exclude', 'alt_sitemap_exclude'],
        'sitemap_priority' => ['seo_sitemap_priority', 'alt_sitemap_priority'],
        'sitemap_changefreq' => ['seo_sitemap_changefreq', 'alt_sitemap_changefreq'],
        'schema_type' => ['seo_schema_type', 'geo_schema_type'],
        'faqs' => ['seo_schema_faqs', 'geo_faqs'],
        'article_headline' => ['seo_schema_article_headline', 'geo_article_headline'],
        'article_description' => ['seo_schema_article_description', 'geo_article_description'],
        'article_author' => ['seo_schema_article_author', 'geo_article_author'],
        'article_published' => ['seo_schema_article_published', 'geo_article_published'],
        'article_image' => ['seo_schema_article_image', 'geo_article_image'],
        'service_name' => ['seo_schema_service_name', 'geo_service_name'],
        'service_description' => ['seo_schema_service_description', 'geo_service_description'],
        'service_area' => ['seo_schema_service_area', 'geo_service_area'],
        'person_name' => ['seo_schema_person_name', 'geo_person_name'],
        'person_job' => ['seo_schema_person_job', 'geo_person_job'],
    ];

    /**
     * The handle this addon writes for the given logical field.
     */
    public static function handle(string $key): string
    {
        return self::MAP[$key][0];
    }

    /**
     * Every handle the given logical field may be read from, best first.
     *
     * @return array<int, string>
     */
    public static function handles(string $key): array
    {
        $handles = self::MAP[$key] ?? [$key];

        return config('seo.legacy_fallbacks', true) ? $handles : [$handles[0]];
    }

    /**
     * Legacy handles that `vulpo:seo:migrate` renames.
     *
     * @return array<string, string> legacy handle => new handle
     */
    public static function legacyRenames(): array
    {
        $renames = [];

        foreach (self::MAP as $handles) {
            foreach (array_slice($handles, 1) as $legacy) {
                $renames[$legacy] = $handles[0];
            }
        }

        return $renames;
    }
}
