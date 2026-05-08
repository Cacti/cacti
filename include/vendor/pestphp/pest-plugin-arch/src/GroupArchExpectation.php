<?php

declare(strict_types=1);

namespace Pest\Arch;

use Closure;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 *
 * @mixin Expectation<string>
 */
final class GroupArchExpectation implements Contracts\ArchExpectation
{
    /**
     * Creates a new Arch Expectation instance.
     *
     * @param  Expectation<array<int, string>|string>  $original
     * @param  array<int, GroupArchExpectation|SingleArchExpectation>  $expectations
     */
    private function __construct(private readonly Expectation $original, private readonly array $expectations)
    {
        // ...
    }

    /**
     * Ignores the given layers.
     *
     * @param  array<int, string>|string  $targetsOrDependencies
     * @return $this
     */
    public function ignoring(array|string $targetsOrDependencies): self
    {
        foreach ($this->expectations as $expectation) {
            $expectation->ignoring($targetsOrDependencies);
        }

        return $this;
    }

    /**
     * Ignores the global "user defined" functions.
     *
     * @return $this
     */
    public function ignoringGlobalFunctions(): self
    {
        foreach ($this->expectations as $expectation) {
            $expectation->ignoringGlobalFunctions();
        }

        return $this;
    }

    /**
     * Sets the "opposite" callback.
     */
    public function opposite(Closure $callback): self
    {
        foreach ($this->expectations as $expectation) {
            $expectation->opposite($callback);
        }

        return $this;
    }

    /**
     * Creates a new Arch Expectation instance from the given expectations.
     *
     * @param  Expectation<array<int, string>|string>  $original
     * @param  array<int, GroupArchExpectation|SingleArchExpectation>  $expectations
     */
    public static function fromExpectations(Expectation $original, array $expectations): self
    {
        return new self($original, $expectations);
    }

    /**
     * Proxies the call to the first expectation.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return Expectation<string>
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->ensureLazyExpectationIsVerified();

        return $this->original->$name(...$arguments); // @phpstan-ignore-line
    }

    /**
     * Proxies the call to the expectation.
     *
     * @return Expectation<string>
     */
    public function __get(string $name): mixed
    {
        $this->ensureLazyExpectationIsVerified();

        return $this->original->$name; // @phpstan-ignore-line
    }

    /**
     * {@inheritDoc}
     */
    public function mergeExcludeCallbacks(array $excludeCallbacks): void
    {
        foreach ($this->expectations as $expectation) {
            $expectation->mergeExcludeCallbacks($excludeCallbacks);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function excludeCallbacks(): array
    {
        return array_merge(...array_map(
            fn (ArchExpectation $expectation): array => $expectation->excludeCallbacks(), $this->expectations,
        ));
    }

    /**
     * Ensures the lazy expectation is verified when the object is destructed.
     */
    public function __destruct()
    {
        $this->ensureLazyExpectationIsVerified();
    }

    /**
     * Ensures the lazy expectation is verified.
     */
    private function ensureLazyExpectationIsVerified(): void
    {
        foreach ($this->expectations as $expectation) {
            $expectation->ensureLazyExpectationIsVerified();
        }
    }
}
