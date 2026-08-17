<?php

namespace Vulpo\Seo\Storage;

/**
 * The addon's own data — redirects, the 404 log, the AI crawler log and the URL
 * index — is reached through this narrow interface, so it can live in flat files
 * or in the database without the surrounding code caring which.
 *
 * A row is a plain associative array. Implementations map its keys to whatever
 * their storage calls them.
 */
interface RowRepository
{
    /**
     * Every row, oldest first unless the implementation is asked otherwise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Replace the whole set. Used when the control panel saves a form, and when
     * a log is rebuilt from scratch.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replace(array $rows): void;

    /**
     * Insert or update the single row matching $keys.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function put(array $keys, array $values): void;

    /**
     * Insert or update the row matching $keys, incrementing $counter by one.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function bump(array $keys, string $counter, array $values): void;

    /**
     * @param  array<string, mixed>  $keys
     */
    public function delete(array $keys): void;

    public function truncate(): void;

    /**
     * Drop rows whose $column sorts before $value, e.g. a date cutoff.
     */
    public function pruneBefore(string $column, string $value): void;

    /**
     * Keep only the newest $limit rows, ordered by $column descending.
     */
    public function keepNewest(string $column, int $limit): void;
}
