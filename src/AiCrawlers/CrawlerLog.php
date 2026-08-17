<?php

namespace Vulpo\Seo\AiCrawlers;

use Illuminate\Support\Collection;
use Vulpo\Seo\Support\YamlFile;

/**
 * Counts visits from known AI crawlers per bot per day, so a site owner can see
 * whether ChatGPT, Claude, Perplexity and friends are actually reading the site.
 */
class CrawlerLog
{
    /**
     * @return Collection<int, array{date: string, bot: string, hits: int, last_path: string, last_seen: string}>
     */
    public function all(): Collection
    {
        return collect($this->file()->read())
            ->filter(fn ($row) => is_array($row) && isset($row['bot'], $row['date']))
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
        $agents = config('vulpo-seo.ai_crawlers.agents', []);

        foreach ($agents as $name => $needle) {
            if (stripos($userAgent, (string) $needle) !== false) {
                return (string) $name;
            }
        }

        return null;
    }

    public function record(string $bot, string $path): void
    {
        $date = now()->toDateString();
        $key = $date.'|'.$bot;

        $rows = $this->all()->keyBy(fn (array $row) => $row['date'].'|'.$row['bot'])->all();

        $rows[$key] = [
            'date' => $date,
            'bot' => $bot,
            'hits' => (int) ($rows[$key]['hits'] ?? 0) + 1,
            'last_path' => $path,
            'last_seen' => now()->toDateTimeString(),
        ];

        $cutoff = now()->subDays((int) config('vulpo-seo.ai_crawlers.retention_days', 30))->toDateString();

        $this->file()->write(collect($rows)
            ->filter(fn (array $row) => $row['date'] >= $cutoff)
            ->sortByDesc('last_seen')
            ->values()
            ->all());
    }

    public function clear(): void
    {
        $this->file()->write([]);
    }

    private function file(): YamlFile
    {
        return YamlFile::inStorage((string) config('vulpo-seo.ai_crawlers.log_path', 'vulpo-seo/ai-crawlers.yaml'));
    }
}
