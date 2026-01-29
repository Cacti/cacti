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

namespace Overtrue\PHPLint\Tests\Benchmark\Console\Command;

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Console\Application;
use Overtrue\PHPLint\Event\EventDispatcher;
use PhpBench\Attributes as Bench;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

use function array_merge;
use function dirname;

/**
 * @author Laurent Laville
 * @since Release 9.1.0
 */
#[Bench\OutputTimeUnit('milliseconds')]
#[Bench\Iterations(10)]
final class LintCommandBench
{
    /**
     * @throws Throwable
     */
    public function benchDefaultConfiguration(): void
    {
        $this->runCommand([]);
    }

    /**
     * @throws Throwable
     */
    public function benchJobs10(): void
    {
        $this->runCommand(['--jobs' => 10]);
    }

    /**
     * @throws Throwable
     */
    public function benchJobs100(): void
    {
        $this->runCommand(['--jobs' => 100]);
    }

    /**
     * @throws Throwable
     */
    public function benchJobs1000(): void
    {
        $this->runCommand(['--jobs' => 1000]);
    }

    /**
     * @throws Throwable
     */
    private function runCommand(array $arguments): void
    {
        $arguments = array_merge([
            'path' => [dirname(__DIR__, 4) . '/vendor-bin/phpunit/vendor/phpunit/phpunit/src'],
            '--no-cache' => true,
            '--no-configuration' => true,
        ], $arguments);

        $dispatcher = new EventDispatcher([]);

        $defaultCommand = new LintCommand($dispatcher);

        $application = new Application();
        $application->add($defaultCommand);
        $application->setDefaultCommand($defaultCommand->getName());
        $application->setDispatcher($dispatcher);

        $commandTester = new CommandTester($defaultCommand);
        $commandTester->execute($arguments);
    }
}
