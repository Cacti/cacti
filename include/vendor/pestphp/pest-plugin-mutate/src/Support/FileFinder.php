<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileFinder
{
    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $pathsToIgnore
     */
    public static function files(array $paths, array $pathsToIgnore): Finder
    {
        ['directories' => $dirs, 'files' => $files] = self::separateDirectoriesAndFiles($paths);

        $allPathsToIgnore = self::buildPathsToIgnore($pathsToIgnore, $dirs);

        return Finder::create()
            ->in($dirs)
            ->name('*.php')
            ->append($files)
            ->files()
            ->filter(fn (SplFileInfo $file): bool => array_filter($allPathsToIgnore, fn (string $pathToIgnore): bool => preg_match($pathToIgnore, $file->getRealPath()) === 1) === []);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array{directories: array<int, string>, files: array<int, SplFileInfo>}
     */
    private static function separateDirectoriesAndFiles(array $paths): array
    {
        $dirs = [];
        $files = [];
        foreach ($paths as $path) {
            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = getcwd().DIRECTORY_SEPARATOR.$path;
            }
            if (is_dir($path)) {
                $dirs[] = $path;
            } elseif (is_file($path)) {
                $file = new \SplFileInfo($path);
                $files[] = new SplFileInfo($file->getPathname(), $file->getPath(), $file->getFilename());
            }
        }

        return ['directories' => $dirs, 'files' => $files];
    }

    /**
     * @param  array<int, string>  $pathsToIgnore
     * @param  array<int, string>  $dirs
     * @return array<int, string>
     */
    private static function buildPathsToIgnore(array $pathsToIgnore, array $dirs): array
    {
        $allPathsToIgnore = [];

        foreach ($pathsToIgnore as $pathToIgnore) {
            if (! str_starts_with($pathToIgnore, DIRECTORY_SEPARATOR)) {
                foreach ($dirs as $dir) {
                    $allPathsToIgnore[] = $dir.DIRECTORY_SEPARATOR.$pathToIgnore;
                }
            }

            $allPathsToIgnore[] = (str_starts_with($pathToIgnore, getcwd()) ? '' : getcwd()).DIRECTORY_SEPARATOR.ltrim($pathToIgnore, DIRECTORY_SEPARATOR); // @phpstan-ignore-line
        }

        return array_map(function (string $pathToIgnore): string {
            if (! str_ends_with($pathToIgnore, '.php') && ! str_ends_with($pathToIgnore, DIRECTORY_SEPARATOR) && ! str_ends_with($pathToIgnore, '*')) {
                $pathToIgnore .= DIRECTORY_SEPARATOR;
            }

            $pattern = '/^'.preg_quote($pathToIgnore, '/').'/';

            $pattern = str_replace('\*\*', '.*', $pattern);

            return str_replace('\*', '[^'.preg_quote(DIRECTORY_SEPARATOR, '/').']*', $pattern);
        }, $allPathsToIgnore);
    }
}
