<?php

namespace Vulpo\Seo\Schema\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Normalize when `seo-and-geo.schema.strict` is on and a value cannot
 * be coerced into something schema.org will accept.
 *
 * Strict mode is meant for a test suite and a local environment. In production
 * the same value is dropped silently, because half a Product node still earns a
 * rich result and a 500 earns nothing.
 */
class InvalidSchemaValue extends InvalidArgumentException
{
    public static function for(string $what, mixed $value): self
    {
        return new self(sprintf(
            'Cannot use %s as a schema.org %s.',
            is_scalar($value) || $value === null ? var_export($value, true) : get_debug_type($value),
            $what,
        ));
    }
}
