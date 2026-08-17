<?php

namespace Vulpo\Seo\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Storage\YamlRows;
use Vulpo\Seo\Support\YamlFile;

/**
 * Moves data the addon already wrote to flat files into the database, for a site
 * switching over to statamic/eloquent-driver.
 *
 * Statamic's own `statamic:eloquent:import-addon-settings` handles the control
 * panel settings; this covers the redirects, the logs and the URL index.
 */
class ImportToDatabaseCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'vulpo:seo:import-to-database
        {--fresh : Empty the database tables before importing}';

    protected $description = 'Import redirects, logs and the URL index from flat files into the database';

    public function handle(StorageManager $storage): int
    {
        if (! $storage->isEloquent()) {
            $this->components->error(
                'The storage driver resolves to "file". Set statamic/eloquent-driver up first, '.
                'or set VULPO_SEO_STORAGE_DRIVER=eloquent.'
            );

            return self::FAILURE;
        }

        foreach ($this->sets() as $set => $file) {
            $rows = (new YamlRows($file))->all();

            if ($set === StorageManager::URIS) {
                $rows = array_merge($rows, $this->legacyUriRows($file));
            }
            $target = $storage->repository($set);

            if ($this->option('fresh')) {
                $target->truncate();
            }

            if ($rows === []) {
                $this->components->twoColumnDetail($set, 'nothing to import');

                continue;
            }

            foreach ($rows as $row) {
                // Insert row by row so a half-filled file still imports what it can.
                $target->put($this->keysFor($set, $row), $row);
            }

            $this->components->twoColumnDetail($set, count($rows).' rows');
        }

        $this->newLine();
        $this->components->info('Done. The flat files are left alone, so you can roll back by switching the driver.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, YamlFile>
     */
    private function sets(): array
    {
        return [
            StorageManager::REDIRECTS => YamlFile::inProject((string) config('seo.redirects.path', 'content/vulpo-seo/redirects.yaml')),
            StorageManager::NOT_FOUND => YamlFile::inStorage((string) config('seo.redirects.not_found_log_path', 'vulpo-seo/not-found.yaml')),
            StorageManager::AI_CRAWLERS => YamlFile::inStorage((string) config('seo.ai_crawlers.log_path', 'vulpo-seo/ai-crawlers.yaml')),
            StorageManager::URIS => YamlFile::inStorage((string) config('seo.redirects.uri_ledger_path', 'vulpo-seo/uris.yaml')),
        ];
    }

    /**
     * The URL index used to be a flat `site::id: /uri` map rather than rows.
     *
     * @return array<int, array<string, string>>
     */
    private function legacyUriRows(YamlFile $file): array
    {
        $rows = [];

        foreach ($file->read() as $key => $uri) {
            if (is_string($key) && is_string($uri) && $uri !== '') {
                $rows[] = ['key' => $key, 'uri' => $uri];
            }
        }

        return $rows;
    }

    /**
     * What makes a row unique, so re-running the command updates instead of
     * duplicating.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function keysFor(string $set, array $row): array
    {
        return match ($set) {
            StorageManager::REDIRECTS => ['from' => $row['from'] ?? null],
            StorageManager::NOT_FOUND => ['path' => $row['path'] ?? null],
            StorageManager::AI_CRAWLERS => ['date' => $row['date'] ?? null, 'bot' => $row['bot'] ?? null],
            StorageManager::URIS => ['key' => $row['key'] ?? null],
            default => $row,
        };
    }
}
