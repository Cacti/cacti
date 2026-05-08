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

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\FileOptionsResolver;
use Overtrue\PHPLint\Event\AfterCheckingEvent;
use Overtrue\PHPLint\Event\AfterCheckingInterface;
use Overtrue\PHPLint\Output\ChainOutput;
use Overtrue\PHPLint\Output\FormatResolver;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class OutputFormat implements EventSubscriberInterface, AfterCheckingInterface
{
    private array $handlers = [];

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'initFormat',
            AfterCheckingEvent::class => 'afterChecking',
        ];
    }

    public function initFormat(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command instanceof LintCommand) {
            // this extension must be only available for lint command
            return;
        }

        $input = $event->getInput();

        if (true === $input->hasParameterOption(['--no-configuration'], true)) {
            $configResolver = new ConsoleOptionsResolver($input);
        } else {
            $configResolver = new FileOptionsResolver($input);
        }

        $this->handlers = (new FormatResolver())->resolve($configResolver, $event->getOutput());
    }

    public function afterChecking(AfterCheckingEvent $event): void
    {
        $outputHandler = new ChainOutput($this->handlers);
        $outputHandler->format($event->getArgument('results'));
    }
}
