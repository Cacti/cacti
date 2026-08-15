<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Consolidated shell command injection regression tests.
 *
 * Combines the previously separate per-advisory test files for:
 * GHSA-7vw4-2r73-89g2, GHSA-c4qp-j9r9-fq24, and GHSA-g9c7-23p2-6hh3.
 *
 * Each test below keeps its original GHSA identifier in its description
 * so the advisory it guards against remains traceable.
 */

$pollerSource    = file_get_contents(__DIR__ . '/../../../../lib/poller.php');
$functionsSource = file_get_contents(__DIR__ . '/../../../../lib/functions.php');

// GHSA-7vw4: file_exists_2gb used to shell out; the $filename expansion was the bug.
test('GHSA-7vw4: file_exists_2gb uses PHP file_exists, not a shell', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	expect($start)->not->toBeFalse();

	$end  = strpos($pollerSource, "\n}\n", $start);
	$body = substr($pollerSource, $start, $end - $start);

	// PHP's file_exists handles >2GB files on every supported build since
	// PHP 5.0, so the shell fallback that used to be here is no longer
	// required and its $filename expansion was the actual bug.
	expect($body)->toContain('return @file_exists($filename);');
});

test('GHSA-7vw4: file_exists_2gb has no shell invocation', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	$end   = strpos($pollerSource, "\n}\n", $start);
	$body  = substr($pollerSource, $start, $end - $start);

	expect($body)->not->toContain('system(');
	expect($body)->not->toContain('shell_exec(');
	expect($body)->not->toContain('exec(');
	expect($body)->not->toContain('test -f');
});

test('GHSA-7vw4: file_exists_2gb body is a single-line delegation', function () use ($pollerSource) {
	$start = strpos($pollerSource, 'function file_exists_2gb(');
	$end   = strpos($pollerSource, "\n}\n", $start);
	$body  = substr($pollerSource, $start, $end - $start);

	// Guard against a future "helpful" refactor re-introducing argv
	// construction around $filename.
	expect($body)->not->toContain('escapeshellarg');
	expect($body)->not->toContain('escapeshellcmd');
});

// GHSA-c4qp: test-script path expansion must shell-escape data_input values.
test('GHSA-c4qp: test-script path expansion shell-escapes data_input values', function () use ($functionsSource) {
	expect($functionsSource)->toContain("function get_full_test_script_path(");
	expect($functionsSource)->toContain("\$value = cacti_escapeshellarg((string) \$item['value']);");
});

test('GHSA-c4qp: get_full_test_script_path does not wrap raw field values in manual quotes', function () use ($functionsSource) {
	expect($functionsSource)->not->toContain("\$value = \"'\" . \$item['value'] . \"'\";");
});

// GHSA-g9c7: cacti_exec() must reject binaries starting with a dash or containing
// embedded whitespace (both are argv-injection vectors).
test('GHSA-g9c7: cacti_exec rejects binary strings that begin with dash', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_exec(');
	expect($functionsSource)->toContain("if (\$binary[0] === '-')");
	expect($functionsSource)->toContain('binary must not begin with "-"');
});

test('GHSA-g9c7: cacti_exec still rejects whitespace-mixed command strings', function () use ($functionsSource) {
	expect($functionsSource)->toContain("preg_match('/\\s/', \$binary)");
});
