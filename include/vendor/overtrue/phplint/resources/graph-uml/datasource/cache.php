<?php

use Overtrue\PHPLint\Cache;

function dataSource(): Generator
{
    $classes = [
        Cache::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
