<?php

namespace Vulpo\Seo\Redirects;

use Illuminate\Support\Collection;
use Vulpo\Seo\Support\YamlFile;

/**
 * Remembers which URLs returned a 404, so an editor can turn the ones that
 * matter into redirects. Disposable data, so it lives in storage rather than
 * in the content directory.
 */
class NotFoundLog
{
    /**
     * @return Collection<int, array{path: string, hits: int, last_seen: string, referer: string|null}>
     */
    public function all(): Collection
    {
        return collect($this->file()->read())
            ->filter(fn ($row) => is_array($row) && isset($row['path']))
            ->sortByDesc('last_seen')
            ->values();
    }

    public function record(string $path, ?string $referer = null): void
    {
        if (! config('seo.redirects.log_not_found', true)) {
            return;
        }

        $path = Redirect::normalize($path);
        $entries = $this->all()->keyBy('path')->all();

        $entries[$path] = [
            'path' => $path,
            'hits' => (int) ($entries[$path]['hits'] ?? 0) + 1,
            'last_seen' => now()->toDateTimeString(),
            'referer' => $referer ?: ($entries[$path]['referer'] ?? null),
        ];

        $max = (int) config('seo.redirects.not_found_log_max', 500);

        $this->file()->write(collect($entries)
            ->sortByDesc('last_seen')
            ->take($max)
            ->values()
            ->all());
    }

    public function forget(string $path): void
    {
        $path = Redirect::normalize($path);

        $this->file()->write($this->all()
            ->reject(fn (array $row) => $row['path'] === $path)
            ->values()
            ->all());
    }

    public function clear(): void
    {
        $this->file()->write([]);
    }

    private function file(): YamlFile
    {
        return YamlFile::inStorage((string) config('seo.redirects.not_found_log_path', 'vulpo-seo/not-found.yaml'));
    }
}
