<?php

namespace Vulpo\Seo\Redirects;

use Statamic\Facades\Entry;
use Vulpo\Seo\Support\YamlFile;

/**
 * Remembers the URI of every entry, so a change can be spotted after a save.
 *
 * Statamic re-syncs an entry's original state while saving it, which makes
 * `isDirty()` unreliable inside save events. Keeping our own ledger also catches
 * URL changes that are not slug edits: moving a page in the structure, changing
 * a date on a dated collection, or renaming a parent.
 */
class UriLedger
{
    /** @var array<string, string>|null */
    private ?array $uris = null;

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->uris ??= array_filter(
            $this->file()->read(),
            fn ($uri) => is_string($uri) && $uri !== '',
        );
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
        $uris = $this->all();
        $key = $this->key($id, $site);

        if ($uri === null || $uri === '') {
            if (! array_key_exists($key, $uris)) {
                return;
            }

            unset($uris[$key]);
        } else {
            $uri = '/'.trim($uri, '/');

            if (($uris[$key] ?? null) === $uri) {
                return;
            }

            $uris[$key] = $uri;
        }

        $this->write($uris);
    }

    /**
     * Record the current URI of every entry. Returns the number of URIs stored.
     */
    public function prime(): int
    {
        $uris = [];

        foreach (Entry::all() as $entry) {
            if (! $uri = $entry->uri()) {
                continue;
            }

            $uris[$this->key((string) $entry->id(), (string) $entry->locale())] = '/'.trim($uri, '/');
        }

        $this->write($uris);

        return count($uris);
    }

    public function flush(): void
    {
        $this->uris = null;
    }

    /**
     * @param  array<string, string>  $uris
     */
    private function write(array $uris): void
    {
        ksort($uris);

        $this->file()->write($uris);

        $this->uris = $uris;
    }

    private function key(string $id, string $site): string
    {
        return $site.'::'.$id;
    }

    private function file(): YamlFile
    {
        return YamlFile::inStorage((string) config('vulpo-seo.redirects.uri_ledger_path', 'vulpo-seo/uris.yaml'));
    }
}
