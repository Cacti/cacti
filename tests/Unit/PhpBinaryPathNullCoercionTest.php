<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * path_php_binary is the one path setting in include/global_settings.php with
 * no 'default' key, so read_default_config_option() returns null for it and
 * read_config_option() hands back null whenever the settings row is absent.
 * That happens on a fresh install and after a partial one.
 *
 * Three callers then passed the null straight into a string parameter. PHP
 * coerces it to '' and raises a deprecation today; PHP 9 makes it a TypeError,
 * which would take out script query collection, poller maintenance and the
 * boost launcher. The callers already handle an empty path, so making the
 * coercion explicit changes nothing except the warning.
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
	$start  = strpos($source, 'function ' . $function . '(');

	expect($start)->not->toBeFalse("$function must exist in $file");

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

test('path_php_binary really has no default to fall back on', function () {
	$settings = file_get_contents(dirname(__DIR__, 2) . '/include/global_settings.php');

	$start = strpos($settings, "'path_php_binary' => [");
	expect($start)->not->toBeFalse();

	$block = substr($settings, $start, strpos($settings, '],', $start) - $start);

	// if a default is ever added, these casts stop being load-bearing
	expect($block)->toContain("'method'")
		->and($block)->not->toContain("'default'");
});

test('a null option is what PHP deprecates, so the guard is not theoretical', function () {
	$raised = null;

	set_error_handler(function ($number, $string) use (&$raised) {
		$raised = $string;

		return true;
	});

	str_replace('|path_php_binary|', null, '|path_php_binary|/x');

	restore_error_handler();

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

test('the boost launcher casts the binary path before escaping it', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/boost.php');

	expect($source)->toContain("cacti_escapeshellcmd((string) read_config_option('path_php_binary'))")
		->and($source)->not->toContain("cacti_escapeshellcmd(read_config_option('path_php_binary'))");
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
