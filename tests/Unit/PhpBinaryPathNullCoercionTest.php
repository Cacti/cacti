<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The six binary path settings covered by #7584 had no 'default' key, so
 * read_default_config_option() returned null whenever the settings row was
 * absent. That happens on a fresh install and after a partial one.
 *
 * Callers already treat an empty path as unset. An explicit empty-string
 * default preserves that behavior at the source and prevents every current
 * and future string-typed consumer from receiving null. Existing casts remain
 * useful defense in depth for partially initialized test/bootstrap contexts.
 */

/**
 * Reads a function body out of a source file.
 *
 * @param string $file     Repository-relative path to the file.
 * @param string $function The function name to extract.
 *
 * @return string The body, up to the next top-level function.
 */
function php_binary_body(string $file, string $function) : string {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect($source)->not->toBeFalse("$file must be readable");

	$start = strpos($source, 'function ' . $function . '(');

	expect($start)->not->toBeFalse("$function must exist in $file");

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

test('the six affected binary path settings default to an empty string rather than null', function () {
	$settings = file_get_contents(dirname(__DIR__, 2) . '/include/global_settings.php');
	expect($settings)->not->toBeFalse('include/global_settings.php must be readable');

	foreach (['path_php_binary', 'path_rrdtool', 'path_spine', 'path_snmpget', 'path_snmpgetnext', 'path_snmpwalk'] as $setting) {
		$start = strpos($settings, "'$setting' => [");
		expect($start)->not->toBeFalse("$setting settings block must exist");

		$end = strpos($settings, "\n\t],", $start);
		expect($end)->not->toBeFalse("$setting settings block must have a closing delimiter");

		$block = substr($settings, $start, $end - $start);

		expect($block)->toMatch("/'default'\\s*=>\\s*''/");
	}
});

test('a null option is what PHP deprecates, so the guard is not theoretical', function () {
	$raised                  = null;
	$previousErrorReporting = error_reporting(E_ALL);

	set_error_handler(function ($number, $string) use (&$raised) {
		$raised = $string;

		return true;
	});

	try {
		str_replace('|path_php_binary|', null, '|path_php_binary|/x');
	} finally {
		// restore before asserting, so a failure here cannot leave the custom
		// handler installed for the rest of the run
		restore_error_handler();
		error_reporting($previousErrorReporting);
	}

	expect($raised)->toContain('Passing null to parameter');
});

test('the script query path substitution casts the binary path', function () {
	expect(php_binary_body('lib/variables.php', 'substitute_script_query_path'))
		->toContain("(string) read_config_option('path_php_binary')");
});

test('poller maintenance casts the binary path before escaping it', function () {
	expect(php_binary_body('lib/functions.php', 'poller_maintenance'))
		->toContain("cacti_escapeshellcmd((string) read_config_option('path_php_binary'))");
});

test('no shipped file escapes the binary path without casting it first', function () {
	$root = dirname(__DIR__, 2);

	if (!function_exists('exec') || str_contains((string) ini_get('disable_functions'), 'exec')) {
		test()->markTestSkipped('exec is disabled, so the shipped file list is unavailable');
	}

	exec('git -C ' . escapeshellarg($root) . ' ls-files "*.php"', $tracked, $status);

	if ($status !== 0 || $tracked === []) {
		test()->markTestSkipped('not a git checkout, so the shipped file list is unknown');
	}

	$unguarded = [];

	foreach ($tracked as $file) {
		if (str_starts_with($file, 'tests/') || str_contains($file, 'vendor/')) {
			continue;
		}

		$source = file_get_contents($root . '/' . $file);

		if ($source !== false && preg_match(
			'/cacti_escapeshellcmd\s*\(\s*(?!\(\s*string\s*\)\s*)read_config_option\s*\(\s*[\'\"]path_php_binary[\'\"](?:\s*,[^)]*)?\)/',
			$source
		)) {
			$unguarded[] = $file;
		}
	}

	// there were 21 of these; a new one is easier to add than to notice
	expect($unguarded)->toBe([]);
});

/**
 * The callers treat an unset path as "assume it is on the PATH", so the cast
 * has to preserve that rather than turn it into a literal 'null' or an error.
 */
test('casting a null option yields the empty string the callers expect', function () {
	$option = null;

	expect((string) $option)->toBe('')
		->and(empty((string) $option))->toBeTrue();
});
