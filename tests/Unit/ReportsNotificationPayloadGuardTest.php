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

$getReportsNotificationSource = static function () : string {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/reports.php');

	expect($source)->not->toBeFalse('Failed to read lib/reports.php');

	return $source;
};

$findReportsPattern = static function (string $pattern, string $source, int $offset, string $label) : int {
	$matched = preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE, $offset);
	expect($matched)->toBe(1, "$label must exist in lib/reports.php");

	return $matches[0][1];
};

test('queued report notifications validate the decoded payload before iteration', function () use ($getReportsNotificationSource, $findReportsPattern) {
	$source = $getReportsNotificationSource();

	$decode = $findReportsPattern('/\$notifications\s*=\s*json_decode\s*\(/', $source, 0, 'notification decode');
	$guard  = $findReportsPattern('/if\s*\(\s*!is_array\s*\(\s*\$notifications\s*\)\s*\)/', $source, $decode, 'payload guard');
	$loop   = $findReportsPattern('/foreach\s*\(\s*\$notifications\s+as\s+\$type\s*=>\s*\$data\s*\)/', $source, $decode, 'notification loop');

	expect($decode)->toBeLessThan($guard)
		->and($guard)->toBeLessThan($loop);
});

test('each notification entry is validated before dispatch', function () use ($getReportsNotificationSource, $findReportsPattern) {
	$source = $getReportsNotificationSource();

	$loop   = $findReportsPattern('/foreach\s*\(\s*\$notifications\s+as\s+\$type\s*=>\s*\$data\s*\)/', $source, 0, 'notification loop');
	$guard  = $findReportsPattern('/if\s*\(\s*!is_array\s*\(\s*\$data\s*\)\s*\)/', $source, $loop, 'entry guard');
	$branch = $findReportsPattern('/switch\s*\(\s*\$type\s*\)/', $source, $loop, 'dispatch switch');

	expect($loop)->toBeLessThan($guard)
		->and($guard)->toBeLessThan($branch);
});
