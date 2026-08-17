<?php

namespace Vulpo\Seo\Storage;

use Illuminate\Support\Facades\DB;

/**
 * Rows in a database table, for sites running statamic/eloquent-driver.
 *
 * Deliberately built on the query builder rather than Eloquent models: there is
 * no behaviour to hang off a model here, and it keeps the addon out of the way
 * of a project's own model conventions.
 *
 * $columns maps a row key to its column when the two differ — `from` and `to`
 * are awkward as column names, so the redirects table uses from_path/to_path.
 */
class EloquentRows implements RowRepository
{
    /**
     * @param  array<string, string>  $columns
     */
    public function __construct(
        private readonly string $table,
        private readonly array $columns = [],
        private readonly ?string $order = null,
    ) {}

    public function all(): array
    {
        $query = DB::table($this->table);

        if ($this->order) {
            $query->orderBy($this->column($this->order), 'desc');
        } else {
            $query->orderBy('id');
        }

        return $query->get()
            ->map(fn ($record) => $this->fromColumns((array) $record))
            ->all();
    }

    public function replace(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            DB::table($this->table)->delete();

            $records = array_map(fn (array $row) => $this->toColumns($row), array_values($rows));

            foreach (array_chunk($records, 500) as $chunk) {
                DB::table($this->table)->insert($chunk);
            }
        });
    }

    public function put(array $keys, array $values): void
    {
        DB::table($this->table)->updateOrInsert(
            $this->toColumns($keys),
            $this->toColumns($values),
        );
    }

    public function bump(array $keys, string $counter, array $values): void
    {
        DB::transaction(function () use ($keys, $counter, $values) {
            $conditions = $this->toColumns($keys);
            $column = $this->column($counter);

            $existing = DB::table($this->table)->where($conditions)->first();

            if ($existing) {
                DB::table($this->table)->where($conditions)->update(
                    array_merge($this->toColumns($values), [$column => ((int) $existing->{$column}) + 1]),
                );

                return;
            }

            DB::table($this->table)->insert(array_merge(
                $conditions,
                $this->toColumns($values),
                [$column => 1],
            ));
        });
    }

    public function delete(array $keys): void
    {
        DB::table($this->table)->where($this->toColumns($keys))->delete();
    }

    public function truncate(): void
    {
        DB::table($this->table)->delete();
    }

    public function pruneBefore(string $column, string $value): void
    {
        DB::table($this->table)->where($this->column($column), '<', $value)->delete();
    }

    public function keepNewest(string $column, int $limit): void
    {
        $keep = DB::table($this->table)
            ->orderBy($this->column($column), 'desc')
            ->limit($limit)
            ->pluck('id');

        DB::table($this->table)->whereNotIn('id', $keep)->delete();
    }

    private function column(string $key): string
    {
        return $this->columns[$key] ?? $key;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toColumns(array $row): array
    {
        $mapped = [];

        foreach ($row as $key => $value) {
            $mapped[$this->column($key)] = is_bool($value) ? (int) $value : $value;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function fromColumns(array $record): array
    {
        $keys = array_flip($this->columns);
        $row = [];

        foreach ($record as $column => $value) {
            if ($column === 'id' || $column === 'updated_at') {
                continue;
            }

            $row[$keys[$column] ?? $column] = $value;
        }

        return $row;
    }
}
