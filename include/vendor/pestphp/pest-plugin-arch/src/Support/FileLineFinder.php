<?php

declare(strict_types=1);

namespace Pest\Arch\Support;

/**
 * @internal
 */
final class FileLineFinder
{
    /**
     * Gets the line number of the first occurrence of the given callback.
     *
     * @param  callable(string): bool  $callback
     * @return callable(string): int
     */
    public static function where(callable $callback): callable
    {
        return function (string $path) use ($callback): int {
            if (file_exists($path) === false) {
                return 0;
            }

            $contents = (string) file_get_contents($path);

            foreach (explode("\n", $contents) as $line => $content) {
                if ($callback($content)) {
                    return $line + 1;
                }
            }

            return 0;
        };
    }
}
