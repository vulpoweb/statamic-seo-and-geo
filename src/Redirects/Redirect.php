<?php

namespace Vulpo\Seo\Redirects;

use Statamic\Support\Str;

/**
 * A single redirect rule.
 *
 * `match` decides how `from` is compared to the incoming path:
 *  - exact:    /old-page
 *  - wildcard: /blog/*  (the matched remainder is available as * or $1 in `to`)
 *  - regex:    ^/blog/(\d+)/(.*)$  (captures are available as $1, $2, … in `to`)
 */
class Redirect
{
    public const MATCH_EXACT = 'exact';

    public const MATCH_WILDCARD = 'wildcard';

    public const MATCH_REGEX = 'regex';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTO = 'auto';

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $status = 301,
        public readonly string $match = self::MATCH_EXACT,
        public readonly bool $active = true,
        public readonly string $source = self::SOURCE_MANUAL,
        public readonly ?string $created_at = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $match = (string) ($row['match'] ?? self::MATCH_EXACT);

        return new self(
            from: self::normalize((string) ($row['from'] ?? ''), $match),
            to: trim((string) ($row['to'] ?? '')),
            status: (int) ($row['status'] ?? 301),
            match: $match,
            active: filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOL),
            source: (string) ($row['source'] ?? self::SOURCE_MANUAL),
            created_at: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
            'match' => $this->match,
            'active' => $this->active,
            'source' => $this->source,
            'created_at' => $this->created_at,
        ];
    }

    public function isValid(): bool
    {
        return $this->from !== '' && $this->to !== '' && $this->from !== $this->to;
    }

    /**
     * The destination for the given path, or null when the rule does not match.
     */
    public function destinationFor(string $path): ?string
    {
        if (! $this->active || ! $this->isValid()) {
            return null;
        }

        return match ($this->match) {
            self::MATCH_WILDCARD => $this->matchWildcard($path),
            self::MATCH_REGEX => $this->matchRegex($path),
            default => strcasecmp($path, $this->from) === 0 ? $this->to : null,
        };
    }

    private function matchWildcard(string $path): ?string
    {
        $pattern = '#^'.str_replace('\*', '(.*)', preg_quote($this->from, '#')).'$#i';

        if (! preg_match($pattern, $path, $matches)) {
            return null;
        }

        $remainder = $matches[1] ?? '';

        return str_replace(['*', '$1'], $remainder, $this->to);
    }

    private function matchRegex(string $path): ?string
    {
        $pattern = '#'.str_replace('#', '\#', $this->from).'#i';

        try {
            if (! preg_match($pattern, $path, $matches)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $destination = $this->to;

        foreach ($matches as $index => $capture) {
            $destination = str_replace('$'.$index, (string) $capture, $destination);
        }

        return $destination;
    }

    /**
     * Paths are stored with a leading slash and no trailing slash. Regexes are
     * left exactly as the user typed them.
     */
    public static function normalize(string $path, string $match = self::MATCH_EXACT): string
    {
        $path = trim($path);

        if ($match === self::MATCH_REGEX || $path === '' || Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return '/'.trim($path, '/');
    }
}
