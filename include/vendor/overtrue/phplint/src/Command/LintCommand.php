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

use Overtrue\PHPLint\Client;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\FileOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Linter;
use Overtrue\PHPLint\Output\LinterOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Throwable;

use function array_unshift;
use function count;
use function microtime;

/**
 * @author Overtrue
 * @author Laurent Laville (since v9.0)
 */
final class LintCommand extends Command
{
    use ConfigureCommandTrait;

    private LinterOutput $results;

    public function __construct(private readonly EventDispatcherInterface $dispatcher, string $name = 'lint')
    {
        parent::__construct($name);
        $this->results = new LinterOutput([], new SymfonyFinder());
    }

    public function getResults(): LinterOutput
    {
        return $this->results;
    }

    protected function configure(): void
    {
        $this->setDescription('Files syntax check only');
        $this->configureCommand($this);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // initializes correctly command and path arguments when lint is set as default command
        $cmd = $input->getArgument('command');
        $paths = $input->getArgument('path');
        if ($cmd !== $this->getName()) {
            array_unshift($paths, $cmd);
        }
        $input->setArgument('path', $paths);
        $input->setArgument('command', $this->getName());
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        if (true === $input->hasParameterOption(['--no-configuration'], true)) {
            $configResolver = new ConsoleOptionsResolver($input);
        } else {
            $configResolver = new FileOptionsResolver($input);
        }

        $finder = (new Finder($configResolver))->getFiles();
        $linter = new Linter(
            $configResolver,
            $this->dispatcher,
            new Client($this->getApplication()),
            $this->getHelperSet(),
            $output
        );
        $this->results = $linter->lintFiles($finder, $startTime);

        $data = $this->results->getFailures();

        if ($configResolver->getOption(OptionDefinition::IGNORE_EXIT_CODE)) {
            return self::SUCCESS;
        }

        if (count($this->results) === 0 || count($data)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
