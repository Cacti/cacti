<?php

declare(strict_types=1);

namespace Pest\Arch;

use Closure;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;
use Pest\Expectations\HigherOrderExpectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 *
 * @mixin Expectation<array<int, string>|string>
 */
final class PendingArchExpectation
{
    /**
     * Whether the expectation is "opposite".
     */
    private bool $opposite = false;

    /**
     * Creates a new Pending Arch Expectation instance.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     * @param  array<int, Closure(ObjectDescription): bool>  $excludeCallbacks
     */
    public function __construct(
        private readonly Expectation $expectation,
        private array $excludeCallbacks,
    ) {}

    /**
     * Filters the given "targets" by only classes.
     */
    public function classes(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! class_exists($object->name) || enum_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only interfaces.
     */
    public function interfaces(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! interface_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only traits.
     */
    public function traits(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! trait_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only enums.
     */
    public function enums(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! enum_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only classes implementing the given interface.
     */
    public function implementing(string $interface): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! in_array($interface, class_implements($object->name));

        return $this;
    }

    /**
     * Filters the given "targets" by only classes extending the given class.
     *
     * @param  class-string  $parentClass
     */
    public function extending(string $parentClass): self
    {
        // @phpstan-ignore-next-line
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! is_subclass_of($object->name, $parentClass);

        return $this;
    }

    /**
     * Filters the given "targets" by only classes using the given trait.
     */
    public function using(string $trait): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! in_array($trait, class_uses($object->name));

        return $this;
    }

    /**
     * Creates an opposite expectation.
     */
    public function not(): self
    {
        $this->opposite = ! $this->opposite;

        return $this;
    }

    /**
     * Proxies the call to the expectation.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): ArchExpectation
    {
        $expectation = $this->opposite ? $this->expectation->not() : $this->expectation;

        /** @var ArchExpectation $archExpectation */
        $archExpectation = $expectation->{$name}(...$arguments);

        if ($archExpectation instanceof HigherOrderExpectation) { // @phpstan-ignore-line
            // @phpstan-ignore-next-line
            $originalExpectation = (fn (): \Pest\Expectation => $this->original)->call($archExpectation);
        } else {
            $originalExpectation = $archExpectation;
        }

        $originalExpectation->mergeExcludeCallbacks($this->excludeCallbacks);

        return $archExpectation;
    }

    /**
     * Proxies the call to the expectation.
     */
    public function __get(string $name): mixed
    {
        return $this->{$name}();
    }
}
