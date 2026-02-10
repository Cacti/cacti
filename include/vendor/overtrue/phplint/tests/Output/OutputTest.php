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

namespace Overtrue\PHPLint\Tests\Output;

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Linter;
use Overtrue\PHPLint\Output\JunitOutput;
use Overtrue\PHPLint\Output\LinterOutput;
use Overtrue\PHPLint\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function fopen;
use function microtime;
use function rewind;

use const DIRECTORY_SEPARATOR;

/**
 * @author Laurent Laville
 * @since Release 9.5.3
 */
#[CoversClass(JunitOutput::class)]
final class OutputTest extends TestCase
{
    private LinterOutput $linterOutput;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher([]);

        $basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures';

        $arguments = [
            OptionDefinition::PATH => [$basePath],
            '--no-configuration' => true,
            '--no-cache' => true,
            '--' . OptionDefinition::WARNING => true,
            '--' . OptionDefinition::EXTENSIONS => ['php']
        ];
        $definition = (new LintCommand($dispatcher))->getDefinition();
        $input = new ArrayInput($arguments, $definition);

        $configResolver = new ConsoleOptionsResolver($input);

        $finder = new Finder($configResolver);

        $linter = new Linter($configResolver, $dispatcher);

        $startTime = microtime(true);
        $defaults = ['application_version' => ['short' => '9.x-dev', 'long' => '9.x-dev']];

        $this->linterOutput = $linter->lintFiles($finder->getFiles(), $startTime);
        $this->linterOutput->setContext($configResolver, $startTime, 2, $defaults);
    }

    public function testJunitOutput(): void
    {
        $stream = fopen('php://memory', 'w+');
        $output = new JunitOutput($stream, OutputInterface::VERBOSITY_VERBOSE, false);
        $output->format($this->linterOutput);

        rewind($stream);
        $xml = stream_get_contents($stream);

        $this->assertStringContainsString('syntax_error.php</error>', $xml);
        $this->assertStringContainsString('syntax_warning.php</error>', $xml);
    }
}
