<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Elements\Layer;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use PHPUnit\Architecture\Elements\ObjectDescription;
use Traversable;

/**
 * @implements IteratorAggregate<int, ObjectDescription>
 */
final class Layer implements IteratorAggregate
{
    use LayerLeave;
    use LayerExclude;
    use LayerSplit;

    protected ?string $name = null;

    /**
     * @var ObjectDescription[]
     */
    protected array $objects = [];

    /**
     * @param ObjectDescription[] $objects
     */
    public function __construct(
        array $objects
    ) {
        $this->objects = $objects;
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->objects);
    }

    public function getName(): string
    {
        if ($this->name === null) {
            $objectsName = array_map(static function (ObjectDescription $objectDescription): string {
                return $objectDescription->name;
            }, $this->objects);

            sort($objectsName);

            $this->name = implode(',', $objectsName);
        }

        return $this->name;
    }

    /**
     * Compare layers
     */
    public function equals(Layer $layer): bool
    {
        return $this->getName() === $layer->getName();
    }

    /**
     * @param Closure $closure static function(ObjectDescription $objectDescription): bool
     */
    public function leave(Closure $closure): self
    {
        return new Layer(array_filter($this->objects, $closure));
    }

    /**
     * @param Closure $closure static function(ObjectDescription $objectDescription): bool
     */
    public function exclude(Closure $closure): self
    {
        return new Layer(array_filter($this->objects, static function ($item) use ($closure): bool {
            return !$closure($item);
        }));
    }

    /**
     * @param Closure $closure static function(ObjectDescription $objectDescription): ?string
     * @return static[]
     */
    public function split(Closure $closure): array
    {
        $objects = [];

        foreach ($this->objects as $object) {
            /** @var null|string $key */
            $key = $closure($object);

            if ($key === null) {
                continue;
            }

            if (!isset($objects[$key])) {
                $objects[$key] = [];
            }

            $objects[$key][] = $object;
        }

        return array_map(static function (array $objects): Layer {
            return new Layer($objects);
        }, $objects);
    }

    /**
     * @return array<string, mixed>
     */
    public function essence(string $path): array
    {
        return $this->essenceRecursion(
            '',
            explode('.', $path),
            $this->objects
        );
    }

    /**
     * @param string[] $parts
     * @param array<string, mixed> $list
     *
     * @return array<string, mixed>
     */
    private function essenceRecursion(string $path, array $parts, $list): array
    {
        $part = array_shift($parts);
        if ($part === null) {
            return $list;
        }

        $result = [];

        if ($part === '*') {
            foreach ($list as $key => $item) {
                /** @var array<string, mixed> $item */
                $result = array_merge($result, $this->essenceRecursion("$path.$key", $parts, $item));
            }

            return $result;
        }

        foreach ($list as $key => $item) {
            $result["$path.$key"] = $item->$part;
        }

        return $this->essenceRecursion($path, $parts, $result);
    }
}
