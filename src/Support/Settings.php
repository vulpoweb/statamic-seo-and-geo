<?php

namespace Vulpo\Seo\Support;

use Statamic\Facades\Addon;
use Statamic\Support\Arr;

/**
 * Reads the addon's control panel settings.
 *
 * Statamic stores these in `resources/addons/seo.yaml` from the settings
 * blueprint (`resources/blueprints/settings.yaml`). Every value arrives as a
 * string because Statamic parses settings through Antlers, so the accessors here
 * cast back to the type the rest of the addon expects.
 */
class Settings
{
    public const PACKAGE = 'vulpo/seo';

    private static ?array $values = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$values ??= Addon::get(self::PACKAGE)?->settings()->all() ?? [];
    }

    /**
     * Forget the memoised settings, e.g. after they were saved in the CP.
     */
    public static function flush(): void
    {
        self::$values = null;
    }

    /**
     * Use the given values instead of reading them from disk. Meant for tests.
     *
     * @param  array<string, mixed>  $values
     */
    public static function swap(array $values): void
    {
        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Arr::get(self::all(), $key);

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $value = self::get($key);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, ?int $default = null): ?int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<int, mixed>
     */
    public static function list(string $key): array
    {
        $value = self::get($key, []);

        return array_values(array_filter(Arr::wrap($value), fn ($item) => $item !== null && $item !== ''));
    }

    /**
     * Rows of a grid field, with empty rows removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(string $key): array
    {
        return array_values(array_filter(
            self::list($key),
            fn ($row) => is_array($row) && array_filter($row, fn ($value) => $value !== null && $value !== '') !== [],
        ));
    }
}
