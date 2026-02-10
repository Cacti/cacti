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

namespace Overtrue\PHPLint\Tests\Configuration;

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\FileOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Configuration\Resolver;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(FileOptionsResolver::class)]
final class YamlConfigTest extends TestCase
{
    public function testInvalidYamlFile(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $dispatcher = new EventDispatcher([]);
        $definition = (new LintCommand($dispatcher))->getDefinition();

        $arguments = ['--configuration' => 'tests/Configuration/invalid_format.yaml'];
        $input = new ArrayInput($arguments, $definition);

        new FileOptionsResolver($input);
    }

    #[DataProvider('commandInputProvider')]
    public function testYamlConfig(array $arguments, callable $fetchExpected): void
    {
        $dispatcher = new EventDispatcher([]);
        $definition = (new LintCommand($dispatcher))->getDefinition();

        $input = new ArrayInput($arguments, $definition);

        $resolver = new FileOptionsResolver($input);

        $this->assertSame($fetchExpected($resolver, $arguments), $resolver->getOptions());
    }

    public static function commandInputProvider(): array
    {
        $baseConfDir = 'tests/Configuration/';

        return [
            'only default values' => [['--configuration' => $baseConfDir . 'empty.yaml'], __CLASS__ . '::expectedOnlyDefaults'],
            'only path modified' => [['--configuration' => $baseConfDir . 'paths.yaml'], __CLASS__ . '::expectedPathModified'],
            'only jobs modified' => [['--configuration' => $baseConfDir . 'jobs.yaml'], __CLASS__ . '::expectedJobsModified'],
            'output to JSON format on Stdout' => [['--configuration' => $baseConfDir . 'log-json.yaml'], __CLASS__ . '::expectedJsonOutputFormat'],
            'output to XML format on File' => [['--configuration' => $baseConfDir . 'log-junit.yaml'], __CLASS__ . '::expectedXmlOutputFormat'],
            'with external readable configuration' => [['path' => '../../examples', '--configuration' => $baseConfDir . 'custom.yaml'], __CLASS__ . '::expectedExternalConfigReadable'],
        ];
    }

    protected static function expectedOnlyDefaults(Resolver $resolver): array
    {
        return self::getExpectedValues($resolver);
    }

    protected static function expectedPathModified(Resolver $resolver): array
    {
        $expected = self::getExpectedValues($resolver);
        $expected['path'] = ['../tests/Cache', '../tests/Finder'];  // see 'paths.yaml' contents
        return $expected;
    }

    protected static function expectedJobsModified(Resolver $resolver): array
    {
        $expected = self::getExpectedValues($resolver);
        $expected['jobs'] = 10;  // see 'jobs.yaml' contents
        return $expected;
    }

    protected static function expectedJsonOutputFormat(Resolver $resolver, array $arguments): array
    {
        $expected = self::getExpectedValues($resolver);
        $expected['output'] = OptionDefinition::DEFAULT_STANDARD_OUTPUT;  // see 'log-json.yaml' contents
        $expected['format'] = ['json'];
        return $expected;
    }

    protected static function expectedXmlOutputFormat(Resolver $resolver, array $arguments): array
    {
        $expected = self::getExpectedValues($resolver);
        $expected['output'] = '/tmp/phplint-results.xml';    // see 'log-junit.yaml' contents
        $expected['format'] = ['junit'];
        return $expected;
    }

    protected static function expectedExternalConfigReadable(Resolver $resolver, array $arguments): array
    {
        // expected command line and yaml file arguments/options combination (see 'custom.yaml' contents)
        $expected = self::getExpectedValues($resolver);
        $expected['path'] = ['../../examples'];
        $expected['jobs'] = 10;
        $expected['exclude'] = ['vendor', 'tests'];
        $expected['warning'] = true;
        $expected['memory-limit'] = -1;
        $expected['no-cache'] = true;
        return $expected;
    }

    protected static function getExpectedValues(Resolver $resolver): array
    {
        $factory = $resolver->factory();
        return $factory->resolve();
    }
}
