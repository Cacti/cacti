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

namespace Overtrue\PHPLint\Extension;

use LogicException;
use Overtrue\PHPLint\Event\AfterCheckingEvent;
use Overtrue\PHPLint\Event\AfterCheckingInterface;
use Overtrue\PHPLint\Event\AfterLintFileEvent;
use Overtrue\PHPLint\Event\AfterLintFileInterface;
use Overtrue\PHPLint\Event\BeforeCheckingEvent;
use Overtrue\PHPLint\Event\BeforeCheckingInterface;
use Overtrue\PHPLint\Output\ConsoleOutputInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function get_class;
use function sprintf;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class ProgressPrinter implements
    EventSubscriberInterface,
    BeforeCheckingInterface,
    AfterCheckingInterface,
    AfterLintFileInterface
{
    private ConsoleOutputInterface $output;

    private int $maxSteps = 0;
    private bool $hasProcessHelper;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'initProgress',
            BeforeCheckingEvent::class => 'beforeChecking',
            AfterCheckingEvent::class => 'afterChecking',
            AfterLintFileEvent::class => 'afterLintFile',
        ];
    }

    /**
     * Initialize progress printer extension (default legacy behavior)
     */
    public function initProgress(ConsoleCommandEvent $event): void
    {
        $this->hasProcessHelper = $event->getCommand()->getHelperSet()->has('process');

        $output = $event->getOutput();

        if (!$output instanceof ConsoleOutputInterface) {
            throw new LogicException(
                sprintf(
                    'Extension %s must implement %s',
                    get_class($this),
                    ConsoleOutputInterface::class
                )
            );
        }

        $this->output = $output;
    }

    public function beforeChecking(BeforeCheckingEvent $event): void
    {
        $this->maxSteps = $event->getArgument('fileCount');
    }

    public function afterChecking(AfterCheckingEvent $event): void
    {
        $this->output->writeln('');
    }

    public function afterLintFile(AfterLintFileEvent $event): void
    {
        if ($this->hasProcessHelper && $this->output->getVerbosity() == OutputInterface::VERBOSITY_VERY_VERBOSE) {
            // ProgressPrinter extension make some noise that break output when ProcessHelper is active in verbose level 2
            return;
        }

        $this->output->progressPrinterAdvance(
            $this->maxSteps,
            $event->getArgument('status'),
            $event->getArgument('file')
        );
    }
}
