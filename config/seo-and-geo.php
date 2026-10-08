<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Where the addon keeps the data it owns: redirects, the 404 log, the AI
    | crawler log and the URL index. Entry and term fields are stored by Statamic
    | itself, and the control panel settings by Statamic's addon settings
    | repository, so both already follow whatever driver the site uses.
    |
    | "auto" follows statamic/eloquent-driver: a site keeping its content in the
    | database gets database tables here too. Set "file" or "eloquent" to decide
    | for yourself.
    |
    */

    'storage' => [
        'driver' => env('VULPO_SEO_STORAGE_DRIVER', 'auto'),

        'tables' => [
            'redirects' => 'vulpo_seo_redirects',
            'not_found' => 'vulpo_seo_not_found',
            'ai_crawlers' => 'vulpo_seo_ai_crawlers',
            'uris' => 'vulpo_seo_uris',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field injection
    |--------------------------------------------------------------------------
    |
    | The addon injects an "SEO" and a "Structured data" tab into blueprints at
    | runtime, so no blueprint editing is required. Disable a target here if you
    | prefer to place the fields yourself, or exclude specific collections and
    | taxonomies by handle.
    |
    */

    'fields' => [
        'entries' => true,
        'terms' => true,
        'exclude_collections' => [],
        'exclude_taxonomies' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | Serves an XML sitemap. Which collections and taxonomies are included is
    | controlled from the control panel (SEO → Settings); this only decides
    | whether the route exists and how long the result is cached.
    |
    */

    'sitemap' => [
        'enabled' => true,
        'route' => 'sitemap.xml',
        'cache_minutes' => 60,
        // Maximum number of URLs in a single sitemap file. Google's limit is 50.000.
        'max_urls' => 5000,
        // Entry fields to pull <image:image> entries from. Empty to omit them.
        'image_fields' => ['seo_image'],
        /*
         * How a registered provider behaves when its source is unreachable.
         *
         * A sitemap that shrinks is worse than one that is stale: the missing
         * URLs read as "these pages are gone". So the last list a provider
         * built successfully is kept well past its normal cache, and a build
         * that had to fall back is only cached for retry_minutes, so the
         * sitemap repairs itself minutes after the source comes back rather
         * than at the end of the hour.
         */
        'provider_fallback_hours' => 24,
        'retry_minutes' => 5,
        // A ceiling, so a runaway source cannot exhaust memory mid-request.
        'max_provider_urls' => 50000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Redirects are stored in a YAML file so they can be committed with the rest
    | of your content. 404s are logged separately, outside of content, because
    | they are throwaway data.
    |
    */

    'redirects' => [
        'enabled' => true,
        'path' => 'content/vulpo-seo/redirects.yaml',
        // Create a redirect automatically when an entry's slug changes.
        'auto_create_on_slug_change' => true,
        // Keep track of URLs that returned a 404 so redirects can be created from them.
        'log_not_found' => true,
        'not_found_log_path' => 'vulpo-seo/not-found.yaml',
        'not_found_log_max' => 500,
        // Where the addon remembers each entry's URL, to spot changes after a save.
        'uri_ledger_path' => 'vulpo-seo/uris.yaml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical URLs
    |--------------------------------------------------------------------------
    |
    | A page's canonical URL is its own address without the query string, so
    | tracking parameters never split a page in two. Pagination is the exception:
    | page 2 should point at itself, not at page 1, or its content looks like a
    | duplicate of the first page and drops out of the index.
    |
    | "trailing_slash" can force a trailing slash on or off, so a site served
    | both ways still advertises one address. Null leaves the URL as it is.
    |
    */

    'canonical' => [
        'pagination_query' => 'page',
        'trailing_slash' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    |
    | When enabled the addon serves /robots.txt from the control panel settings.
    | A physical public/robots.txt file always wins, because the web server
    | serves it before the request ever reaches Laravel.
    |
    */

    'robots' => [
        'enabled' => true,
        'route' => 'robots.txt',
        'cache_minutes' => 60,
        /*
         * Paths to mark noindex, as request()->is() patterns. The code-level
         * complement to the same setting in the control panel; the two are
         * unioned, so a developer and an editor cannot overwrite each other.
         *
         * Note this is noindex, not Disallow. Disallowing a URL stops a crawler
         * reading the noindex on it, which leaves it in the index as a bare
         * address forever.
         */
        'noindex_paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | llms.txt
    |--------------------------------------------------------------------------
    |
    | Serves an /llms.txt describing the site for AI assistants, built from your
    | pages and their SEO descriptions.
    |
    */

    'llms' => [
        'enabled' => true,
        'route' => 'llms.txt',
        'cache_minutes' => 60,
        'max_urls' => 200,
        /*
         * llms.txt is a map, not an inventory: it is meant to be read whole, so
         * it stays short and curated. llms-full.txt is the unabridged companion
         * for anything a provider wants to list in full -- a product catalogue,
         * typically -- and is capped separately.
         */
        'full_enabled' => true,
        'full_route' => 'llms-full.txt',
        'full_max_urls' => 20000,
        // Per heading, so one large provider cannot crowd out the pages.
        'max_per_group' => 50,
        'provider_fallback_hours' => 24,
        'retry_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI crawler log
    |--------------------------------------------------------------------------
    |
    | Counts visits from known AI crawlers so you can see who is reading the
    | site. Matching happens on the user agent; add your own patterns freely.
    |
    */

    'ai_crawlers' => [
        'enabled' => true,
        'log_path' => 'vulpo-seo/ai-crawlers.yaml',
        'retention_days' => 30,
        'agents' => [
            'ChatGPT' => 'ChatGPT-User',
            'OpenAI (training)' => 'GPTBot',
            'OpenAI (search)' => 'OAI-SearchBot',
            'Claude' => 'ClaudeBot',
            'Claude (user)' => 'Claude-User',
            'Perplexity' => 'PerplexityBot',
            'Google Extended' => 'Google-Extended',
            'Gemini' => 'Google-CloudVertexBot',
            'Applebot Extended' => 'Applebot-Extended',
            'Bytespider' => 'Bytespider',
            'Amazon' => 'Amazonbot',
            'Meta' => 'meta-externalagent',
            'Mistral' => 'MistralAI-User',
            'You.com' => 'YouBot',
            'Common Crawl' => 'CCBot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocoder
    |--------------------------------------------------------------------------
    |
    | Turns the address entered in the control panel into coordinates for local
    | business structured data, using the free OpenStreetMap Nominatim service.
    | No API key is needed. Results are cached to respect their usage policy,
    | which also requires a descriptive User-Agent.
    |
    */

    'geocoder' => [
        'enabled' => true,
        'endpoint' => env('VULPO_SEO_GEOCODER_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('VULPO_SEO_GEOCODER_USER_AGENT'),
        'timeout' => 5,
        'cache_days' => 30,
        'cache_days_failed' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy field fallbacks
    |--------------------------------------------------------------------------
    |
    | Read data written by other SEO addons when a Vulpo SEO field is empty, so
    | a site keeps its meta tags before `php please vulpo:seo:migrate` is run.
    |
    */

    'legacy_fallbacks' => true,

    /*
    |--------------------------------------------------------------------------
    | Structured data
    |--------------------------------------------------------------------------
    |
    | Every node is emitted in one @graph so they can reference each other by
    | @id -- the WebPage points at the WebSite, the Product at the Organization
    | selling it. Turn `graph` off to go back to one <script> per node.
    |
    | `strict` turns a value schema.org would reject into an exception instead
    | of a dropped key. Keep it on locally and in your tests, off in production:
    | a malformed price should fail a build, not a product page.
    |
    */

    'schema' => [
        'graph' => true,
        'webpage' => true,
        'strict' => env('VULPO_SEO_SCHEMA_STRICT', false),
    ],

];
