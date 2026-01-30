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
use Overtrue\PHPLint\Event\BeforeLintFileEvent;
use Overtrue\PHPLint\Event\BeforeLintFileInterface;
use Overtrue\PHPLint\Output\ConsoleOutputInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function get_class;
use function mb_strimwidth;
use function min;
use function sprintf;
use function strlen;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class ProgressBar implements
    EventSubscriberInterface,
    BeforeCheckingInterface,
    AfterCheckingInterface,
    BeforeLintFileInterface,
    AfterLintFileInterface
{
    private ConsoleOutputInterface $output;
    private bool $hasProcessHelper;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'initProgress',
            BeforeCheckingEvent::class => 'beforeChecking',
            AfterCheckingEvent::class => 'afterChecking',
            BeforeLintFileEvent::class => 'beforeLintFile',
            AfterLintFileEvent::class => 'afterLintFile',
        ];
    }

    /**
     * Initialize progress bar extension
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
        if ($this->hasProcessHelper && $this->output->isVeryVerbose()) {
            // ProgressBar extension make some noise that break output when ProcessHelper is active
            return;
        }
        $this->output->progressStart($event->getArgument('fileCount'));
    }

    public function afterChecking(AfterCheckingEvent $event): void
    {
        $this->output->progressFinish();
    }

    public function beforeLintFile(BeforeLintFileEvent $event): void
    {
        $this->output->progressMessage('Checking file ...');

        $filename = $event->getArgument('file')->getRelativePathname();
        $width = min(strlen($filename), 70);
        $this->output->progressMessage(mb_strimwidth($filename, -1 * $width, $width), 'filename');
    }

    public function afterLintFile(AfterLintFileEvent $event): void
    {
        if ($this->hasProcessHelper && $this->output->isVeryVerbose()) {
            // ProgressBar extension make some noise that break output when ProcessHelper is active
            return;
        }

        $this->output->progressAdvance();
    }
}
