<?php

namespace Vulpo\Seo\Redirects;

use Illuminate\Support\Collection;
use Vulpo\Seo\Storage\RowRepository;

/**
 * Redirects, kept either in a YAML file inside the project (so they travel with
 * the content in version control) or in the database on an eloquent-driver site.
 * Which one is decided by Vulpo\Seo\Storage\StorageManager.
 */
class RedirectRepository
{
    /** @var Collection<int, Redirect>|null */
    private ?Collection $redirects = null;

    public function __construct(private readonly RowRepository $rows) {}

    /**
     * @return Collection<int, Redirect>
     */
    public function all(): Collection
    {
        return $this->redirects ??= collect($this->rows->all())
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
            ->reject(fn (Redirect $existing) => $this->isSameRule($existing, $redirect->from, $redirect->site))
            ->push($redirect)
            ->values();

        $this->write($redirects);

        return true;
    }

    public function remove(string $from, ?string $site = null): void
    {
        $this->write($this->all()
            ->reject(fn (Redirect $redirect) => $this->isSameRule($redirect, $from, $site))
            ->values());
    }

    public function has(string $from, ?string $site = null): bool
    {
        return $this->all()->contains(
            fn (Redirect $redirect) => strcasecmp($redirect->from, $from) === 0 && $redirect->appliesTo($site),
        );
    }

    /**
     * Two rules are the same when they cover the same path on the same site, so
     * a French rule never overwrites the Dutch one for the same URL.
     */
    private function isSameRule(Redirect $redirect, string $from, ?string $site): bool
    {
        return strcasecmp($redirect->from, $from) === 0 && $redirect->site === $site;
    }

    /**
     * Resolve the destination for a path, following at most a few hops so a
     * misconfigured chain can never loop forever.
     *
     * `consumed_query` tells the caller the matching rule matched on the query
     * string, so it must not be appended to the destination again.
     *
     * @return array{to: string, status: int, consumed_query: bool}|null
     */
    public function resolve(string $path, ?string $site = null, ?string $query = null): ?array
    {
        $current = Redirect::normalize($path);
        $visited = [$current];
        $result = null;

        for ($hop = 0; $hop < 5; $hop++) {
            if (! $hit = $this->firstMatch($current, $site, $query)) {
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
            // Only the requested URL has a query string; a hop within the site does not.
            $query = null;
        }

        return $result;
    }

    /**
     * @return array{to: string, status: int, consumed_query: bool}|null
     */
    private function firstMatch(string $path, ?string $site = null, ?string $query = null): ?array
    {
        $withQuery = $query ? $path.'?'.$query : null;

        foreach ($this->all() as $redirect) {
            // A rule carrying a query string only matches the full request URL.
            $subject = $redirect->matchesQueryString() ? $withQuery : $path;

            if ($subject === null) {
                continue;
            }

            $destination = $redirect->destinationFor($subject, $site);

            if ($destination !== null && $destination !== $subject) {
                return [
                    'to' => $destination,
                    'status' => $redirect->status,
                    'consumed_query' => $redirect->matchesQueryString(),
                ];
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
        $this->rows->replace($redirects->map->toArray()->all());

        $this->redirects = $redirects;
    }
}
