<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Storage;

use Generator;
use PHPUnit\Architecture\Services\ServiceContainer;
use SplFileInfo;

final class Filesystem
{
    public static function getBaseDir(): string
    {
        $dir = __DIR__ . '/../../';

        if (file_exists($dir . DIRECTORY_SEPARATOR . 'vendor')) {
            return $dir;
        }

        return $dir . '../../../';
    }

    /**
     * @return Generator<string>
     */
    public static function files(): Generator
    {
        /** @var SplFileInfo[] $paths */
        $paths = ServiceContainer::$finder;

        foreach ($paths as $path) {
            if ($path->isFile()) {
                yield $path->getRealPath();
            }
        }
    }
}
