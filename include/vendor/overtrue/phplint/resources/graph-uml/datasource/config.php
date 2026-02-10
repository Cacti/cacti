<?php

use Overtrue\PHPLint\Configuration\AbstractOptionsResolver;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Configuration\FileOptionsResolver;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Configuration\Options;
use Overtrue\PHPLint\Configuration\OptionsFactory;
use Overtrue\PHPLint\Configuration\Resolver;

function dataSource(): Generator
{
    $classes = [
        AbstractOptionsResolver::class,
        ConsoleOptionsResolver::class,
        FileOptionsResolver::class,
        OptionDefinition::class,
        Options::class,
        OptionsFactory::class,
        Resolver::class,
    ];
    foreach ($classes as $class) {
        yield $class;
    }
}
