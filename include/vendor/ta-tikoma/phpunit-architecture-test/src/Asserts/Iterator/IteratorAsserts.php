<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Iterator;

trait IteratorAsserts
{
    abstract public static function assertTrue($condition, string $message = ''): void;

    /**
     * @template TItemValue
     * @param iterable<string|int, TItemValue>|array<string|int, TItemValue> $list
     * @param callable(TItemValue $item): bool $check
     * @param callable(string|int $key, TItemValue $item): string $message
     */
    public function assertEach($list, callable $check, callable $message): void
    {
        foreach ($list as $key => $item) {
            if (!$check($item)) {
                self::assertTrue(false, $message($key, $item));
            }
        }

        self::assertTrue(true);
    }

    /**
     * @template TItemValue
     * @param iterable<string|int, TItemValue>|array<string|int, TItemValue> $list
     * @param callable(TItemValue $item): bool $check
     * @param callable(string|int $key, TItemValue $item): string $message
     */
    public function assertNotOne($list, callable $check, callable $message): void
    {
        foreach ($list as $key => $item) {
            if ($check($item)) {
                self::assertTrue(false, $message($key, $item));
            }
        }

        self::assertTrue(true);
    }

    /**
     * @template TItemValue
     * @param iterable<string|int, TItemValue>|array<string|int, TItemValue> $list
     * @param callable(TItemValue $item): bool $check
     */
    public function assertAny($list, callable $check, string $message): void
    {
        $isTrue = false;
        foreach ($list as $item) {
            if ($check($item)) {
                $isTrue = true;
            }
        }

        self::assertTrue($isTrue, $message);
    }
}
