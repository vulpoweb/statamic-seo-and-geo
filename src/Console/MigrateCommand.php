<?php

namespace Vulpo\Seo\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Support\Fields;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\YamlFile;

/**
 * Moves data written by the addons Vulpo SEO replaces (alt-design/alt-seo,
 * alt-design/alt-sitemap, alt-design/alt-redirects and vulpo/geo) onto this
 * addon's field handles and settings.
 */
class MigrateCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'vulpo:seo:migrate
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Migrate SEO data from alt-seo, alt-sitemap, alt-redirects and vulpo/geo';

    private bool $dryRun = false;

    public function handle(RedirectRepository $redirects): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->warn('Dry run: nothing will be written.');
        }

        $this->migrateContent();
        $this->migrateSettings();
        $this->migrateRedirects($redirects);

        $this->newLine();
        $this->components->info('Done. Review the results, then remove the old addons from composer.json.');

        return self::SUCCESS;
    }

    private function migrateContent(): void
    {
        $renames = Fields::legacyRenames();
        $entries = 0;
        $terms = 0;

        foreach (Entry::all() as $entry) {
            if ($this->renameFields($entry, $renames)) {
                $entries++;
            }
        }

        foreach (Term::all() as $term) {
            if ($this->renameFields($term, $renames)) {
                $terms++;
            }
        }

        $this->components->info("Entries updated: {$entries}");
        $this->components->info("Terms updated: {$terms}");
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function renameFields(object $item, array $renames): bool
    {
        if (! method_exists($item, 'data')) {
            return false;
        }

        $data = $item->data()->all();
        $migrated = [];

        foreach ($renames as $legacy => $current) {
            if (! array_key_exists($legacy, $data)) {
                continue;
            }

            $value = $data[$legacy];
            unset($data[$legacy]);

            // Never overwrite a value that was already entered on the new field.
            if (($data[$current] ?? null) === null || $data[$current] === '') {
                $data[$current] = $this->convert($current, $value);
            }

            $migrated[] = "{$legacy} → {$current}";
        }

        if ($migrated === []) {
            return false;
        }

        $this->line('  '.($item->id() ?? '?').': '.implode(', ', $migrated));

        if (! $this->dryRun) {
            $item->data($data)->saveQuietly();
        }

        return true;
    }

    /**
     * The old GEO page types used the same values, but a "none" select needs to
     * stay a string while toggles must become booleans.
     */
    private function convert(string $handle, mixed $value): mixed
    {
        return in_array($handle, ['seo_noindex', 'seo_nofollow', 'seo_sitemap_exclude'], true)
            ? filter_var($value, FILTER_VALIDATE_BOOL)
            : $value;
    }

    private function migrateSettings(): void
    {
        $values = [];

        $geo = YamlFile::inProject('content/vulpo-geo/settings.yaml')->read();

        if ($geo !== []) {
            $values = array_merge($values, array_filter([
                'business_type' => $geo['business_type'] ?? null,
                'business_description' => $geo['description'] ?? null,
                'knows_about' => $geo['knows_about'] ?? null,
                'area_served' => $geo['area_served'] ?? null,
                'founding_year' => $geo['founding_year'] ?? null,
                'address_locality' => $geo['address_locality'] ?? null,
                'postal_code' => $geo['postal_code'] ?? null,
                'country_code' => $geo['country_code'] ?? null,
                'opening_hours' => $geo['opening_hours'] ?? null,
                'price_range' => $geo['price_range'] ?? null,
                'geo_lat' => $geo['geo_lat'] ?? null,
                'geo_lng' => $geo['geo_lng'] ?? null,
            ]));
        }

        $altSeo = YamlFile::inProject('content/alt-seo/settings.yaml')->read();

        if ($altSeo !== []) {
            $values = array_merge($values, array_filter([
                'default_description' => $altSeo['alt_seo_meta_description_default'] ?? null,
                'site_name' => $altSeo['alt_seo_site_name'] ?? null,
            ]));
        }

        if ($values === []) {
            $this->components->info('Settings: nothing found to migrate.');

            return;
        }

        $this->components->info('Settings migrated: '.implode(', ', array_keys($values)));

        if ($this->dryRun) {
            return;
        }

        $addon = Addon::get(Settings::PACKAGE);
        $settings = $addon->settings();

        // Existing settings win, so re-running the command is harmless.
        $settings->set(array_merge($values, array_filter($settings->raw(), fn ($value) => $value !== null && $value !== '')));
        $settings->save();

        Settings::flush();
    }

    private function migrateRedirects(RedirectRepository $repository): void
    {
        $candidates = [
            'content/alt-redirects/redirects.yaml',
            'content/redirects/redirects.yaml',
            'content/redirects.yaml',
        ];

        $imported = 0;

        foreach ($candidates as $candidate) {
            $file = YamlFile::inProject($candidate);

            if (! $file->exists()) {
                continue;
            }

            foreach ($this->rows($file->read()) as $row) {
                $from = $row['from'] ?? $row['source'] ?? $row['old'] ?? null;
                $to = $row['to'] ?? $row['destination'] ?? $row['new'] ?? null;

                if (! $from || ! $to) {
                    continue;
                }

                $redirect = new Redirect(
                    from: Redirect::normalize((string) $from),
                    to: (string) $to,
                    status: (int) ($row['status'] ?? $row['type'] ?? 301),
                    match: str_contains((string) $from, '*') ? Redirect::MATCH_WILDCARD : Redirect::MATCH_EXACT,
                    created_at: now()->toDateTimeString(),
                );

                if ($this->dryRun) {
                    $this->line("  {$redirect->from} → {$redirect->to}");
                    $imported++;

                    continue;
                }

                $imported += $repository->add($redirect) ? 1 : 0;
            }
        }

        $this->components->info("Redirects imported: {$imported}");
    }

    /**
     * The old files store either a list of rows or a map of from => to.
     *
     * @param  array<mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $rows[] = $value;

                continue;
            }

            if (is_string($key) && is_string($value)) {
                $rows[] = ['from' => $key, 'to' => $value];
            }
        }

        return $rows;
    }
}
