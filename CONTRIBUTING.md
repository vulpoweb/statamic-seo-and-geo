# Contributing

Thanks for helping out.

## Getting set up

```bash
composer install
vendor/bin/pest
vendor/bin/pint
```

The suite runs against flat-file storage by default. To run it against the database driver:

```bash
VULPO_SEO_STORAGE_DRIVER=eloquent vendor/bin/pest
```

CI runs both, so a change to storage needs to pass both.

## Pull requests

- One change per pull request.
- Add a test. Bugs get a test that fails before the fix; features get a test for the behaviour, not the implementation.
- Run `vendor/bin/pint` before pushing.
- Note anything user-visible in `CHANGELOG.md` under "Unreleased".

## Reporting a bug

Include the Statamic and PHP version, whether the site uses `statamic/eloquent-driver`, and the smallest reproduction you can manage. A failing test is the fastest possible bug report.

## Adding a schema type or a crawler

New `@type` handling belongs in `src/Seo/Schema.php` with fields in `resources/blueprints/`, and both need a case in `tests/SchemaTest.php`. New AI crawlers are a line in the `ai_crawlers.agents` config; the user agent pattern is matched case-insensitively.
