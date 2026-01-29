<?php

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Linter;
use Symfony\Component\Console\Input\ArrayInput;

$dispatcher = new EventDispatcher([]);

$arguments = [
    'path' => [dirname(__DIR__) . '/src', dirname(__DIR__) . '/tests'],
    '--no-configuration' => true,
    '--no-cache' => true,
    '--exclude' => ['vendor'],
    '--extensions' => ['php'],
    '--warning' => true,
];
$command = new LintCommand($dispatcher);
$definition = $command->getDefinition();
$input = new ArrayInput($arguments, $definition);

$configResolver = new ConsoleOptionsResolver($input);

$finder = new Finder($configResolver);
$linter = new Linter($configResolver, $dispatcher);
$results = $linter->lintFiles($finder->getFiles());

var_dump("Files checked :", count($results));

var_dump("Errors detected :", $results->getErrors());

var_dump("Warnings detected :", $results->getWarnings());
