<?php

namespace Vulpo\Seo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Statamic\Console\RunsInPlease;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

/**
 * Bulk-imports redirects from a CSV, which is how sites arriving from WordPress
 * or a redirect spreadsheet get their rules in without hand-typing them into the
 * control panel grid.
 */
class ImportRedirectsCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'vulpo:seo:import-redirects
        {file : Path to a CSV file with from,to[,status][,site] columns}
        {--dry-run : Show what would be imported without writing anything}';

    protected $description = 'Import redirects from a CSV file';

    public function handle(RedirectRepository $redirects): int
    {
        $path = $this->argument('file');

        if (! File::exists($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->components->error("Could not read: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $columns = null;
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            // A header row is optional; without one the order is from, to, status, site.
            if ($columns === null) {
                $columns = $this->header($row);

                if ($columns !== null) {
                    continue;
                }

                $columns = ['from', 'to', 'status', 'site'];
            }

            $values = $this->values($columns, $row);
            $redirect = $this->redirect($values);

            if (! $redirect?->isValid()) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  {$redirect->from} → {$redirect->to} ({$redirect->status})");
                $imported++;

                continue;
            }

            $imported += $redirects->add($redirect) ? 1 : 0;
        }

        fclose($handle);

        $this->components->info("Redirects imported: {$imported}");

        if ($skipped > 0) {
            $this->components->warn("Rows skipped as incomplete or invalid: {$skipped}");
        }

        return self::SUCCESS;
    }

    /**
     * The column names, when the first row is a header rather than data.
     *
     * @param  array<int, string|null>  $row
     * @return array<int, string>|null
     */
    private function header(array $row): ?array
    {
        $names = array_map(fn ($value) => strtolower(trim((string) $value)), $row);

        return in_array('from', $names, true) || in_array('source', $names, true)
            ? $names
            : null;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function values(array $columns, array $row): array
    {
        $values = [];

        foreach ($columns as $index => $name) {
            $values[$name] = trim((string) ($row[$index] ?? ''));
        }

        return [
            'from' => $values['from'] ?? $values['source'] ?? $values['old'] ?? '',
            'to' => $values['to'] ?? $values['destination'] ?? $values['new'] ?? '',
            'status' => $values['status'] ?? $values['type'] ?? $values['code'] ?? '',
            'site' => $values['site'] ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function redirect(array $values): ?Redirect
    {
        if ($values['from'] === '' || $values['to'] === '') {
            return null;
        }

        // The control panel only offers 301, 302 and 410, so the temporary and
        // permanent variants are folded into the ones the grid can display.
        $status = match ((int) ($values['status'] ?: 301)) {
            302, 307 => 302,
            410 => 410,
            default => 301,
        };

        return new Redirect(
            from: Redirect::normalize($values['from']),
            to: $values['to'],
            status: $status,
            match: str_contains($values['from'], '*') ? Redirect::MATCH_WILDCARD : Redirect::MATCH_EXACT,
            created_at: now()->toDateTimeString(),
            site: $values['site'] !== '' ? $values['site'] : null,
        );
    }
}
