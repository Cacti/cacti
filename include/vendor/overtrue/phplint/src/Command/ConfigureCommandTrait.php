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

namespace Overtrue\PHPLint\Command;

use Overtrue\PHPLint\Configuration\OptionDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
trait ConfigureCommandTrait
{
    protected function configureCommand(Command $command): void
    {
        $command
            ->addArgument(
                'path',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Path to file or directory to lint (<comment>default: working directory</comment>)'
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Path to file or directory to exclude from linting'
            )
            ->addOption(
                'extensions',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Check only files with selected extensions'
            )
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_REQUIRED,
                'Number of paralleled jobs to run'
            )
            ->addOption(
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'Read configuration from config file',
                OptionDefinition::DEFAULT_CONFIG_FILE
            )
            ->addOption(
                'no-configuration',
                null,
                InputOption::VALUE_NONE,
                'Ignore default configuration file (<comment>' . OptionDefinition::DEFAULT_CONFIG_FILE . '</comment>)'
            )
            ->addOption(
                'cache',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the cache directory (<comment>Deprecated option, use "cache-dir" instead</comment>)'
            )
            ->addOption(
                'cache-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the cache directory'
            )
            ->addOption(
                'cache-ttl',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit cached data for a period of time'
                . ' (<info>>0: time to live in seconds</info>)',
                OptionDefinition::DEFAULT_CACHE_TTL
            )
            ->addOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'Ignore cached data'
            )
            ->addOption(
                'progress',
                'p',
                InputOption::VALUE_REQUIRED,
                'Show the progress output'
            )
            ->addOption(
                'no-progress',
                null,
                InputOption::VALUE_NONE,
                'Hide the progress output'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Generate an output to the specified path (<comment>default: ' . OptionDefinition::DEFAULT_STANDARD_OUTPUT_LABEL . '</comment>)'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Format of requested reports'
            )
            ->addOption(
                'warning',
                'w',
                InputOption::VALUE_NONE,
                'Also show warnings'
            )
            ->addOption(
                'memory-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Memory limit for analysis'
            )
            ->addOption(
                'ignore-exit-code',
                null,
                InputOption::VALUE_NONE,
                'Ignore exit codes so there are no "failure" exit code even when no files processed'
            )
            ->addOption(
                'bootstrap',
                null,
                InputOption::VALUE_REQUIRED,
                'A PHP script that is included before the linter run'
            );
    }
}
