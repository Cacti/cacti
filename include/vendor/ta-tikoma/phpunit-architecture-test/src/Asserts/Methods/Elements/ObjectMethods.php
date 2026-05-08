<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Methods\Elements;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<MethodDescription>
 */
final class ObjectMethods implements IteratorAggregate
{
    /**
     * Object methods
     *
     * @var MethodDescription[]
     */
    protected array $methods;

    /**
     * @param MethodDescription[] $methods
     */
    public function __construct(array $methods)
    {
        $this->methods = $methods;
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->methods);
    }
}
