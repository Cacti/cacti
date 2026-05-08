<?php

declare(strict_types=1);

namespace Pest\Arch\ValueObjects;

use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 */
final class Targets
{
    /**
     * Creates a new Target instance.
     *
     * @param  array<int, string>  $value
     */
    public function __construct(
        public readonly array $value,
    ) {
        // ..
    }

    /**
     * Creates a new Target instance from the given "expectation" input.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    public static function fromExpectation(Expectation $expectation): self
    {
        assert(is_string($expectation->value) || is_array($expectation->value)); // @phpstan-ignore-line

        $values = is_string($expectation->value) ? [$expectation->value] : $expectation->value;

        foreach ($values as $value) {
            if (str_contains($value, '/')) {
                throw new ExpectationFailedException(
                    "Expecting '{$value}' to be a class name or namespace, but it contains a path.",
                );
            }
        }

        return new self($values);
    }
}
