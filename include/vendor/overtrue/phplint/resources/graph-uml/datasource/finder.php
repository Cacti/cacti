<?php

use Overtrue\PHPLint\Finder;

function dataSource(): Generator
{
    $classes = [
        Finder::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
