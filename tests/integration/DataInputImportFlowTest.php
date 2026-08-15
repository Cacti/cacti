<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for the issue #7121 import-side regression.
 *
 * The regex fix in lib/functions.php is exercised end-to-end against the
 * same call sites that previously failed:
 *
 *   - lib/import.php : xml_to_data_input_method() rejected templates with
 *     quoted or digit-suffixed placeholders, returning bool false. The
 *     caller in import_xml_data() then did `$hash_cache += $return`,
 *     producing the TypeError "Unsupported operand types: array + bool".
 *
 *   - data_input.php : the GUI save path runs the same validator and
 *     would reject the same templates with a "validation_error" raise.
 *
 * A true DB-backed integration test (sql_save against data_input,
 * data_input_fields, data_template_data, data_local) needs the Cacti
 * bootstrap and a writable database. This file stays at the structural
 * level so it can run on any PHP installation that has the source tree
 * available, while still exercising the real source paths.
 */

$importSource    = file_get_contents(__DIR__ . '/../../lib/import.php');
$dataInputSource = file_get_contents(__DIR__ . '/../../data_input.php');
$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('lib/import.php still calls cacti_input_string_is_safe before sql_save', function () use ($importSource) {
	/* Per the test SecurityScriptServerDataInputTest, the import path
	 * MUST keep calling the validator. PR #7122's alternative removed the
	 * call entirely; this assertion guards against that approach landing
	 * by accident. */
	$start = strpos($importSource, 'function xml_to_data_input_method(');
	expect($start)->not->toBeFalse();

	$end  = strpos($importSource, "\nfunction ", $start + 1);
	$body = substr($importSource, $start, $end !== false ? $end - $start : 6000);

	$validatorPos = strpos($body, '!cacti_input_string_is_safe($save[$field_name])');
	expect($validatorPos)->not->toBeFalse('the shared validator must gate the imported input_string');

	$sqlSavePos = strpos($body, "sql_save(\$save, 'data_input')");
	expect($sqlSavePos)->not->toBeFalse('sql_save call must remain present');
	expect($validatorPos < $sqlSavePos)->toBeTrue('validator must run before sql_save');
});

test('data_input.php still gates form_save through the shared validator', function () use ($dataInputSource) {
	expect(strpos($dataInputSource, "cacti_input_string_is_safe(\$save['input_string'])"))
		->not->toBeFalse('GUI save must call the shared helper');
});

test('shared validator accepts the issue #7121 ss_grid_preason template', function () use ($functionsSource) {
	/* Extract the canonical function and run the exact failing template
	 * from issue #7121 against it. */
	preg_match(
		'/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm',
		$functionsSource,
		$m
	);
	expect($m)->not->toBeEmpty();

	$src = preg_replace(
		'/^function cacti_input_string_is_safe\(/m',
		'function _integration_is_safe(',
		$m[0]
	);
	if (!function_exists('_integration_is_safe')) {
		eval($src); // nosemgrep: php.lang.security.eval-use.eval-use
	}

	$ss_grid_preason = '<path_cacti>/scripts/ss_grid_preason.php ss_grid_preason <clusterid> "<reason>"';
	expect(_integration_is_safe($ss_grid_preason))
		->toBeTrue('the literal template from #7121 must round-trip through the validator');
});

test('shared validator placeholder grammar matches generate_data_input_field_sequences', function () use ($functionsSource) {
	/* lib/functions.php:generate_data_input_field_sequences() parses
	 * placeholders with /<([_a-zA-Z0-9]+)>/. cacti_input_string_is_safe()
	 * must accept any input_string the sequence generator accepts, or the
	 * GUI save will refuse templates that the rest of Cacti happily
	 * processes. Cross-check both regexes against a shared corpus. */
	preg_match(
		'/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm',
		$functionsSource,
		$m
	);
	$src = preg_replace(
		'/^function cacti_input_string_is_safe\(/m',
		'function _grammar_is_safe(',
		$m[0]
	);
	if (!function_exists('_grammar_is_safe')) {
		eval($src); // nosemgrep: php.lang.security.eval-use.eval-use
	}

	$corpus = [
		'<path_cacti>/scripts/test.php <param>',
		'<path_cacti>/scripts/test.php <arg1>',
		'<path_cacti>/scripts/test.php <host_id1> <host_id2>',
		'<path_cacti>/scripts/test.php "<param>"',
		'<path_cacti>/scripts/test.php "<arg1>"',
	];

	foreach ($corpus as $tpl) {
		$sequenceMatches = preg_match_all('/<([_a-zA-Z0-9]+)>/', $tpl, $unused) > 0;
		expect($sequenceMatches)->toBeTrue("sequence generator must find placeholders in: $tpl");
		expect(_grammar_is_safe($tpl))->toBeTrue("validator must accept template the sequence generator accepts: $tpl");
	}
});
