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

namespace Overtrue\PHPLint\Helper;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function spl_object_hash;
use function sprintf;
use function str_replace;

/**
 * CREDITS to Symfony Console ProcessHelper,
 * that differ essentially by support of the start() method (asynchronous process) instead of run() method (synchronous process)
 * @link https://symfony.com/doc/current/components/console/helpers/processhelper.html
 *
 * @author Laurent Laville
 * @since Release 9.1.0
 */
final class ProcessHelper extends Helper
{
    public function getName(): string
    {
        return 'process';
    }

    public function start(
        OutputInterface $output,
        Process $process,
        ?callable $callback = null,
        array $env = [],
        int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE
    ): Process {
        $formatter = $this->getFormatter();

        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        if ($formatter && $verbosity <= $output->getVerbosity()) {
            $output->write($formatter->start(spl_object_hash($process), $this->escapeString($process->getCommandLine())));
        }

        if ($output->isDebug()) {
            $callback = $this->wrapCallback($formatter, $output, $process, $callback);
        }

        $process->start($callback, $env);

        return $process;
    }

    public function isTerminated(
        OutputInterface $output,
        Process $process,
        int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE
    ): bool {
        $formatter = $this->getFormatter();

        $ended = $process->isTerminated();

        if ($ended && $verbosity <= $output->getVerbosity()) {
            $message = $process->isSuccessful()
                ? 'Command ran successfully' :
                sprintf('%s Command did not run successfully', $process->getExitCode())
            ;
            $output->write($formatter->stop(spl_object_hash($process), $message, $process->isSuccessful()));
        }

        return $ended;
    }

    private function getFormatter(): ?DebugFormatterHelper
    {
        /** @var ?DebugFormatterHelper $formatter */
        $formatter =  $this->getHelperSet()->has('debug_formatter')
            ? $this->getHelperSet()->get('debug_formatter')
            : null
        ;
        return $formatter;
    }

    private function wrapCallback(
        DebugFormatterHelper $formatter,
        OutputInterface $output,
        Process $process,
        ?callable $callback = null
    ): callable {
        return function ($type, $buffer) use ($output, $process, $callback, $formatter) {
            $output->write(
                $formatter->progress(spl_object_hash($process), $this->escapeString($buffer), Process::ERR === $type)
            );

            if (null !== $callback) {
                $callback($type, $buffer);
            }
        };
    }

    private function escapeString(string $str): string
    {
        return str_replace('<', '\\<', $str);
    }
}
