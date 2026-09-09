<?php

namespace Vulpo\Seo\Storage;

use Statamic\Eloquent\ServiceProvider;
use Vulpo\Seo\Support\YamlFile;

/**
 * Decides where the addon keeps its data and hands out the right repository.
 *
 * With the driver left on "auto" the addon follows the site: a project running
 * statamic/eloquent-driver for its content gets its redirects and logs in the
 * database too, and a flat-file project keeps YAML. Setting the driver
 * explicitly wins over both.
 */
class StorageManager
{
    public const REDIRECTS = 'redirects';

    public const NOT_FOUND = 'not_found';

    public const AI_CRAWLERS = 'ai_crawlers';

    public const URIS = 'uris';

    /** @var array<string, RowRepository> */
    private array $repositories = [];

    public function driver(): string
    {
        $driver = config('seo-and-geo.storage.driver', 'auto');

        return $driver === 'auto' ? $this->detectDriver() : $driver;
    }

    public function isEloquent(): bool
    {
        return $this->driver() === 'eloquent';
    }

    public function repository(string $set): RowRepository
    {
        return $this->repositories[$set] ??= $this->isEloquent()
            ? $this->eloquent($set)
            : $this->yaml($set);
    }

    public function flush(): void
    {
        $this->repositories = [];
    }

    /**
     * Follow statamic/eloquent-driver: if the site keeps its content in the
     * database, the addon's data belongs there as well.
     */
    private function detectDriver(): string
    {
        if (! class_exists(ServiceProvider::class)) {
            return 'file';
        }

        $drivers = ['entries', 'taxonomies', 'globals', 'addon_settings'];

        foreach ($drivers as $repository) {
            if (config("statamic.eloquent-driver.{$repository}.driver") === 'eloquent') {
                return 'eloquent';
            }
        }

        return 'file';
    }

    private function eloquent(string $set): RowRepository
    {
        return match ($set) {
            self::REDIRECTS => new EloquentRows(
                table: $this->table($set),
                columns: ['from' => 'from_path', 'to' => 'to_path', 'match' => 'match_type'],
            ),
            self::NOT_FOUND => new EloquentRows(
                table: $this->table($set),
                order: 'last_seen',
            ),
            self::AI_CRAWLERS => new EloquentRows(
                table: $this->table($set),
                order: 'last_seen',
            ),
            self::URIS => new EloquentRows(table: $this->table($set)),
            default => throw new \InvalidArgumentException("Unknown storage set [{$set}]."),
        };
    }

    private function yaml(string $set): RowRepository
    {
        return new YamlRows(match ($set) {
            self::REDIRECTS => YamlFile::inProject((string) config('seo-and-geo.redirects.path', 'content/vulpo-seo/redirects.yaml')),
            self::NOT_FOUND => YamlFile::inStorage((string) config('seo-and-geo.redirects.not_found_log_path', 'vulpo-seo/not-found.yaml')),
            self::AI_CRAWLERS => YamlFile::inStorage((string) config('seo-and-geo.ai_crawlers.log_path', 'vulpo-seo/ai-crawlers.yaml')),
            self::URIS => YamlFile::inStorage((string) config('seo-and-geo.redirects.uri_ledger_path', 'vulpo-seo/uris.yaml')),
            default => throw new \InvalidArgumentException("Unknown storage set [{$set}]."),
        });
    }

    private function table(string $set): string
    {
        return (string) config("seo-and-geo.storage.tables.{$set}", 'vulpo_seo_'.$set);
    }
}
