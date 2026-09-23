<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Utility;

final class Arr
{
    /**
     * Get a partial array slice with start/end indexes.
     *
     * @param array    $array the array
     * @param int      $start the starting index (negative = count from backward)
     * @param null|int $end   the ending index (negative = count from backward)
     *                        if is null, it returns a slice from $start to the end
     *
     * @return array array of all of the lines between the specified range
     */
    public static function getPartialByIndex(array $array, int $start = 0, ?int $end = null): array
    {
        $count = \count($array);

        // make $end set
        $end ??= $count;

        // make $start non-negative
        if ($start < 0) {
            $start += $count;

            if ($start < 0) {
                $start = 0;
            }
        }

        // make $end non-negative
        if ($end < 0) {
            $end += $count;

            if ($end < 0) {
                $end = 0;
            }
        }

        // make the length non-negative
        return \array_slice($array, $start, max(0, $end - $start));
    }

    /**
     * Determines whether the array is associative.
     *
     * @param array $arr the array
     *
     * @return bool `true` if the array is associative, `false` otherwise
     */
    public static function isAssociative($arr): bool
    {
        foreach ($arr as $key => $value) {
            if (\is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
