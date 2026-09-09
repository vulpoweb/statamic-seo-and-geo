<?php

namespace Vulpo\Seo\AiCrawlers;

use Illuminate\Support\Collection;
use Vulpo\Seo\Storage\RowRepository;

/**
 * Counts visits from known AI crawlers per bot per day, so a site owner can see
 * whether ChatGPT, Claude, Perplexity and friends are actually reading the site.
 */
class CrawlerLog
{
    public function __construct(private readonly RowRepository $rows) {}

    /**
     * @return Collection<int, array{date: string, bot: string, hits: int, last_path: string, last_seen: string}>
     */
    public function all(): Collection
    {
        return collect($this->rows->all())
            ->filter(fn (array $row) => isset($row['bot'], $row['date']))
            ->map(fn (array $row) => [
                'date' => (string) $row['date'],
                'bot' => (string) $row['bot'],
                'hits' => (int) ($row['hits'] ?? 0),
                'last_path' => (string) ($row['last_path'] ?? ''),
                'last_seen' => (string) ($row['last_seen'] ?? ''),
            ])
            ->sortByDesc('last_seen')
            ->values();
    }

    /**
     * Total hits per bot, most active first.
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        return $this->all()
            ->groupBy('bot')
            ->map(fn (Collection $rows) => (int) $rows->sum('hits'))
            ->sortDesc()
            ->all();
    }

    /**
     * The bot name for a user agent, or null when it is not a known AI crawler.
     */
    public function identify(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        /** @var array<string, string> $agents */
        $agents = config('seo-and-geo.ai_crawlers.agents', []);

        foreach ($agents as $name => $needle) {
            if (stripos($userAgent, (string) $needle) !== false) {
                return (string) $name;
            }
        }

        return null;
    }

    public function record(string $bot, string $path): void
    {
        $this->rows->bump(
            keys: ['date' => now()->toDateString(), 'bot' => $bot],
            counter: 'hits',
            values: [
                'last_path' => $path,
                'last_seen' => now()->toDateTimeString(),
            ],
        );

        $this->rows->pruneBefore(
            'date',
            now()->subDays((int) config('seo-and-geo.ai_crawlers.retention_days', 30))->toDateString(),
        );
    }

    public function clear(): void
    {
        $this->rows->truncate();
    }
}
