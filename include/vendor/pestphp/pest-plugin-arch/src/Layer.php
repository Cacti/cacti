<?php

declare(strict_types=1);

namespace Pest\Arch;

use IteratorAggregate;
use PHPUnit\Architecture\Elements\Layer\Layer as BaseLayer;
use PHPUnit\Architecture\Elements\ObjectDescription;
use Traversable;

/**
 * @method Layer assertDoesNotDependOn(string ...$objects)
 * @method Layer excludeByNameStart(string $name)
 * @method Layer exclude(callable $callback)
 * @method Layer leaveByNameRegex(string $name)
 * @method Layer leaveByNameStart(string $name)
 *
 * @implements IteratorAggregate<int, ObjectDescription>
 */
final class Layer implements IteratorAggregate
{
    /**
     * Creates a new layer instance.
     */
    private function __construct(private readonly BaseLayer $layer)
    {
        //
    }

    /**
     * Creates a new layer instance from the given base layer.
     *
     * @param  array<int, ObjectDescription>  $objects
     */
    public static function fromBase(array $objects): self
    {
        return new self(new BaseLayer($objects));
    }

    /**
     * If the layer is equal to the given layer.
     */
    public function equals(self $layer): bool
    {
        return $this->layer->equals($layer->layer);
    }

    /**
     * Get the iterator.
     */
    public function getIterator(): Traversable
    {
        return $this->layer->getIterator();
    }

    /**
     * Get the base layer.
     */
    public function getBase(): BaseLayer
    {
        return $this->layer;
    }

    /**
     * Dynamically calls the layer methods.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        return new self($this->layer->{$name}(...$arguments)); // @phpstan-ignore-line
    }
}
