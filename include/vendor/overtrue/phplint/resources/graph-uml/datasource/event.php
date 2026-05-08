<?php

use Overtrue\PHPLint\Event\AfterCheckingEvent;
use Overtrue\PHPLint\Event\AfterCheckingInterface;
use Overtrue\PHPLint\Event\AfterLintFileEvent;
use Overtrue\PHPLint\Event\AfterLintFileInterface;
use Overtrue\PHPLint\Event\BeforeCheckingEvent;
use Overtrue\PHPLint\Event\BeforeCheckingInterface;
use Overtrue\PHPLint\Event\BeforeLintFileEvent;
use Overtrue\PHPLint\Event\BeforeLintFileInterface;
use Overtrue\PHPLint\Event\EventDispatcher;

function dataSource(): Generator
{
    $classes = [
        EventDispatcher::class,
        AfterCheckingEvent::class,
        AfterCheckingInterface::class,
        AfterLintFileEvent::class,
        AfterLintFileInterface::class,
        BeforeCheckingEvent::class,
        BeforeCheckingInterface::class,
        BeforeLintFileEvent::class,
        BeforeLintFileInterface::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
