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

namespace Overtrue\PHPLint\Tests\Finder;

use Iterator;
use LogicException;
use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Input\ArrayInput;

use function array_keys;
use function array_map;
use function dirname;
use function iterator_to_array;
use function str_replace;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
#[CoversClass(Finder::class)]
final class FinderTest extends TestCase
{
    public function testAllPhpFilesFoundShouldExists(): void
    {
        $dispatcher = new EventDispatcher([]);

        $basePath = dirname(__DIR__);

        $arguments = [
            OptionDefinition::PATH => [$basePath],
            '--no-configuration' => true,
            '--' . OptionDefinition::EXCLUDE => [],
            '--' . OptionDefinition::EXTENSIONS => ['php'],
        ];
        $definition = (new LintCommand($dispatcher))->getDefinition();
        $input = new ArrayInput($arguments, $definition);

        $configResolver = new ConsoleOptionsResolver($input);

        $finder = new Finder($configResolver);

        foreach ($finder->getFiles() as $file) {
            $this->assertFileExists($file->getRealPath());
        }
    }

    public function testAllPathShouldExistsAndReadable(): void
    {
        $this->expectException(LogicException::class);

        $dispatcher = new EventDispatcher([]);

        $basePath = dirname(__DIR__) . '/fixtures/missing_dir';

        $arguments = [
            OptionDefinition::PATH => [$basePath],
            '--no-configuration' => true,
        ];
        $definition = (new LintCommand($dispatcher))->getDefinition();
        $input = new ArrayInput($arguments, $definition);

        $configResolver = new ConsoleOptionsResolver($input);

        $finder = new Finder($configResolver);
        count($finder->getFiles());
    }

    public function testSearchPhpFilesWithCondition(): void
    {
        $dispatcher = new EventDispatcher([]);

        $basePath = dirname(__DIR__);

        $arguments = [
            OptionDefinition::PATH => [$basePath],
            '--no-configuration' => true,
            '--' . OptionDefinition::EXCLUDE => ['fixtures', 'Benchmark'],
            '--' . OptionDefinition::EXTENSIONS => ['php']
        ];
        $definition = (new LintCommand($dispatcher))->getDefinition();
        $input = new ArrayInput($arguments, $definition);

        $configResolver = new ConsoleOptionsResolver($input);

        $finder = new Finder($configResolver);

        $this->assertEqualsCanonicalizing(
            [
                'Cache/CacheTest.php',
                'Configuration/ConsoleConfigTest.php',
                'Configuration/YamlConfigTest.php',
                'EndToEnd/LintCommandTest.php',
                'EndToEnd/Reserved@Keywords.php',
                'Finder/FinderTest.php',
                'Output/OutputTest.php',
                'TestCase.php',
            ],
            $this->getRelativePathFiles($finder->getFiles()->getIterator(), $basePath)
        );
    }

    private function getRelativePathFiles(Iterator $iterator, string $basePath): array
    {
        return array_map(
            function (string $filename) use ($basePath) {
                return str_replace($basePath . '/', '', $filename);
            },
            array_keys(iterator_to_array($iterator))
        );
    }
}
