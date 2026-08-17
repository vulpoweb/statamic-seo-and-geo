# Vulpo SEO

One addon for everything a Statamic site needs to be found: meta tags, an XML sitemap, redirects, structured data, `robots.txt` and `llms.txt`.

It replaces the stack many Statamic sites run today — `alt-design/alt-seo`, `alt-design/alt-sitemap`, `alt-design/alt-redirects` and `vulpo/geo` — with a single set of settings, one CP section, and one template tag. Data from those addons is read as a fallback, and a migration command moves it over.

## Features

**Live previews** — a read-only field at the top of every SEO tab shows the Google result and the social card as the editor types, truncated by measured pixel width the way Google actually cuts it, with warnings for a missing description, a title that will be cut off, or a page hidden from search.

**Meta tags** — title, description, canonical, robots, Open Graph, Twitter cards, hreflang and search console verification from one tag. Per-page fields override site-wide defaults, and each site can override the defaults again.

**Sitemap** — `/sitemap.xml`, built from published, routable entries (and optionally taxonomy terms), with per-page "leave out of the sitemap" and collection exclusions. Cached, and flushed when content changes.

**Redirects** — a CP screen with exact, wildcard and regex rules (301, 302, 410). Redirects are checked only when a URL would otherwise 404, so normal page loads pay nothing. When a page's URL changes — renamed slug, moved in the structure, changed date — a redirect is created automatically, plus a wildcard rule for its children.

**404 log** — every miss is logged with hit counts and referrer, and can be turned into a redirect in one click.

**Structured data (JSON-LD)** — Organization or LocalBusiness, WebSite, BreadcrumbList, and a per-page FAQ, Article, Service or Person node. Coordinates are geocoded from the address for free through OpenStreetMap and cached.

**llms.txt** — `/llms.txt` describing the site and its pages for AI assistants, with an editable summary.

**robots.txt** — served from the CP when there is no `public/robots.txt`, with an AI crawler policy (allow all, block all, or pick).

**AI crawler log** — see which assistants (ChatGPT, Claude, Perplexity, Gemini, …) actually read the site.

**Dashboard widgets** — recent 404s and AI crawler activity, so problems surface without going looking.

## Installation

```bash
composer require vulpo/seo
php please vulpo:seo:index-uris
```

Working on the addon from a local checkout instead? Point a path repository at it and require `"vulpo/seo": "@dev"`:

```json
"repositories": [
    { "type": "path", "url": "../vulpo-seo" }
]
```

Then add the tag to your layout's `<head>`:

```antlers
{{ vulpo_seo }}
```

`vulpo:seo:index-uris` records where every page currently lives. Automatic redirects compare against that index, so run it once after installing. It maintains itself afterwards.

Settings live in the control panel under **Tools → SEO**. The SEO and Structured data tabs are added to every entry and term blueprint automatically.

To put the widgets on the dashboard, add them in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'vulpo_seo_404s', 'width' => 50],
    ['type' => 'vulpo_seo_ai_crawlers', 'width' => 50],
],
```

On a multi-site install, **SEO → Settings → Sites** takes a row per site to override the site name, default description, sharing image, business details and llms.txt summary. Anything left empty falls back to the global value.

## Tags

| Tag | Output |
| --- | --- |
| `{{ vulpo_seo }}` | Meta tags and JSON-LD — everything for the `<head>` |
| `{{ vulpo_seo:meta }}` | Meta tags only |
| `{{ vulpo_seo:schema }}` | JSON-LD only |
| `{{ vulpo_seo:title }}` | The resolved page title, as text |
| `{{ vulpo_seo:description }}` | The resolved page description, as text |
| `{{ vulpo_seo:image }}` | The resolved social image URL |

In Blade:

```blade
{!! Statamic::tag('vulpo_seo') !!}
```

## Migrating from alt-seo, alt-sitemap, alt-redirects or vulpo/geo

```bash
php please vulpo:seo:migrate --dry-run   # see what would change
php please vulpo:seo:migrate
```

The command renames legacy field handles on every entry and term (`alt_seo_meta_title` → `seo_title`, `geo_faqs` → `seo_schema_faqs`, …), copies the old global settings into the addon settings, and imports any redirects it finds.

Until you run it, legacy handles are still read at render time, so nothing breaks the moment you swap addons. Set `legacy_fallbacks` to `false` in the config once you have migrated.

Replace `{{ alt_seo:meta }}` and `{{ structured_data }}` in your layout with `{{ vulpo_seo }}`, then remove the old addons from `composer.json`.

## Configuration

Editor-facing options live in the control panel. Developer options — routes, caching, field injection, the AI crawler list — live in `config/seo.php` (Statamic derives the name from the package, so the config key is `seo`):

```bash
php artisan vendor:publish --tag=seo-config
```

To customise the injected fields:

```bash
php artisan vendor:publish --tag=vulpo-seo-blueprints
```

Published blueprints in `resources/blueprints/vendor/vulpo-seo/` win over the addon's own.

### Field handles

| Handle | Purpose |
| --- | --- |
| `seo_title`, `seo_description`, `seo_image` | Search results and sharing previews |
| `seo_canonical`, `seo_noindex`, `seo_nofollow` | Indexing |
| `seo_sitemap_exclude` | Leave the page out of the sitemap |
| `seo_schema_type` + `seo_schema_*` | Per-page structured data |
| `seo_preview` | The live Google and social preview (stores nothing) |

## Where data lives

| What | Where |
| --- | --- |
| Settings | `resources/addons/seo.yaml` |
| Redirects | `content/vulpo-seo/redirects.yaml` |
| 404 log, AI crawler log, URL index | `storage/app/vulpo-seo/` |

Settings and redirects belong in version control. The files in `storage` do not.

## Notes and limits

- Redirects run inside the `web` middleware group, after the response. A URL that never reaches Laravel (a real file on disk, a route outside `web`) is not redirected.
- A physical `public/robots.txt` is served by the web server and wins over this addon. Delete it to let the control panel manage robots.txt. On Laravel Herd the generated body is correct but nginx reports a 404 status for `/robots.txt`, because its `error_page` handler keeps the missing-file status; standard nginx/Apache front-controller configs return 200.
- Automatic redirects react to entry saves. Statamic rewrites child URLs without saving each child, which is why a parent change also adds a `parent/*` wildcard rule.
- Control panel screens use Statamic's own UI components. Every export of the CP's `@ui` package is registered globally as a `ui-<kebab-name>` Vue component, and the CP compiles a Blade view's output as an in-DOM template, so `<ui-card-panel>`, `<ui-table>` and friends work straight from Blade and the screens match the CP. Two things not to try instead: a `<style>` block in a CP view (the Vue app drops it) and Tailwind variants the CP bundle never compiled (`sm:grid-cols-2` and the like are absent, plain utilities are fine).
- The config file is `config/seo.php`, not `config/vulpo-seo.php`. Statamic derives an addon's slug from the package name, and a custom `extra.statamic.slug` breaks core's settings lookup: settings are written to `resources/addons/{slug}.yaml` but read from `resources/addons/{package-name}.yaml`.
- Geocoding uses OpenStreetMap Nominatim, whose usage policy requires a descriptive User-Agent; set `VULPO_SEO_GEOCODER_USER_AGENT` for production.

## Extending

The preview field is a normal fieldtype, so you can move it, drop it, or add it to a blueprint of your own by publishing the blueprints and editing them.

The control panel script is deliberately buildless: `resources/js/cp.js` registers the preview component through the globals Statamic exposes (`window.Statamic.$components`, `window.Vue`, `window.__STATAMIC__`), which means there is no npm dependency, no Vite config, and no bundle to rebuild when Statamic ships a new minor version. It is published to `public/vendor/seo/js/` by `php please statamic:install` or `php artisan vendor:publish --tag=seo --force`.

## Testing

```bash
composer install
composer test
```

## Credits

Built by [Vulpo](https://vulpo.be). Inspired by the MIT-licensed `alt-design/alt-seo` and `alt-design/alt-sitemap` addons, whose field-injection approach showed the way.

## License

MIT. See [LICENSE.md](LICENSE.md).
