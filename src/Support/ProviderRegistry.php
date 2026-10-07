<?php

namespace Vulpo\Seo\Support;

use Closure;
use InvalidArgumentException;

/**
 * Who else has URLs to contribute.
 *
 * A singleton, not a scoped binding: packages register from boot(), which under
 * Octane happens once per worker rather than once per request. Keying by class
 * string means a package that registers twice still counts once.
 */
class ProviderRegistry
{
    /** @var array<string, class-string|Closure> */
    private array $providers = [];

    /**
     * @param  class-string|Closure(string): iterable<mixed>  $provider
     * @param  string|null  $key  defaults to the class name; required for a closure
     */
    public function register(string|Closure $provider, ?string $key = null): void
    {
        if ($provider instanceof Closure && $key === null) {
            throw new InvalidArgumentException(
                'A closure provider needs a key, so it can be cached and invalidated by name.'
            );
        }

        $this->providers[$key ?? $provider] = $provider;
    }

    public function forget(string $key): void
    {
        unset($this->providers[$key]);
    }

    public function flush(): void
    {
        $this->providers = [];
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /** @return array<string, class-string|Closure> */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Resolve a provider. Lazily, and only when something is actually being
     * built -- a provider that talks to an API must cost nothing on a page
     * request that never asks it anything.
     */
    public function resolve(string $key): ?object
    {
        $provider = $this->providers[$key] ?? null;

        if ($provider instanceof Closure || $provider === null) {
            return $provider;
        }

        return app($provider);
    }
}
