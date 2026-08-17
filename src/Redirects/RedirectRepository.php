<?php

namespace Vulpo\Seo\Redirects;

use Illuminate\Support\Collection;
use Vulpo\Seo\Support\YamlFile;

/**
 * Stores redirects in a YAML file inside the project, so they travel with the
 * content in version control.
 */
class RedirectRepository
{
    /** @var Collection<int, Redirect>|null */
    private ?Collection $redirects = null;

    /**
     * @return Collection<int, Redirect>
     */
    public function all(): Collection
    {
        return $this->redirects ??= collect($this->file()->read())
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => Redirect::fromArray($row))
            ->filter->isValid()
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function raw(): array
    {
        return $this->all()->map->toArray()->all();
    }

    /**
     * Replace the whole list, e.g. after the control panel form was saved.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function save(array $rows): void
    {
        $redirects = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => Redirect::fromArray($row))
            ->filter->isValid()
            ->values();

        $this->write($redirects);
    }

    /**
     * Add a redirect, replacing any existing rule for the same source path.
     */
    public function add(Redirect $redirect): bool
    {
        if (! $redirect->isValid()) {
            return false;
        }

        $redirects = $this->all()
            ->reject(fn (Redirect $existing) => strcasecmp($existing->from, $redirect->from) === 0)
            ->push($redirect)
            ->values();

        $this->write($redirects);

        return true;
    }

    public function remove(string $from): void
    {
        $this->write($this->all()
            ->reject(fn (Redirect $redirect) => strcasecmp($redirect->from, $from) === 0)
            ->values());
    }

    public function has(string $from): bool
    {
        return $this->all()->contains(fn (Redirect $redirect) => strcasecmp($redirect->from, $from) === 0);
    }

    /**
     * Resolve the destination for a path, following at most a few hops so a
     * misconfigured chain can never loop forever.
     *
     * @return array{to: string, status: int}|null
     */
    public function resolve(string $path): ?array
    {
        $current = Redirect::normalize($path);
        $visited = [$current];
        $result = null;

        for ($hop = 0; $hop < 5; $hop++) {
            if (! $hit = $this->firstMatch($current)) {
                break;
            }

            $result = $hit;
            $next = Redirect::normalize($hit['to']);

            // An external target ends the chain, and so does a loop.
            if (str_starts_with($hit['to'], 'http') || in_array($next, $visited, true)) {
                break;
            }

            $visited[] = $next;
            $current = $next;
        }

        return $result;
    }

    /**
     * @return array{to: string, status: int}|null
     */
    private function firstMatch(string $path): ?array
    {
        foreach ($this->all() as $redirect) {
            $destination = $redirect->destinationFor($path);

            if ($destination !== null && $destination !== $path) {
                return ['to' => $destination, 'status' => $redirect->status];
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->redirects = null;
    }

    /**
     * @param  Collection<int, Redirect>  $redirects
     */
    private function write(Collection $redirects): void
    {
        $this->file()->write($redirects->map->toArray()->all());

        $this->redirects = $redirects;
    }

    private function file(): YamlFile
    {
        return YamlFile::inProject((string) config('seo.redirects.path', 'content/vulpo-seo/redirects.yaml'));
    }
}
