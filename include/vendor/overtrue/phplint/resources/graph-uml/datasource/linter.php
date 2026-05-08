<?php

use Overtrue\PHPLint\Linter;

function dataSource(): Generator
{
    $classes = [
        Linter::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
