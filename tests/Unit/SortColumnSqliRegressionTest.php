<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression coverage for the "ORDER BY sort_column" SQL-injection advisory
 * family, verified against develop's own remediation:
 *
 *   - GHSA-3p6w-h4wv-6x7g  (utilities.php)
 *   - GHSA-72vr-jr4v-55vf  (lib/html_reports.php)
 *   - GHSA-q9xg-p762-9jm3  (lib/api_automation.php)
 *
 * develop neutralises the class with sanitize_sql_column(), which strips every
 * character outside [a-zA-Z0-9_().] before a user-supplied column is embedded in
 * an ORDER BY clause and falls back to a safe default, and pins sort_direction
 * to a strict ASC/DESC allowlist. These are source-scan invariants that fail if
 * the fix is reverted. (1.2.x fixes the same class with cacti_validate_sort_column();
 * develop's mechanism is sanitize_sql_column(), so this test asserts the
 * develop-native helper rather than the 1.2.x name.)
 */

$root = dirname(__DIR__, 2);

test('sanitize_sql_column strips SQL metacharacters and defaults safely', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->not->toBeFalse();
	expect($src)->toContain('function sanitize_sql_column(string $column, string $default = \'id\') : string');
	// Allowlist: only word characters, dot and parentheses survive.
	expect($src)->toContain("preg_replace('/[^a-zA-Z0-9_().]/', ''");
	// A fully stripped value falls back to the safe default column.
	expect($src)->toContain("return \$result !== '' ? \$result : \$default;");
});

$sort_column_sinks = [
	'GHSA-3p6w utilities.php'          => 'utilities.php',
	'GHSA-72vr lib/html_reports.php'   => 'lib/html_reports.php',
	'GHSA-q9xg lib/api_automation.php' => 'lib/api_automation.php',
];

foreach ($sort_column_sinks as $label => $file) {
	test("$label routes sort_column through sanitize_sql_column", function () use ($root, $file) {
		$src = file_get_contents($root . '/' . $file);

		expect($src)->not->toBeFalse();
		expect($src)->toContain('sanitize_sql_column(');
	});
}

test('sort_direction is pinned to an ASC/DESC allowlist, not echoed from input', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_reports.php');

	expect($src)->not->toBeFalse();
	expect($src)->toContain("strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC'");
});
