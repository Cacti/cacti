<?php

declare(strict_types=1);

namespace Pest\Arch\Support;

use PHPUnit\Framework\Assert;
use ReflectionClass;
use ReflectionProperty;

/**
 * @internal
 */
final class AssertLocker
{
    /**
     * The current assert count.
     */
    private static int $count = 0;

    /**
     * Locks the assert count.
     */
    public static function incrementAndLock(): void
    {
        // @phpstan-ignore-next-line
        Assert::assertTrue(true);

        self::$count = Assert::getCount();
    }

    /**
     * Unlocks the assert count.
     */
    public static function unlock(): void
    {
        $reflection = self::reflection();

        $reflection->setValue(null, self::$count);
    }

    /**
     * Gets the current assert count reflection.
     */
    private static function reflection(): ReflectionProperty
    {
        $reflectionClass = new ReflectionClass(Assert::class);

        $property = $reflectionClass->getProperty('count');
        $property->setAccessible(true);

        return $property;
    }
}
