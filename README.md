# SEO & GEO

One addon for everything a Statamic site needs to be found: meta tags, an XML sitemap, redirects, structured data, `robots.txt`, and `llms.txt`.

It replaces the stack many Statamic sites run today. Use it instead of `alt-design/alt-seo`, `alt-design/alt-sitemap`, `alt-design/alt-redirects`, and `vulpo/geo`. You get one set of settings, one CP section, and one template tag. Data from those addons is read as a fallback, and a migration command moves it over.

## Features

**Live previews.** A read-only field sits at the top of every SEO tab. It shows the Google result and the social card as the editor types. Titles and descriptions are truncated by measured pixel width, which matches how Google cuts them. It warns when the description is missing, the title is too long, or the page is hidden from search. The social card updates as soon as the image is picked, before the entry is saved. The Google preview shows no image on purpose. Google takes its thumbnail from the page content, not from the sharing image.

**Meta tags.** Title, description, canonical, robots, Open Graph, Twitter cards, hreflang, and search console verification from one tag. Per-page fields override site-wide defaults. Each site can override those defaults again.

**Sitemap.** Served at `/sitemap.xml` and built from published, routable entries. Taxonomy terms are optional. Set "leave out of the sitemap" per page, or exclude whole collections. On a multisite install it emits `xhtml:link` hreflang alternates. When the URLs no longer fit one file it becomes a sitemap index (`/sitemap-1.xml`, and so on). It is cached and flushed when content changes.

**Redirects.** A CP screen with exact, wildcard, and regex rules (301, 302, 410). Each rule is scoped to one site or to all of them. Query strings are supported, so legacy `/index.php?id=42` URLs can be matched. Redirects are checked only when a URL would otherwise 404, so normal page loads pay nothing. When a page's URL changes through a renamed slug, a move in the structure, or a changed date, a redirect is created automatically for that site. A wildcard rule is added for its children too.

**404 log.** Every miss is logged per site with hit counts and referrer. The log is searchable and paginated, and any entry can be turned into a redirect in one click.

**Structured data (JSON-LD).** Organization or LocalBusiness, WebSite, BreadcrumbList, and a per-page FAQ, Article, Service, Person, Product, or Event node. A raw JSON-LD field covers anything else. Coordinates are geocoded from the address for free through OpenStreetMap and cached.

**llms.txt.** Served at `/llms.txt`, describing the site and its pages for AI assistants. The summary is editable.

**robots.txt.** Served from the CP when there is no `public/robots.txt`. It includes an AI crawler policy: allow all, block all, or pick.

**AI crawler log.** See which assistants (ChatGPT, Claude, Perplexity, Gemini, and others) actually read the site.

**Dashboard widgets.** Recent 404s and AI crawler activity, so problems surface without going looking.

## Installation

```bash
composer require vulpo/seo-and-geo
php please vulpo:seo:index-uris
```

Working on the addon from a local checkout instead? Point a path repository at it and require `"vulpo/seo-and-geo": "@dev"`:

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

On a multi-site install, **SEO → Settings → Sites** takes a row per site. There you can override the site name, default description, sharing image, business details, and llms.txt summary. Anything left empty falls back to the global value.

## Tags

| Tag | Output |
| --- | --- |
| `{{ vulpo_seo }}` | Meta tags and JSON-LD, everything for the `<head>` |
| `{{ vulpo_seo:meta }}` | Meta tags only |
| `{{ vulpo_seo:schema }}` | JSON-LD only |
| `{{ vulpo_seo:title }}` | The resolved page title, as text |
| `{{ vulpo_seo:description }}` | The resolved page description, as text |
| `{{ vulpo_seo:image }}` | The resolved social image URL |

In Blade:

```blade
{!! Statamic::tag('vulpo_seo') !!}
```

## Migrating from SEO Pro, alt-seo, alt-sitemap, alt-redirects or vulpo/geo

```bash
php please vulpo:seo:migrate --dry-run   # see what would change
php please vulpo:seo:migrate
```

The command renames legacy field handles on every entry and term (`alt_seo_meta_title` becomes `seo_title`, `geo_faqs` becomes `seo_schema_faqs`, and so on). It copies the old global settings into the addon settings, and it imports any redirects it finds.

SEO Pro keeps everything in one `seo` array per entry, so it is translated rather than renamed. `title`, `description`, `canonical_url`, `image`, `robots_indexing`/`robots_following`, `sitemap`, `priority`, `change_frequency`, and `json_ld_schema` are all mapped over. Its site defaults are read from `resources/addons/seo-pro.yaml`. Two things are not carried over, because they would render literally in a meta tag: values pointing at another field (`@seo:content/title`) and values containing Antlers. The `seo` array itself is left in place, so SEO Pro keeps working if you have not removed it yet.

Until you run the migration, legacy handles are still read at render time. This includes SEO Pro's `seo` array, so nothing breaks the moment you swap addons. Set `legacy_fallbacks` to `false` in the config once you have migrated.

Replace `{{ alt_seo:meta }}`, `{{ seo_pro:meta }}`, and `{{ structured_data }}` in your layout with `{{ vulpo_seo }}`. Then remove the old addons from `composer.json`.

### Bulk redirects from a CSV

```bash
php please vulpo:seo:import-redirects redirects.csv --dry-run
php please vulpo:seo:import-redirects redirects.csv
```

Columns are `from,to,status,site`. A header row is optional, and `source`/`destination`/`code` are accepted as aliases. A `*` in the source path makes it a wildcard rule. Rows missing a source or destination are skipped and counted rather than failing the import.

## Configuration

Editor-facing options live in the control panel. Developer options live in `config/seo.php`: routes, caching, field injection, and the AI crawler list. Statamic derives the name from the package, so the config key is `seo`.

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
| `seo_schema_custom` | Raw JSON-LD, merged in as its own node |
| `seo_preview` | The live Google and social preview (stores nothing) |

## Where data lives

Flat file by default:

| What | Where |
| --- | --- |
| Settings | `resources/addons/seo.yaml` |
| Redirects | `content/vulpo-seo/redirects.yaml` |
| 404 log, AI crawler log, URL index | `storage/app/vulpo-seo/` |

Settings and redirects belong in version control. The files in `storage` do not.

### With statamic/eloquent-driver

The addon follows the site. Install the eloquent driver and switch any of its
repositories to `eloquent`, and everything the addon owns moves to the database
too. No configuration is needed:

| What | Where |
| --- | --- |
| Entry and term SEO fields | Wherever Statamic stores entries and terms |
| Settings | `addon_settings` table, through Statamic's own settings repository |
| Redirects | `vulpo_seo_redirects` |
| 404 log | `vulpo_seo_not_found` |
| AI crawler log | `vulpo_seo_ai_crawlers` |
| URL index | `vulpo_seo_uris` |

The addon's migrations load only when its storage resolves to `eloquent`, so a
flat-file project never sees them. On a database site:

```bash
php artisan migrate
php please vulpo:seo:import-to-database   # optional, brings existing flat file data over
php please statamic:eloquent:import-addon-settings  # Statamic's own, for the settings
```

Going the other way, `php please vulpo:seo:export-to-files` writes the database rows back out as YAML.

`import-to-database` leaves the flat files alone, so switching back is a config
change. Re-running it updates rather than duplicating.

Override the detection when you want to decide yourself:

```php
// config/seo.php
'storage' => [
    'driver' => 'file', // or 'eloquent', default 'auto'
],
```

or set `VULPO_SEO_STORAGE_DRIVER=eloquent` in `.env`. Table names are configurable in
the same block.

## Notes and limits

- Redirects run inside the `web` middleware group, after the response. A URL that never reaches Laravel (a real file on disk, or a route outside `web`) is not redirected.
- A physical `public/robots.txt` is served by the web server and wins over this addon. Delete it to let the control panel manage robots.txt.
- On Laravel Herd and Valet, `/robots.txt` comes back with the right body but a 404 status. Their nginx template gives it an exact-match location with no `try_files`, so nginx looks only for a static file. The `error_page 404` handler then renders through PHP while keeping the 404. Sitemap and llms.txt are unaffected because they have no such block. Production nginx and Apache configs return 200. To fix it locally, edit the site's config in `~/Library/Application Support/Herd/config/valet/Nginx/<site>` (Valet: `~/.config/valet/Nginx/<site>`):

  ```nginx
  location = /robots.txt  { access_log off; log_not_found off; try_files $uri "/Applications/Herd.app/Contents/Resources/valet/server.php"; }
  ```

  Then run `herd restart nginx`. Herd regenerates that file when the site is re-secured, so the edit may need repeating.
- Automatic redirects react to entry saves. Statamic rewrites child URLs without saving each child, which is why a parent change also adds a `parent/*` wildcard rule.
- Control panel screens use Statamic's own UI components. Every export of the CP's `@ui` package is registered globally as a `ui-<kebab-name>` Vue component, and the CP compiles a Blade view's output as an in-DOM template. So `<ui-card-panel>`, `<ui-table>`, and friends work straight from Blade and the screens match the CP. Two things not to try instead: a `<style>` block in a CP view (the Vue app drops it) and Tailwind variants the CP bundle never compiled (`sm:grid-cols-2` and the like are absent, plain utilities are fine).
- The config file is `config/seo.php`, not `config/vulpo-seo.php`. Statamic derives an addon's slug from the package name, and a custom `extra.statamic.slug` breaks core's settings lookup: settings are written to `resources/addons/{slug}.yaml` but read from `resources/addons/{package-name}.yaml`.
- Canonical URLs drop the query string, except the pagination parameter. `?page=2` points at itself, so page 2 is not read as a duplicate of page 1. `seo.canonical.trailing_slash` forces a trailing slash on or off. Null leaves URLs alone.
- Entries that only redirect are left out of the sitemap and llms.txt. This covers Statamic's `redirect` field, including `redirect: 404`. Their `absoluteUrl()` returns the destination, so listing them would advertise another page's URL as one of yours.
- `/sitemap.xml`, `/robots.txt`, and `/llms.txt` send `Cache-Control` built from their `cache_minutes` config, with `stale-while-revalidate` so a CDN never makes a crawler wait for a rebuild. Setting `cache_minutes` to 0 sends `no-store`.
- The redirects screen is a grid holding every rule at once. It is comfortable into the hundreds. Past that, edit `content/vulpo-seo/redirects.yaml` (or the table) directly and use the CSV import for bulk work.
- Geocoding uses OpenStreetMap Nominatim, whose usage policy requires a descriptive User-Agent. Set `VULPO_SEO_GEOCODER_USER_AGENT` for production.

## Translations

Every string the addon renders goes through `__()`, and English lives in `lang/en.json`. To translate it, publish that file and drop your own locale next to it:

```bash
php artisan vendor:publish --tag=vulpo-seo-translations
```

## Permissions

| Permission | Allows |
| --- | --- |
| `view vulpo seo` | Opening the settings, redirects, 404 log and crawler screens |
| `edit vulpo seo` | Saving redirects, dismissing 404s, clearing logs |

A role with only the view permission sees the data and no write controls.

## Extending

The preview field is a normal fieldtype. You can move it, drop it, or add it to a blueprint of your own by publishing the blueprints and editing them.

The control panel script is buildless on purpose. `resources/js/cp.js` registers the preview component through the globals Statamic exposes (`window.Statamic.$components`, `window.Vue`, `window.__STATAMIC__`). That means there is no npm dependency, no Vite config, and no bundle to rebuild when Statamic ships a new minor version. Because addon scripts load before the control panel's own modules, the script waits for `window.Statamic` to appear rather than assuming it is there. It is published to `public/vendor/seo/js/` by `php please statamic:install` or `php artisan vendor:publish --tag=seo --force`.

## Testing

```bash
composer install
composer test
```

## Credits

Built by [Vulpo](https://vulpo.be). Inspired by the MIT-licensed `alt-design/alt-seo` and `alt-design/alt-sitemap` addons, whose field-injection approach showed the way.

## License

MIT. See [LICENSE.md](LICENSE.md).
