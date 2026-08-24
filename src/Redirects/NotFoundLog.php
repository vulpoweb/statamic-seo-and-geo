<?php

namespace Vulpo\Seo\Redirects;

use Illuminate\Support\Collection;
use Vulpo\Seo\Storage\RowRepository;

/**
 * Remembers which URLs returned a 404, so an editor can turn the ones that
 * matter into redirects. Disposable data: it lives outside the content
 * directory, in storage or in the database.
 */
class NotFoundLog
{
    public function __construct(private readonly RowRepository $rows) {}

    /**
     * @return Collection<int, array{path: string, site: string|null, hits: int, last_seen: string, referer: string|null}>
     */
    public function all(): Collection
    {
        return collect($this->rows->all())
            ->filter(fn (array $row) => isset($row['path']))
            // Storage may leave out a null referer, so guarantee the shape here
            // rather than making every caller defensive.
            ->map(fn (array $row) => [
                'path' => (string) $row['path'],
                'site' => $row['site'] ?? null,
                'hits' => (int) ($row['hits'] ?? 0),
                'last_seen' => (string) ($row['last_seen'] ?? ''),
                'referer' => $row['referer'] ?? null,
            ])
            ->sortByDesc('last_seen')
            ->values();
    }

    public function record(string $path, ?string $referer = null, ?string $site = null): void
    {
        if (! config('seo.redirects.log_not_found', true)) {
            return;
        }

        $this->rows->bump(
            keys: ['path' => Redirect::normalize($path), 'site' => $site],
            counter: 'hits',
            // A later hit without a referer must not wipe the one we already have.
            values: array_filter([
                'last_seen' => now()->toDateTimeString(),
                'referer' => $referer,
            ], fn ($value) => $value !== null),
        );

        $this->rows->keepNewest('last_seen', (int) config('seo.redirects.not_found_log_max', 500));
    }

    public function forget(string $path, ?string $site = null): void
    {
        $this->rows->delete(array_filter(
            ['path' => Redirect::normalize($path), 'site' => $site],
            fn ($value) => $value !== null,
        ));
    }

    public function clear(): void
    {
        $this->rows->truncate();
    }
}
