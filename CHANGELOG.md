# Changelog

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
