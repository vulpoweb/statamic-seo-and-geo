<?php

namespace Vulpo\Seo\Support;

use Illuminate\Support\Facades\File;
use Statamic\Facades\YAML;

/**
 * A tiny read/write wrapper around a single YAML file.
 *
 * Redirects live in the project (so they can be committed alongside content),
 * while logs live in storage because they are disposable.
 */
class YamlFile
{
    private function __construct(private readonly string $path) {}

    public static function inProject(string $relativePath): self
    {
        return new self(base_path($relativePath));
    }

    public static function inStorage(string $relativePath): self
    {
        return new self(storage_path('app/'.$relativePath));
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
     * @return array<mixed>
     */
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        return YAML::parse(File::get($this->path)) ?: [];
    }

    /**
     * @param  array<mixed>  $data
     */
    public function write(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        File::put($this->path, YAML::dump($data));
    }

    public function delete(): void
    {
        File::delete($this->path);
    }
}
