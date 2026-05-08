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

namespace Overtrue\PHPLint\Output;

use Overtrue\PHPLint\Configuration\Resolver;
use Symfony\Component\Console\Output\ConsoleOutputInterface as SymfonyConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

use function array_key_exists;
use function array_values;
use function class_exists;
use function fopen;

use const STDOUT;

/**
 * @author Laurent Laville
 * @since Release 9.4.0
 */
final class FormatResolver
{
    private const FORMATTERS = [
        'checkstyle' => CheckstyleOutput::class,
        'console' => ConsoleOutput::class,
        'json' => JsonOutput::class,
        'junit' => JunitOutput::class,
    ];

    /**
     * @return OutputInterface[]
     */
    public function resolve(Resolver $configResolver, SymfonyOutputInterface $output): array
    {
        $decorated = $output->isDecorated();

        $filename = $configResolver->getOption('output');
        if ($filename) {
            $stream = fopen($filename, 'w');
            $decorated = false;
        } else {
            $errOutput = $output instanceof SymfonyConsoleOutputInterface ? $output->getErrorOutput() : $output;
            if ($errOutput instanceof StreamOutput) {
                $stream = $errOutput->getStream();
            } else {
                $stream = STDOUT;
            }
        }

        $requestedFormats = $configResolver->getOption('format');

        $handlers = [];

        foreach ($requestedFormats as $requestedFormat) {
            if (array_key_exists($requestedFormat, self::FORMATTERS)) {
                // use built-in formatter
                $formatterClass = self::FORMATTERS[$requestedFormat];
                $formatter = new $formatterClass($stream, $output->getVerbosity(), $decorated, $output->getFormatter());
                $handlers[$formatter->getName()] = $formatter;
                continue;
            }

            if (class_exists($requestedFormat)) {
                // try to load custom/external formatter
                $formatter = new $requestedFormat($stream, $output->getVerbosity(), $decorated, $output->getFormatter());

                if (!$formatter instanceof OutputInterface) {
                    // skip invalid instance that does not implement contract
                    continue;
                }
                $handlers[$formatter->getName()] = $formatter;
            }
        }

        // Be sure to always have console output printed first (@see \Overtrue\PHPLint\Output\ChainOutput::format)
        if (isset($handlers['console'])) {
            $consoleHandler = $handlers['console'];
            unset($handlers['console']);

            $handlers = array_values($handlers);
            $handlers[] = $consoleHandler;
        }

        return $handlers;
    }
}
