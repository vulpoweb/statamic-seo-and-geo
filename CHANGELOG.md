# Changelog

## Unreleased

- The **SEO** entry in the control panel sidebar no longer doubles up: core's own entry for the addon, nested under Tools → Addons, is removed since this addon already links to the same settings screen.
- Per-site overrides are a collapsible replicator rather than a stacked grid, so a site's seven fields fold away into one row.
- Migrates from `statamic/seo-pro`: its per-entry `seo` array and its site defaults are translated to this addon's handles and settings, and are read at render time until you migrate. Values pointing at another field (`@seo:content/title`) and values containing Antlers are dropped rather than rendered literally.
- `php please vulpo:seo:import-redirects file.csv` imports redirects in bulk, with an optional header row, column aliases and a dry run.
- Every string ships in `lang/en.json`, publishable with the `vulpo-seo-translations` tag, so the addon can be translated.
- `/sitemap.xml`, `/robots.txt` and `/llms.txt` send `Cache-Control` with `stale-while-revalidate`, built from their `cache_minutes` config.
- Canonical URLs keep the pagination parameter so page 2 points at itself, and `seo.canonical.trailing_slash` can force a trailing slash on or off.
- Fixed: entries that only redirect somewhere else (Statamic's `redirect` field, including `redirect: 404`) were listed in the sitemap and llms.txt. Because `absoluteUrl()` returns the destination for such an entry, the sitemap advertised the redirect target as one of the site's own pages.
- Fixed: a value on a legacy handle was masked by the new field's untouched toggle, which augments to `false` rather than to nothing.

## 1.4.0

- Redirects and the 404 log are scoped to a site. A rule can apply to one site or to all of them, automatic redirects belong to the entry's site, and the same path on two sites no longer overwrites itself. Existing rules keep working: no site means every site.
- Redirects match query strings, so legacy URLs like `/index.php?id=42` can be redirected. A rule that matched on the query string no longer has it appended to the destination again.
- The sitemap emits `xhtml:link` hreflang alternates on a multisite install, and turns into a sitemap index once the URLs pass the per-file limit instead of silently dropping the overflow.
- Open Graph article properties (`article:published_time`, `article:modified_time`, `article:author`) and an optional `twitter:creator`.
- Structured data gains Product and Event types, plus a raw JSON-LD field for anything the addon does not model. Invalid JSON is ignored rather than breaking the page.
- The 404 log is searchable and paginated.
- `view vulpo seo` and `edit vulpo seo` are separate permissions, so a role can be read-only.
- `php please vulpo:seo:export-to-files` mirrors the import command.
- Fixed: `routes/web.php` still read the old `vulpo-seo.*` config keys, so the sitemap, robots and llms.txt routes ignored the published config and used their defaults.

## 1.3.0

- Works with statamic/eloquent-driver. Redirects, the 404 log, the AI crawler log and the URL index move to database tables on a site that keeps its content in the database, and stay in flat files on one that does not. Detection follows the eloquent driver's own configuration and can be overridden with `seo.storage.driver`.
- `php please vulpo:seo:import-to-database` brings existing flat file data over, without deleting the files.
- Settings already followed the site: they are read through Statamic's addon settings repository, which the eloquent driver implements for the database.

## 1.2.3

- The Google preview is readable in dark mode. It now renders on its own white surface with Google's colours, instead of putting Google's light-mode blue and grey straight onto the control panel's dark background.
- The social card follows the control panel's light and dark palette, and its empty image state is a short strip instead of a full 1200x630 hole.

## 1.2.2

- The social preview now follows the image the editor picks, reading it from the publish form's meta instead of only the value that was saved.
- The Google preview says what it does not show: Google takes its thumbnail from the page content, not from the sharing image.

## 1.2.1

- Fixed the SEO preview rendering "Component seo_preview-fieldtype does not exist". Addon scripts are emitted before the control panel's own Vite modules, so `window.Statamic` did not exist yet when the script ran; it now waits for the control panel instead of returning early.

## 1.2.0

- Site verification fields for Google, Bing, Pinterest and Facebook, plus a grid for anything else. A pasted `<meta>` tag is accepted as well as a bare code.
- Dashboard widgets: recent 404s and AI crawler activity.
- Per-site overrides on a new Sites settings tab, for the site name, default description, sharing image, business details and llms.txt summary.

## 1.1.0

- Live Google and social previews on the SEO tab, as a read-only `seo_preview` fieldtype.
- CI on GitHub Actions: Pest across PHP 8.3 and 8.4 against lowest and highest dependencies, plus a Pint check.

## 1.0.5

- The control panel screens are now built from Statamic's own UI components (`ui-header`, `ui-card-panel`, `ui-table`, `ui-badge`, `ui-button`, `ui-input`), so they match the control panel exactly instead of imitating it. No addon CSS ships any more.

## 1.0.4

- The control panel stylesheet is now a published asset registered through `$stylesheets`, instead of a `<style>` block in the view. The control panel's Vue app drops inline style elements from a Blade view, which left both screens unstyled.

## 1.0.3

- Control panel screens now carry their own CSS instead of Tailwind utility classes, which the control panel bundle does not contain for addon views. The crawler totals sit in a compact row of cards and both tables are denser.
- The 404 log only reports a destination for active redirects; an inactive rule shows the "add redirect" form, since an inactive rule is why the URL 404s.

## 1.0.2

- Fixed the redirects screen failing to render when installed from vendor (blueprint path resolved one directory too high)
- Settings and config now use the `seo` slug Statamic derives from the package name; a custom slug meant control panel settings were saved but never read back

## 1.0.0

Initial release.

- Meta tags: title, description, canonical, robots, Open Graph, Twitter cards, hreflang
- XML sitemap with per-page and per-collection exclusions
- Redirects with exact, wildcard and regex matching, plus automatic redirects on URL changes
- 404 log with one-click redirect creation
- Structured data: Organization / LocalBusiness, WebSite, BreadcrumbList, FAQ, Article, Service, Person
- Free geocoding through OpenStreetMap Nominatim
- `llms.txt` and control-panel-managed `robots.txt` with an AI crawler policy
- AI crawler visit log
- `vulpo:seo:migrate` for data from alt-seo, alt-sitemap, alt-redirects and vulpo/geo
- `vulpo:seo:index-uris` to prime the URL index used by automatic redirects
