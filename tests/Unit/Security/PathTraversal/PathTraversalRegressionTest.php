<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Consolidated path traversal / local file inclusion regression tests.
 *
 * Combines the previously separate per-advisory test files for:
 * GHSA-g37j-39f4-6r4j and GHSA-mjvw-mhj5-9jcj.
 *
 * Both advisories target the same reports "format_file" sink: the POST-supplied
 * value was written verbatim into reports.format_file and later concatenated
 * onto CACTI_PATH_FORMATS in reports_load_format_file without a directory
 * component check, letting a traversal sequence escape the formats directory
 * and read arbitrary files the web user could access. The fix calls basename()
 * at both save and load sites, and reports_load_format_file additionally
 * canonicalizes via validate_path_within() before any file IO.
 *
 * Each test below keeps its original GHSA identifier in its description
 * so the advisory it guards against remains traceable.
 */

$reportsSource     = file_get_contents(__DIR__ . '/../../../../lib/reports.php');
$htmlReportsSource = file_get_contents(__DIR__ . '/../../../../lib/html_reports.php');

// GHSA-g37j / GHSA-mjvw: reports_load_format_file anchors the path to the formats
// directory via validate_path_within() before any file IO runs.
test('GHSA-g37j / GHSA-mjvw: reports_load_format_file anchors the path to the formats directory', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	expect($start)->not->toBeFalse();

	$end  = strpos($reportsSource, "\n}\n", $start);
	$body = substr($reportsSource, $start, $end - $start);

	// The traversal guard must resolve $format_file inside
	// <base_path>/formats before any file IO runs against it.
	expect($body)->toContain("validate_path_within(\$format_file, \$config['base_path'] . '/formats')");
});

test('GHSA-g37j / GHSA-mjvw: invalid format paths are logged and rejected', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	expect($body)->toContain('if ($validated === false) {');
	expect($body)->toContain("cacti_log('ERROR: Invalid format file path rejected: '");
	expect($body)->toContain('return false;');
});

test('GHSA-g37j / GHSA-mjvw: validation precedes any file read', function () use ($reportsSource) {
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

test('GHSA-g37j / GHSA-mjvw: validated path is re-assigned before use', function () use ($reportsSource) {
	$start = strpos($reportsSource, 'function reports_load_format_file(');
	$end   = strpos($reportsSource, "\n}\n", $start);
	$body  = substr($reportsSource, $start, $end - $start);

	// The canonicalised path must overwrite $format_file so downstream
	// file() sees the resolved form, not the attacker-supplied one.
	expect($body)->toContain('$format_file = $validated;');
});

// GHSA-mjvw: basename() must strip directory traversal at both the save and load
// sites for format_file.
test('GHSA-mjvw: basename strips directory traversal from format_file', function () {
	expect(basename('../../../etc/passwd'))->toBe('passwd');
	expect(basename('../lib/auth.php'))->toBe('auth.php');
	expect(basename('/absolute/path/cacti_group.format'))->toBe('cacti_group.format');
});

test('GHSA-mjvw: basename preserves a legitimate format file name', function () {
	expect(basename('cacti_group.format'))->toBe('cacti_group.format');
	expect(basename('my_report.format'))->toBe('my_report.format');
});

test('GHSA-mjvw: html_reports.php sanitizes format_file before save', function () use ($htmlReportsSource) {
	expect($htmlReportsSource)->toContain("basename((string) \$post['format_file'])");
	expect($htmlReportsSource)->not->toMatch('/\$save\[[\'"]format_file[\'"]\]\s*=\s*\$post\[[\'"]format_file[\'"]\];/');
});

test('GHSA-mjvw: reports_load_format_file applies basename before concatenation', function () use ($reportsSource) {
	$openPos = strpos($reportsSource, 'function reports_load_format_file');
	expect($openPos)->not->toBeFalse();

	$slice = substr($reportsSource, $openPos, 1200);

	$basenamePos = strpos($slice, 'basename($format_file)');
	$concatPos   = strpos($slice, 'CACTI_PATH_FORMATS');

	expect($basenamePos)->not->toBeFalse();
	expect($concatPos)->not->toBeFalse();
	expect($basenamePos)->toBeLessThan($concatPos);
});
