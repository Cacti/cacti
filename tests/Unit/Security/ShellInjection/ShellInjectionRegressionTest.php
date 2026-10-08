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
	expect($functionsSource)->toContain("\$value = cacti_escapeshellarg_cmd((string) \$item['value']);");
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

// GHSA-fq9x: the data-input <field> substitution must be single-pass so a value
// that happens to contain another field's <token> cannot re-inject that later
// field's already-escaped payload into the first field's quoted region
// (second-order placeholder re-substitution breakout).

test('GHSA-fq9x: substitute_script_path replaces tokens in a single pass over the original template', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function substitute_script_path(');
	// a single preg_replace_callback over the ORIGINAL template, not an
	// iterative str_replace loop that re-scans substituted output.
	expect($functionsSource)->toContain('>(?(1)');
	expect($functionsSource)->toContain('array_key_exists($matches[2], $escaped_values)');
});

test('GHSA-fq9x: both path builders no longer re-scan the mutated buffer with str_replace', function () use ($functionsSource) {
	// the vulnerable per-field, progressively-mutating substitution is gone...
	expect($functionsSource)->not->toContain("\$full_path = str_replace('<' . \$item['data_name'] . '>', \$value, \$full_path);");
	// ...and both callers now route through the single-pass helper.
	expect(substr_count($functionsSource, '$full_path = substitute_script_path($full_path, $escaped_values);'))->toBe(2);
});

test('GHSA-fq9x: path tokens are merged after the field map so field names keep precedence', function () use ($functionsSource) {
	// the field loop populates $escaped_values first, then the trusted path
	// tokens are added with += (which does NOT overwrite existing field keys),
	// preserving the historical field-first substitution order.
	expect($functionsSource)->toContain("\$escaped_values += array(");
	expect($functionsSource)->toContain("'path_cacti'      =>");
});

/*
 * Behavioral proof that the real helper contains the second-order payload.
 * The function body is pulled directly out of this repo's own lib/functions.php
 * and eval()'d into scope (Test-only; never external/user input), following the
 * extract-and-eval pattern used in SsHostDiskNegativeSizeTest.php and
 * PercentileContractTest.php, because the 1.2.x unit bootstrap deliberately does
 * not load lib/functions.php.
 */
if (!function_exists('substitute_script_path')) {
	preg_match('/\nfunction substitute_script_path\b.*?\n\}/s', "\n" . $functionsSource, $sspMatch);

	if (!empty($sspMatch)) {
		eval($sspMatch[0]);
	}
}

test('GHSA-fq9x: a field whose value is another field token is not re-substituted', function () {
	expect(function_exists('substitute_script_path'))->toBeTrue();

	// field1's escaped value literally contains <arg2>; arg2 carries a payload.
	// A single pass must emit the literal "<arg2>" for field1 and must NOT
	// splice arg2's PAYLOAD in its place.
	$result = substitute_script_path('<field1>', array(
		'field1' => '<arg2>',
		'arg2'   => 'PAYLOAD',
	));

	expect($result)->toBe('<arg2>');
	expect($result)->not->toContain('PAYLOAD');
});

test('GHSA-fq9x: unknown tokens are left intact and known tokens are replaced once', function () {
	expect(substitute_script_path('<unknown>', array()))->toBe('<unknown>');
	expect(substitute_script_path('<f>-<f>', array('f' => "'v'")))->toBe("'v'-'v'");
	expect(substitute_script_path('<a><b>', array('a' => 'X', 'b' => 'Y')))->toBe('XY');
});
