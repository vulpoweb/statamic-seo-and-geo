<?php

namespace Vulpo\Seo\Sitemap;

use DateTimeInterface;
use InvalidArgumentException;
use Vulpo\Seo\Schema\Support\Normalize;

/**
 * One URL a provider wants in the sitemap.
 *
 * Everything but the location is optional, and deliberately so: a product
 * served from an API often has no modification date, and a made-up <lastmod>
 * is worse than none -- it tells a crawler to come back for a page that has
 * not changed, and stops telling it anything once every page claims today.
 */
final class SitemapUrl
{
    private const CHANGEFREQ = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    private ?string $lastmod = null;

    private ?string $changefreq = null;

    private ?string $priority = null;

    /** @var array<int, array{loc: string, title: string|null, caption: string|null}> */
    private array $images = [];

    /** @var array<int, array{hreflang: string, href: string}> */
    private array $alternates = [];

    private function __construct(private readonly string $loc) {}

    public static function make(string $loc): self
    {
        if (! $absolute = Normalize::url($loc)) {
            throw new InvalidArgumentException("[{$loc}] is not a URL that can be put in a sitemap.");
        }

        return new self($absolute);
    }

    public function lastmod(DateTimeInterface|string|null $value): self
    {
        $this->lastmod = $value === null ? null : Normalize::date($value);

        return $this;
    }

    public function changefreq(?string $value): self
    {
        $value = strtolower(trim((string) $value));

        $this->changefreq = in_array($value, self::CHANGEFREQ, true) ? $value : null;

        return $this;
    }

    public function priority(float|string|null $value): self
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            $this->priority = null;

            return $this;
        }

        $this->priority = number_format(max(0.0, min(1.0, (float) $value)), 1, '.', '');

        return $this;
    }

    public function image(string $loc, ?string $title = null, ?string $caption = null): self
    {
        if ($absolute = Normalize::url($loc)) {
            $this->images[] = ['loc' => $absolute, 'title' => $title, 'caption' => $caption];
        }

        return $this;
    }

    public function alternate(string $hreflang, string $href): self
    {
        if ($absolute = Normalize::url($href)) {
            $this->alternates[] = ['hreflang' => $hreflang, 'href' => $absolute];
        }

        return $this;
    }

    /**
     * The shape the sitemap view reads, which is also the shape entries are
     * already built in.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'loc' => $this->loc,
            'lastmod' => $this->lastmod,
            'changefreq' => $this->changefreq,
            'priority' => $this->priority,
            'alternates' => $this->alternates,
            'images' => $this->images,
        ];
    }
}
