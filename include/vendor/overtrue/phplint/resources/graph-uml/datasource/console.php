<?php

use Overtrue\PHPLint\Command\ConfigureCommandTrait;
use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Console\Application;

function dataSource(): Generator
{
    $classes = [
        ConfigureCommandTrait::class,
        LintCommand::class,
        Application::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
