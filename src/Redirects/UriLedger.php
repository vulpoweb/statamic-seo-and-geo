<?php

namespace Vulpo\Seo\Redirects;

use Statamic\Facades\Entry;
use Vulpo\Seo\Storage\RowRepository;
use Vulpo\Seo\Support\YamlFile;

/**
 * Remembers the URI of every entry, so a change can be spotted after a save.
 *
 * Statamic re-syncs an entry's original state while saving it, which makes
 * `isDirty()` unreliable inside save events. Keeping our own record also catches
 * URL changes that are not slug edits: moving a page in the structure, changing
 * a date on a dated collection, or renaming a parent.
 */
class UriLedger
{
    /** @var array<string, string>|null */
    private ?array $uris = null;

    public function __construct(private readonly RowRepository $rows) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->uris !== null) {
            return $this->uris;
        }

        $uris = [];

        foreach ($this->rows->all() as $row) {
            if (isset($row['key'], $row['uri']) && $row['uri'] !== '') {
                $uris[(string) $row['key']] = (string) $row['uri'];
            }
        }

        return $this->uris = $uris + $this->legacy();
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    public function get(string $id, string $site): ?string
    {
        return $this->all()[$this->key($id, $site)] ?? null;
    }

    public function remember(string $id, string $site, ?string $uri): void
    {
        $key = $this->key($id, $site);
        $uris = $this->all();

        if ($uri === null || $uri === '') {
            if (! array_key_exists($key, $uris)) {
                return;
            }

            $this->rows->delete(['key' => $key]);
            unset($uris[$key]);
            $this->uris = $uris;

            return;
        }

        $uri = '/'.trim($uri, '/');

        if (($uris[$key] ?? null) === $uri) {
            return;
        }

        $this->rows->put(['key' => $key], ['uri' => $uri]);

        $uris[$key] = $uri;
        $this->uris = $uris;
    }

    /**
     * Record the current URI of every entry. Returns the number of URIs stored.
     */
    public function prime(): int
    {
        $rows = [];

        foreach (Entry::all() as $entry) {
            if (! $uri = $entry->uri()) {
                continue;
            }

            $rows[] = [
                'key' => $this->key((string) $entry->id(), (string) $entry->locale()),
                'uri' => '/'.trim($uri, '/'),
            ];
        }

        $this->rows->replace($rows);
        $this->uris = null;

        return count($rows);
    }

    public function flush(): void
    {
        $this->uris = null;
    }

    /**
     * Versions before the storage layer wrote a flat `site::id: /uri` map. Read
     * it so an upgraded site keeps its index, and its automatic redirects, until
     * the next save rewrites the entry in the current format.
     *
     * @return array<string, string>
     */
    private function legacy(): array
    {
        $file = YamlFile::inStorage((string) config('seo-and-geo.redirects.uri_ledger_path', 'vulpo-seo/uris.yaml'));

        if (! $file->exists()) {
            return [];
        }

        return array_filter(
            $file->read(),
            fn ($uri, $key) => is_string($key) && is_string($uri) && $uri !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function key(string $id, string $site): string
    {
        return $site.'::'.$id;
    }
}
