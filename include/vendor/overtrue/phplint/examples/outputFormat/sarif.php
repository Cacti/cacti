<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * @author Laurent Laville
 * @since Release 9.4.0
 */

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Linter;
use Overtrue\PHPLint\Output\SarifOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

if ($argc > 1 && file_exists($argv[1])) {
    // specify autoloader that should be used to load resources
    require_once $argv[1];
}

$outputClass = $argv[2] ?? '';

if (empty($outputClass) || !class_exists($outputClass)) {
    // fallback to built-in SARIF output class
    $outputClass = SarifOutput::class;
}

$converterClass = $argv[3] ?? '';

$isVerbose = array_search('-v', $argv) !== false;

if (empty($converterClass) || !class_exists($converterClass)) {
    $converter = null;
} else {
    $converter = new $converterClass($isVerbose);
}

$dispatcher = new EventDispatcher([]);

$arguments = [
    'path' => [__DIR__ . '/../../src', __DIR__ . '/../../tests'],
    '--no-configuration' => true,
];
$command = new LintCommand($dispatcher);
$input = new ArrayInput($arguments, $command->getDefinition());
$configResolver = new ConsoleOptionsResolver($input);

$finder = new Finder($configResolver);
$linter = new Linter($configResolver, $dispatcher);
$results = $linter->lintFiles($finder->getFiles());

$output = new $outputClass(STDOUT, OutputInterface::VERBOSITY_VERBOSE, null, null, $converter);
if ($output instanceof OutputInterface) {
    $output->format($results);
}
