<?php

use Overtrue\PHPLint\Output\ChainOutput;
use Overtrue\PHPLint\Output\ConsoleOutput;
use Overtrue\PHPLint\Output\ConsoleOutputInterface;
use Overtrue\PHPLint\Output\JsonOutput;
use Overtrue\PHPLint\Output\JunitOutput;
use Overtrue\PHPLint\Output\LinterOutput;
use Overtrue\PHPLint\Output\OutputInterface;

function dataSource(): Generator
{
    $classes = [
        ChainOutput::class,
        ConsoleOutput::class,
        ConsoleOutputInterface::class,
        JsonOutput::class,
        JunitOutput::class,
        LinterOutput::class,
        OutputInterface::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
