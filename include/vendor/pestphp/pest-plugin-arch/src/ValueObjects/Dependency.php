<?php

declare(strict_types=1);

namespace Pest\Arch\ValueObjects;

use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 */
final class Dependency
{
    /**
     * Creates a new Dependency instance.
     */
    public function __construct(
        public readonly string $value,
    ) {
        // ..
    }

    /**
     * Creates a new Dependency instance from the given "expectation" input.
     */
    public static function fromString(string $value): self
    {
        if (str_contains($value, '/')) {
            throw new ExpectationFailedException(
                "Expecting '{$value}' to be a class name or namespace, but it contains a path.",
            );
        }

        return new self($value);
    }
}
