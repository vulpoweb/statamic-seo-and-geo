# Changelog

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
