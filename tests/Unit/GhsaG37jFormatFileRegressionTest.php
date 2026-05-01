<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$reportsSource = file_get_contents(__DIR__ . '/../../lib/reports.php');

test('GHSA-g37j-39f4-6r4j / GHSA-mjvw-mhj5-9jcj: reports_load_format_file anchors the path to the formats directory', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	expect($start)->not->toBeFalse();

	$end  = strpos($reportsSource, "\n}\n", $start);
	$body = substr($reportsSource, $start, $end - $start);

	// The traversal guard must resolve $format_file inside
	// <base_path>/formats before any file IO runs against it.
	expect($body)->toContain("validate_path_within(\$format_file, \$config['base_path'] . '/formats')");
});

test('GHSA-g37j-39f4-6r4j / GHSA-mjvw-mhj5-9jcj: invalid format paths are logged and rejected', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	expect($body)->toContain('if ($validated === false) {');
	expect($body)->toContain("cacti_log('ERROR: Invalid format file path rejected: '");
	expect($body)->toContain('return false;');
});

test('GHSA-g37j-39f4-6r4j / GHSA-mjvw-mhj5-9jcj: validation precedes any file read', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	$validatePos = strpos($body, 'validate_path_within(');
	$filePos     = strpos($body, 'file($format_file)');
	$existsPos   = strpos($body, 'file_exists($format_file)');

	expect($validatePos)->not->toBeFalse();
	expect($filePos)->not->toBeFalse();
	expect($existsPos)->not->toBeFalse();
	expect($validatePos)->toBeLessThan($existsPos);
	expect($validatePos)->toBeLessThan($filePos);
});

test('GHSA-g37j-39f4-6r4j / GHSA-mjvw-mhj5-9jcj: validated path is re-assigned before use', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	// The canonicalised path must overwrite $format_file so downstream
	// file() sees the resolved form, not the attacker-supplied one.
	expect($body)->toContain('$format_file = $validated;');
});
