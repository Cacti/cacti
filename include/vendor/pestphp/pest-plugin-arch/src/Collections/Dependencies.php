<?php

declare(strict_types=1);

namespace Pest\Arch\Collections;

use Pest\Arch\ValueObjects\Dependency;
use Stringable;

/**
 * @internal
 */
final class Dependencies implements Stringable
{
    /**
     * Creates a new Layers instance.
     *
     * @param  array<int, Dependency>  $values
     */
    public function __construct(
        public readonly array $values,
    ) {
        // ..
    }

    /**
     * Creates a new Dependencies collection instance from the given Expectation input.
     *
     * @param  array<int, string>|string  $values
     */
    public static function fromExpectationInput(array|string $values): self
    {
        return new self(array_map(
            static fn (string $value): Dependency => Dependency::fromString($value),
            is_array($values) ? $values : [$values]
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return implode(', ', array_map(
            static fn (Dependency $dependency): string => $dependency->value,
            $this->values
        ));
    }
}
