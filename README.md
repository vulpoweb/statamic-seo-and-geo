# SEO & GEO

One addon for everything a Statamic site needs to be found: meta tags, an XML sitemap, redirects, structured data, `robots.txt`, and `llms.txt`.

It replaces the stack many Statamic sites run today. Use it instead of separate meta, sitemap, redirect, and GEO addons. You get one set of settings, one CP section, and one template tag. Data from those addons is read as a fallback, and a migration command moves it over.

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

## Migrating from SEO Pro, vulpo/geo or other SEO addons

```bash
php please vulpo:seo:migrate --dry-run   # see what would change
php please vulpo:seo:migrate
```

The command renames legacy field handles on every entry and term (`geo_faqs` becomes `seo_schema_faqs`, and so on). It copies the old global settings into the addon settings, and it imports any redirects it finds.

SEO Pro keeps everything in one `seo` array per entry, so it is translated rather than renamed. `title`, `description`, `canonical_url`, `image`, `robots_indexing`/`robots_following`, `sitemap`, `priority`, `change_frequency`, and `json_ld_schema` are all mapped over. Its site defaults are read from `resources/addons/seo-pro.yaml`. Two things are not carried over, because they would render literally in a meta tag: values pointing at another field (`@seo:content/title`) and values containing Antlers. The `seo` array itself is left in place, so SEO Pro keeps working if you have not removed it yet.

Until you run the migration, legacy handles are still read at render time. This includes SEO Pro's `seo` array, so nothing breaks the moment you swap addons. Set `legacy_fallbacks` to `false` in the config once you have migrated.

Replace `{{ seo_pro:meta }}` and `{{ structured_data }}` in your layout with `{{ vulpo_seo }}`. Then remove the old addons from `composer.json`.

### Bulk redirects from a CSV

```bash
php please vulpo:seo:import-redirects redirects.csv --dry-run
php please vulpo:seo:import-redirects redirects.csv
```

Columns are `from,to,status,site`. A header row is optional, and `source`/`destination`/`code` are accepted as aliases. A `*` in the source path makes it a wildcard rule. Rows missing a source or destination are skipped and counted rather than failing the import.

## Configuration

Editor-facing options live in the control panel. Developer options live in `config/seo-and-geo.php`: routes, caching, field injection, structured data, and the AI crawler list. Statamic derives the slug from the package name, so the config key is `seo-and-geo`.

```bash
php artisan vendor:publish --tag=seo-and-geo-config
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
| Settings | `resources/addons/seo-and-geo.yaml` |
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
// config/seo-and-geo.php
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
- The config file is `config/seo-and-geo.php`, not `config/vulpo-seo.php`. Statamic derives an addon's slug from the package name, and a custom `extra.statamic.slug` breaks core's settings lookup: settings are written to `resources/addons/{slug}.yaml` but read from `resources/addons/{package-name}.yaml`.
- Canonical URLs drop the query string, except the pagination parameter. `?page=2` points at itself, so page 2 is not read as a duplicate of page 1. `seo-and-geo.canonical.trailing_slash` forces a trailing slash on or off. Null leaves URLs alone.
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

## Pages that are not entries

The addon reads a page's SEO off the entry behind it. A route with no entry --
a product served from an API, a search results page -- can say the same things
through the `Seo` facade, and the layout keeps calling `{{ vulpo_seo }}`
unconditionally.

```php
use Vulpo\Seo\Facades\Seo;
use Vulpo\Seo\Schema\Nodes\{ProductNode, OfferNode};

Seo::override([
        'title'       => $product->name,
        'description' => $product->excerpt,
        'canonical'   => route('shop.product', $product->slug),
        'image'       => $product->cover,
        'og_type'     => 'product',
        'noindex'     => $product->hidden,
    ])
    ->breadcrumbs([
        ['name' => 'Badkamer', 'url' => '/badkamer'],
        ['name' => $product->name],   // the last crumb is the page you are on
    ])
    ->schema(
        ProductNode::make($product->name)
            ->gtin($product->ean)
            ->brand($product->brand)
            ->image($product->images)
            ->condition('new')
            ->offer(OfferNode::fromMinorUnits($product->priceCents)->availability('in_stock')),
    );
```

Precedence, highest first: `Seo::override()`, the page's own fields, legacy
handles, SEO Pro's `seo` array, `Seo::defaults()`, the control panel settings.

Don't branch away from `{{ vulpo_seo }}` to hand-write a head. That is what the
facade replaces, and the branch costs the page its Organization, its WebSite,
the Twitter card, `og:site_name`, `og:locale` and every verification tag.

`Seo::for($object)` is the same thing for a class implementing `ProvidesSeo`.
`Seo::noindex()` and the `seo.noindex` middleware keep a route out of the index;
`noindex_paths` in the settings does it by path pattern. None of them add a
robots.txt `Disallow`, deliberately: a crawler has to be allowed to fetch a URL
in order to read the instruction not to index it.

### Structured data

Nodes go out as a single `@graph` so they can reference each other by `@id`.
`seo-and-geo.schema.graph` reverts to one `<script>` per node.

Builders available: `ProductNode`, `OfferNode`, `AggregateOfferNode`,
`ItemListNode`, `CollectionPageNode`, `BreadcrumbListNode`, `FaqPageNode`, plus
`ShippingDetails` and `ReturnPolicy`. Each returns nothing at all when its
minimum viable data is missing -- an invalid node costs the page its whole rich
result, an absent one costs only itself.

Values are validated on the way in: prices normalise to a dot decimal, dates to
ISO-8601, GTINs are checked against their check digit. A value schema.org would
reject is dropped. Set `seo-and-geo.schema.strict` (or `VULPO_SEO_SCHEMA_STRICT`)
in your test suite to have it throw instead, so a malformed price fails CI
rather than a product page.

Shipping and returns come from the **Shop** settings tab unless an offer says
otherwise, since they are facts about the shop rather than about one product.

### Contributing URLs to the sitemap and llms.txt

The addon can only see entries and terms. Anything else a site serves has to
say so:

```php
use Vulpo\Seo\Contracts\SitemapProvider;
use Vulpo\Seo\Sitemap\SitemapUrl;

class ProductSitemapProvider implements SitemapProvider
{
    public function sitemapUrls(string $site): iterable
    {
        foreach ($this->products->cursor() as $product) {
            yield SitemapUrl::make(route('shop.product', $product->slug))
                ->lastmod($product->updatedAt)
                ->image($product->cover);
        }
    }
}

// In your service provider's boot():
Seo::sitemap()->register(ProductSitemapProvider::class);
Seo::llms()->register(ProductLlmsProvider::class);
```

Register by class string; it doubles as the cache key and keeps a package that
registers twice from counting twice. Providers `yield`, so a paginated API does
not have to be buffered, and they are only resolved when something is actually
being built.

When a provider throws, its last successful result is served instead and the
combined file is cached for `retry_minutes` rather than the full hour. This is
deliberate: a sitemap that shrinks does not read as "the source is down", it
reads as "these pages are gone".

Implement `LlmsFullProvider` as well to list more in `/llms-full.txt` than
belongs in `/llms.txt`. The short file is meant to be read whole, so entries
fill its budget first and no single heading may crowd out the rest.

`Sitemap::flushProvider($key)` and `LlmsTxt::flushProvider($key)` invalidate one
provider's slice, for a package with its own webhook.

## Extending

The preview field is a normal fieldtype. You can move it, drop it, or add it to a blueprint of your own by publishing the blueprints and editing them.

The control panel script is buildless on purpose. `resources/js/cp.js` registers the preview component through the globals Statamic exposes (`window.Statamic.$components`, `window.Vue`, `window.__STATAMIC__`). That means there is no npm dependency, no Vite config, and no bundle to rebuild when Statamic ships a new minor version. Because addon scripts load before the control panel's own modules, the script waits for `window.Statamic` to appear rather than assuming it is there. It is published to `public/vendor/seo-and-geo/js/` by `php please statamic:install` or `php artisan vendor:publish --tag=seo-and-geo --force`.

## Testing

```bash
composer install
composer test
```

## Releasing

Releasing is merging.

Write the release notes under `## Unreleased` in `CHANGELOG.md` as you go. When
it is time to ship, open a pull request that renames that heading to the
version — `## 2.0.0`, or `## 2.0.0 - 2026-10-08` — and add a fresh
`## Unreleased` above it. Merging that pull request runs the suite, tags
`vX.Y.Z`, and creates the GitHub release using those notes as its body.
Packagist picks the tag up on its own.

An ordinary merge releases nothing: the workflow only acts once the changelog
opens with a version rather than `## Unreleased`. That keeps the question of
whether something is a major, a minor or a patch where it belongs — with a
person, in a diff somebody reviews — while costing one line rather than a trip
to the Actions tab.

Nothing is tagged until the tests pass, and re-running is safe: a tag that
already exists is a no-op.

## Credits

Built by [Vulpo](https://vulpo.be).

## License

MIT. See [LICENSE.md](LICENSE.md).
