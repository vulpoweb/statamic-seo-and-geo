<?php

namespace Vulpo\Seo\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Storage\YamlRows;
use Vulpo\Seo\Support\YamlFile;

/**
 * Writes the addon's database rows back out to flat files, for a site leaving
 * statamic/eloquent-driver — or for anyone who wants their redirects in version
 * control again.
 */
class ExportToFilesCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'vulpo:seo:export-to-files';

    protected $description = 'Export redirects, logs and the URL index from the database into flat files';

    public function handle(StorageManager $storage): int
    {
        if (! $storage->isEloquent()) {
            $this->components->error('The storage driver already resolves to "file", so there is nothing in the database to export.');

            return self::FAILURE;
        }

        foreach ($this->files() as $set => $file) {
            $rows = $storage->repository($set)->all();

            (new YamlRows($file))->replace($rows);

            $this->components->twoColumnDetail($set, $rows === [] ? 'empty' : count($rows).' rows');
        }

        $this->newLine();
        $this->components->info('Done. Switch the storage driver to "file" to start using them.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, YamlFile>
     */
    private function files(): array
    {
        return [
            StorageManager::REDIRECTS => YamlFile::inProject((string) config('seo-and-geo.redirects.path', 'content/vulpo-seo/redirects.yaml')),
            StorageManager::NOT_FOUND => YamlFile::inStorage((string) config('seo-and-geo.redirects.not_found_log_path', 'vulpo-seo/not-found.yaml')),
            StorageManager::AI_CRAWLERS => YamlFile::inStorage((string) config('seo-and-geo.ai_crawlers.log_path', 'vulpo-seo/ai-crawlers.yaml')),
            StorageManager::URIS => YamlFile::inStorage((string) config('seo-and-geo.redirects.uri_ledger_path', 'vulpo-seo/uris.yaml')),
        ];
    }
}
