<?php

declare(strict_types=1);

include __DIR__ . '/demo_base.php';

use Jfcherng\Diff\DiffHelper;
use Jfcherng\Utility\CliColor;

$colorStyles = [
    'section' => ['f_black', 'b_cyan'],
];

$manyNewlines = "\n\n\n\n";

echo CliColor::color("Unified Diff\n============", $colorStyles['section']) . "\n\n";

// generate a unified diff
$unifiedResult = DiffHelper::calculate(
    $oldString,
    $newString,
    'Unified',
    $diffOptions,
    $rendererOptions,
);

echo $unifiedResult . $manyNewlines;

echo CliColor::color("Context Diff\n============", $colorStyles['section']) . "\n\n";

// generate a context diff
$contextResult = DiffHelper::calculate(
    $oldString,
    $newString,
    'Context',
    $diffOptions,
    $rendererOptions,
);

echo $contextResult . $manyNewlines;
