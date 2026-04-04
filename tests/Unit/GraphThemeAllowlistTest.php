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

/**
 * Tests for the graph_theme LFI/path-traversal allowlist in lib/rrd.php.
 *
 * The fix adds a preg_match('/^[a-zA-Z0-9_-]+$/', ...) guard inside
 * rrdtool_function_theme_font_options() before interpolating graph_theme
 * into an include path. Any value that does not match the pattern falls
 * back to get_selected_theme(), so directory traversal strings such as
 * '../evil' or '../../../../etc/passwd' can never reach the include.
 *
 * Two test suites:
 *   1. Source-scan: verifies the allowlist regex is present at the correct
 *      location and that the include is guarded, not raw.
 *   2. Behavioral: exercises the regex predicate in isolation to confirm
 *      safe themes pass and traversal/injection strings are rejected.
 */

// ---------------------------------------------------------------------------
// Source file
// ---------------------------------------------------------------------------

$src = file_get_contents(__DIR__ . '/../../lib/rrd.php');

// ---------------------------------------------------------------------------
// Source-scan suite
// ---------------------------------------------------------------------------

test('rrdtool_function_theme_font_options: allowlist regex is present in source', function () use ($src) {
	expect($src)->toContain("preg_match('/^[a-zA-Z0-9_-]+\$/', \$graph_data_array['graph_theme'])");
});

test('rrdtool_function_theme_font_options: graph_theme is checked with isset before regex', function () use ($src) {
	// Both isset and preg_match must appear together in the same condition.
	expect($src)->toContain("isset(\$graph_data_array['graph_theme']) && preg_match('/^[a-zA-Z0-9_-]+\$/', \$graph_data_array['graph_theme'])");
});

test('rrdtool_function_theme_font_options: include of rrdtheme.php is inside file_exists guard', function () use ($src) {
	// The include must be guarded by file_exists() and is_readable(); raw include
	// without a guard would be exploitable even after the allowlist check.
	$file_exists_pos = strpos($src, 'file_exists($rrdtheme) && is_readable($rrdtheme)');
	$include_pos     = strpos($src, 'include($rrdtheme)');

	expect($file_exists_pos)->not->toBeFalse()
		->and($include_pos)->not->toBeFalse()
		->and($file_exists_pos)->toBeLessThan($include_pos);
});

test('rrdtool_function_theme_font_options: allowlist check precedes the include path assignment', function () use ($src) {
	$allowlist_pos = strpos($src, "preg_match('/^[a-zA-Z0-9_-]+\$/', \$graph_data_array['graph_theme'])");
	$include_pos   = strpos($src, 'include($rrdtheme)');

	expect($allowlist_pos)->not->toBeFalse()
		->and($include_pos)->not->toBeFalse()
		->and($allowlist_pos)->toBeLessThan($include_pos);
});

test('rrdtool_function_theme_font_options: fallback to get_selected_theme() when check fails', function () use ($src) {
	// The else branch must use get_selected_theme() so an invalid graph_theme
	// does not produce an empty/attacker-controlled path.
	expect($src)->toContain('get_selected_theme()');
});

test('rrdtool_function_theme_font_options: no raw graph_theme interpolation without guard', function () use ($src) {
	// Ensure there is no code path that builds the theme path from graph_theme
	// without the preg_match guard wrapping it (i.e., no bare array concat).
	$raw_concat = strpos($src, "'/themes/' . \$graph_data_array['graph_theme']");
	$guarded    = strpos($src, "preg_match('/^[a-zA-Z0-9_-]+\$/', \$graph_data_array['graph_theme'])");

	// If raw concat exists it must appear only inside the guarded if-branch,
	// meaning the guard position is less than the concat position.
	if ($raw_concat !== false) {
		expect($guarded)->not->toBeFalse()
			->and($guarded)->toBeLessThan($raw_concat);
	} else {
		// Preferred: the fix used $graph_theme local var; raw array access absent.
		expect($guarded)->not->toBeFalse();
	}
});

// ---------------------------------------------------------------------------
// Behavioral suite — allowlist predicate in isolation
//
// The production guard is: preg_match('/^[a-zA-Z0-9_-]+$/', $value)
// These tests exercise that regex without bootstrapping Cacti.
// ---------------------------------------------------------------------------

/*
 * Returns true when $theme would pass the allowlist and be used directly,
 * false when it would be rejected and fall back to get_selected_theme().
 */
function themeAllowed(string $theme): bool {
	return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $theme);
}

// --- safe themes ---

test('theme name "classic" passes allowlist', function () {
	expect(themeAllowed('classic'))->toBeTrue();
});

test('theme name "modern-dark" passes allowlist', function () {
	expect(themeAllowed('modern-dark'))->toBeTrue();
});

test('theme name "theme_v2" passes allowlist', function () {
	expect(themeAllowed('theme_v2'))->toBeTrue();
});

test('theme name with mixed case passes allowlist', function () {
	expect(themeAllowed('MyTheme123'))->toBeTrue();
});

// --- traversal attacks ---

test('directory traversal "../evil" is rejected by allowlist', function () {
	expect(themeAllowed('../evil'))->toBeFalse();
});

test('deep traversal "../../../../etc/passwd" is rejected', function () {
	expect(themeAllowed('../../../../etc/passwd'))->toBeFalse();
});

test('null byte in theme name is rejected', function () {
	expect(themeAllowed("classic\x00../../etc/passwd"))->toBeFalse();
});

test('slash in theme name is rejected', function () {
	expect(themeAllowed('themes/evil'))->toBeFalse();
});

test('backslash in theme name is rejected', function () {
	expect(themeAllowed('theme\\evil'))->toBeFalse();
});

// --- injection characters ---

test('single quote in theme name is rejected', function () {
	expect(themeAllowed("the'me"))->toBeFalse();
});

test('space in theme name is rejected', function () {
	expect(themeAllowed('my theme'))->toBeFalse();
});

test('semicolon in theme name is rejected', function () {
	expect(themeAllowed('theme;evil'))->toBeFalse();
});

test('empty theme name is rejected (allowlist requires at least one char)', function () {
	expect(themeAllowed(''))->toBeFalse();
});
