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

];
