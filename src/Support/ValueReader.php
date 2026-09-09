<?php

namespace Vulpo\Seo\Support;

use Statamic\Contracts\Data\Augmentable;
use Statamic\Tags\Context;

/**
 * Reads field values either from the template cascade (when rendering a tag) or
 * straight off an entry or term (when building the sitemap or llms.txt), with
 * legacy handle fallbacks applied.
 */
class ValueReader
{
    /**
     * SEO Pro's `seo` array, already translated to this addon's handles.
     *
     * @var array<string, mixed>|null
     */
    private ?array $seoPro = null;

    /**
     * @param  \Closure(string): mixed  $resolver
     */
    private function __construct(private readonly \Closure $resolver) {}

    public static function fromContext(Context $context): self
    {
        return new self(fn (string $handle) => $context->value($handle));
    }

    public static function fromData(Augmentable $data): self
    {
        return new self(fn (string $handle) => $data->augmentedValue($handle)?->value());
    }

    public static function empty(): self
    {
        return new self(fn (string $handle) => null);
    }

    /**
     * Value of a logical field, checking legacy handles when empty.
     */
    public function field(string $key): mixed
    {
        foreach (Fields::handles($key) as $handle) {
            $value = $this->handle($handle);

            // `false` is skipped as well: an untouched toggle augments to false,
            // which would otherwise mask a legacy value that is actually set.
            if ($value !== null && $value !== '' && $value !== [] && $value !== false) {
                return $value;
            }
        }

        return $this->seoProFields()[Fields::handle($key)] ?? null;
    }

    /**
     * SEO Pro nests everything in one `seo` array rather than using flat
     * handles, so it needs its own lookup for a site that has installed this
     * addon but not yet run `vulpo:seo:migrate`.
     *
     * @return array<string, mixed>
     */
    private function seoProFields(): array
    {
        if ($this->seoPro !== null) {
            return $this->seoPro;
        }

        if (! config('seo-and-geo.legacy_fallbacks', true)) {
            return $this->seoPro = [];
        }

        $value = $this->handle('seo');

        if (is_object($value) && method_exists($value, 'all')) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return $this->seoPro = [];
        }

        return $this->seoPro = (new SeoProImport)->fields(array_map(
            fn ($item) => is_object($item) && method_exists($item, 'value') ? $item->value() : $item,
            $value,
        ));
    }

    public function handle(string $handle): mixed
    {
        try {
            return ($this->resolver)($handle);
        } catch (\Throwable) {
            return null;
        }
    }

    public function string(string $key): ?string
    {
        $value = $this->field($key);

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->field($key), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $key): array
    {
        $value = $this->field($key);

        if (is_object($value) && method_exists($value, 'all')) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            // A grid row is a plain array when read raw, and a Values object once augmented.
            fn ($row) => is_object($row) && method_exists($row, 'all') ? $row->all() : $row,
            $value,
        ), 'is_array'));
    }
}
