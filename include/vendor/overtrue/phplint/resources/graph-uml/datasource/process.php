<?php

use Overtrue\PHPLint\Process\LintProcess;
use Overtrue\PHPLint\Process\LintProcessItem;

function dataSource(): Generator
{
    $classes = [
        LintProcess::class,
        LintProcessItem::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
