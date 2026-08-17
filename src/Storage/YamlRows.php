<?php

namespace Vulpo\Seo\Storage;

use Vulpo\Seo\Support\YamlFile;

/**
 * Rows in a YAML file. The default, and what a flat-file Statamic site uses.
 */
class YamlRows implements RowRepository
{
    public function __construct(private readonly YamlFile $file) {}

    public function all(): array
    {
        return array_values(array_filter($this->file->read(), 'is_array'));
    }

    public function replace(array $rows): void
    {
        $this->file->write(array_values($rows));
    }

    public function put(array $keys, array $values): void
    {
        $rows = $this->all();
        $index = $this->indexOf($rows, $keys);

        if ($index === null) {
            $rows[] = array_merge($keys, $values);
        } else {
            $rows[$index] = array_merge($rows[$index], $values);
        }

        $this->replace($rows);
    }

    public function bump(array $keys, string $counter, array $values): void
    {
        $rows = $this->all();
        $index = $this->indexOf($rows, $keys);
        $current = $index === null ? 0 : (int) ($rows[$index][$counter] ?? 0);

        $this->put($keys, array_merge($values, [$counter => $current + 1]));
    }

    public function delete(array $keys): void
    {
        $this->replace(array_values(array_filter(
            $this->all(),
            fn (array $row) => ! $this->matches($row, $keys),
        )));
    }

    public function truncate(): void
    {
        $this->replace([]);
    }

    public function pruneBefore(string $column, string $value): void
    {
        $this->replace(array_values(array_filter(
            $this->all(),
            fn (array $row) => (string) ($row[$column] ?? '') >= $value,
        )));
    }

    public function keepNewest(string $column, int $limit): void
    {
        $rows = $this->all();

        usort($rows, fn (array $a, array $b) => strcmp((string) ($b[$column] ?? ''), (string) ($a[$column] ?? '')));

        $this->replace(array_slice($rows, 0, $limit));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $keys
     */
    private function indexOf(array $rows, array $keys): ?int
    {
        foreach ($rows as $index => $row) {
            if ($this->matches($row, $keys)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $keys
     */
    private function matches(array $row, array $keys): bool
    {
        foreach ($keys as $key => $value) {
            if (($row[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
