#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
	fwrite(STDERR, "usage: check_coverage.php <clover.xml> <source-file> <minimum-percent>\n");

	exit(2);
}

[$script, $report, $source, $minimum] = $argv;
$minimum                              = filter_var($minimum, FILTER_VALIDATE_FLOAT);

if ($minimum === false || $minimum < 0 || $minimum > 100) {
	fwrite(STDERR, "Coverage minimum must be a number between 0 and 100.\n");

	exit(2);
}

if (!is_file($report)) {
	fwrite(STDERR, "Coverage report '$report' was not generated.\n");

	exit(1);
}

$xml = simplexml_load_file($report);

if ($xml === false) {
	fwrite(STDERR, "Coverage report '$report' is not valid XML.\n");

	exit(1);
}

$source = str_replace('\\', '/', $source);
$files  = $xml->xpath('//file');
$found  = null;

foreach (is_array($files) ? $files : [] as $file) {
	$name = str_replace('\\', '/', (string) $file['name']);

	if ($name === $source || str_ends_with($name, '/' . $source)) {
		$found = $file;

		break;
	}
}

if ($found === null || !isset($found->metrics)) {
	fwrite(STDERR, "Coverage report does not contain metrics for '$source'.\n");

	exit(1);
}

$statements = (int) $found->metrics['statements'];
$covered    = (int) $found->metrics['coveredstatements'];

if ($statements < 1) {
	fwrite(STDERR, "Coverage report contains no executable statements for '$source'.\n");

	exit(1);
}

$percentage = $covered / $statements * 100;
printf("%s line coverage: %.2f%% (%d/%d, minimum %.2f%%)\n", $source, $percentage, $covered, $statements, $minimum);

exit($percentage + 1e-9 >= $minimum ? 0 : 1);
