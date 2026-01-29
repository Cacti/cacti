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
use Symfony\Component\Console\Helper\ProgressIndicator as ProgressIndicatorHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @link https://symfony.com/doc/current/components/console/helpers/progressindicator.html
 *
 * @author Laurent Laville
 * @since Release 9.1.0
 */
final class ProgressIndicator implements
    EventSubscriberInterface,
    BeforeCheckingInterface,
    AfterCheckingInterface,
    AfterLintFileInterface
{
    private ConsoleOutputInterface $output;
    private bool $hasProcessHelper;

    private ?ProgressIndicatorHelper $progressIndicator;
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
     * Initialize progress indicator extension
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

        if ($this->hasProcessHelper && $this->output->isVeryVerbose()) {
            $this->progressIndicator = null;
        } else {
            $this->progressIndicator = new ProgressIndicatorHelper(
                $output,
                'normal',
                100,
                ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']
            );
        }
    }

    public function beforeChecking(BeforeCheckingEvent $event): void
    {
        if ($this->hasProcessHelper && $this->output->isVeryVerbose()) {
            // ProgressIndicator extension make some noise that break output when ProcessHelper is active
            return;
        }

        $this->progressIndicator?->start('Linting files ...');
    }

    public function afterChecking(AfterCheckingEvent $event): void
    {
        $this->progressIndicator?->finish('Finished');
    }

    public function afterLintFile(AfterLintFileEvent $event): void
    {
        if ($this->hasProcessHelper && $this->output->isVeryVerbose()) {
            // ProgressIndicator extension make some noise that break output when ProcessHelper is active
            return;
        }

        $this->progressIndicator?->advance();
    }
}
