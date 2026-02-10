<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint;

use ArrayIterator;
use JsonSerializable;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Configuration\Resolver;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Symfony\Component\Finder\SplFileInfo;

use function implode;
use function is_dir;
use function is_file;
use function realpath;
use function sprintf;

/**
 * Finder allows to find files and directories.
 *
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class Finder implements JsonSerializable
{
    private array $paths;
    private array $excludes;
    private array $extensions;

    public function __construct(Resolver $configResolver)
    {
        $this->paths = $configResolver->getOption(OptionDefinition::PATH);
        $this->excludes = $configResolver->getOption(OptionDefinition::EXCLUDE);
        $this->extensions = $configResolver->getOption(OptionDefinition::EXTENSIONS);
    }

    public function jsonSerialize(): array
    {
        return [
            'paths' => $this->paths,
            'excludes' => $this->excludes,
            'extensions' => $this->extensions,
        ];
    }

    public function getFiles(): SymfonyFinder
    {
        $finder = new SymfonyFinder();
        foreach ($this->paths as $path) {
            if (is_dir($path)) {
                $finder->append($this->getFilesFromDir($path));
            } elseif (is_file($path)) {
                $iterator = new ArrayIterator();
                // @phpstan-ignore-next-line
                $iterator[$path] = new SplFileInfo($path, $path, $path);
                $finder->append($iterator);
            }
        }
        return $finder;
    }
    private function getFilesFromDir(string $dir): SymfonyFinder
    {
        $finder = new SymfonyFinder();
        $finder->files()
            ->ignoreUnreadableDirs()
            ->filter(function (SplFileInfo $file) {
                return $file->isReadable();
            })
            ->name(sprintf('/\\.(%s)$/', implode('|', $this->extensions)))
            ->notPath($this->excludes)
            ->in(realpath($dir));

        return $finder;
    }
}
