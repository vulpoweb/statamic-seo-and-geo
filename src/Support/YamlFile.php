<?php

namespace Vulpo\Seo\Support;

use Illuminate\Support\Facades\File;
use Statamic\Facades\YAML;
use Statamic\Yaml\ParseException;
use Symfony\Component\Yaml\Exception\ParseException as SymfonyParseException;

/**
 * A tiny read/write wrapper around a single YAML file.
 *
 * Redirects live in the project (so they can be committed alongside content),
 * while logs live in storage because they are disposable.
 *
 * Logs are written on every request that hits them, so writes are serialised
 * with a lock and land in one rename: a reader never sees a half-written file,
 * and two writers can no longer interleave their bytes.
 */
class YamlFile
{
    private function __construct(
        private readonly string $path,
        private readonly bool $disposable,
    ) {}

    public static function inProject(string $relativePath): self
    {
        return new self(base_path($relativePath), disposable: false);
    }

    public static function inStorage(string $relativePath): self
    {
        return new self(storage_path('app/'.$relativePath), disposable: true);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    /**
     * A disposable file that no longer parses reads as empty, and the next
     * write replaces it. A project file still throws: reading redirects as
     * empty would wipe them all on the next save.
     *
     * @return array<mixed>
     */
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        try {
            return YAML::parse(File::get($this->path)) ?: [];
        } catch (ParseException|SymfonyParseException $exception) {
            if (! $this->disposable) {
                throw $exception;
            }

            report($exception);

            return [];
        }
    }

    /**
     * @param  array<mixed>  $data
     */
    public function write(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        $temporary = $this->path.'.'.bin2hex(random_bytes(6)).'.tmp';

        try {
            File::put($temporary, YAML::dump($data));
            File::move($temporary, $this->path);
        } finally {
            File::delete($temporary);
        }
    }

    /**
     * Read, change and write the file while holding its lock, so concurrent
     * requests take turns instead of overwriting each other.
     *
     * @param  callable(array<mixed>): array<mixed>  $change
     */
    public function mutate(callable $change): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        $lock = fopen($this->path.'.lock', 'c');

        try {
            flock($lock, LOCK_EX);

            $this->write($change($this->read()));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function delete(): void
    {
        File::delete($this->path);
    }
}
