<?php

use Overtrue\PHPLint\Extension\OutputFormat;
use Overtrue\PHPLint\Extension\ProgressBar;
use Overtrue\PHPLint\Extension\ProgressIndicator;
use Overtrue\PHPLint\Extension\ProgressPrinter;

function dataSource(): Generator
{
    $classes = [
        OutputFormat::class,
        ProgressBar::class,
        ProgressIndicator::class,
        ProgressPrinter::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
