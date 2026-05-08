<?php

declare(strict_types=1);

namespace Pest\Arch\Objects;

use PHPUnit\Architecture\Asserts\Dependencies\Elements\ObjectUses;
use PHPUnit\Architecture\Elements\ObjectDescription;
use ReflectionFunction;
use Throwable;

/**
 * @internal
 */
final class FunctionDescription extends ObjectDescription
{
    /**
     * {@inheritDoc}
     */
    public static function make(string $path): self
    {
        $description = new self;

        try {
            $description->path = (string) (new ReflectionFunction($path))->getFileName();
        } catch (Throwable) {
            $description->path = $path;
        }

        /** @var class-string<mixed> $path */
        $description->name = $path;
        $description->uses = new ObjectUses([]);
        // $description->reflectionClass = new ReflectionFunction($path);

        return $description;
    }
}
