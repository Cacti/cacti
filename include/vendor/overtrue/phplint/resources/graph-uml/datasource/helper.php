<?php

use Overtrue\PHPLint\Helper\DebugFormatterHelper;
use Overtrue\PHPLint\Helper\ProcessHelper;

function dataSource(): Generator
{
    $classes = [
        DebugFormatterHelper::class,
        ProcessHelper::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
