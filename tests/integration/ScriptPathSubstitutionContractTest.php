<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the unresolved substitution warning (#3744).
 *
 * Both script path builders must warn before nulling out unresolved
 * <field> tokens, and the legacy strip regex must stay unchanged so
 * existing templates keep producing the same command lines.
 */

$root = dirname(__DIR__, 2);

test('both script path builders warn before stripping unresolved tokens', function () use ($root) {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/functions.php');
	expect($src)->not->toBeFalse('Failed to read lib/functions.php');

	foreach (['get_full_test_script_path', 'get_full_script_path'] as $fn) {
		$fnPos = strpos($src, "function $fn(");
		expect($fnPos)->not->toBeFalse("$fn must exist");

		$fnEnd = strpos($src, "\nfunction ", $fnPos + 1);
		$body  = substr($src, $fnPos, ($fnEnd === false ? strlen($src) : $fnEnd) - $fnPos);

		$warnPos  = strpos($body, 'unresolved substitution variables');
		$stripPos = strpos($body, "preg_replace('/(<[A-Za-z0-9_]+>)+/'");

		expect($warnPos)->not->toBeFalse("$fn must log the unresolved variable warning");
		expect($stripPos)->not->toBeFalse("$fn must keep the legacy token strip");
		expect($warnPos)->toBeLessThan($stripPos, "$fn must warn before stripping");
		expect(strpos($body, "'POLLER'"))->not->toBeFalse("$fn warning must use the POLLER facility");
	}
});

test('warning fires only when tokens remain', function () use ($root) {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/functions.php');
	expect($src)->not->toBeFalse('Failed to read lib/functions.php');

	// tolerant of formatting: any preg_match_all() on the token pattern against $full_path
	$count = preg_match_all('/preg_match_all\(\s*\'\/<\[A-Za-z0-9_\]\+>\/\'\s*,\s*\$full_path\s*,\s*\$matches\s*\)/', $src);
	expect($count)->toBe(2,
		'both builders must gate the warning and strip behind a preg_match_all check');
});
