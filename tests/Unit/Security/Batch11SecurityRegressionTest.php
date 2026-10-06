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
 * Batch-11 security consolidation regressions (develop), guarding the four
 * sinks fixed in PR #8165:
 *
 *   GHSA-fq9x-x3vf-3vf2  single-pass data-input <field> substitution
 *   GHSA-hh7r-464j-rf39  Content-Disposition filename sanitization
 *   GHSA-v99q-57w7-mpmg  HTML <title> escaping
 *   GHSA-f5f5-h85v-6m3h  user administration tooltip text encoding
 */

$root = dirname(__DIR__, 3);

$functionsSource = file_get_contents($root . '/lib/functions.php');
$xportSource     = file_get_contents($root . '/graph_xport.php');
$htmlSource      = file_get_contents($root . '/lib/html.php');
$userAdminSource = file_get_contents($root . '/user_admin.php');
$userGroupSource = file_get_contents($root . '/user_group_admin.php');

// =====================================================================
// GHSA-fq9x: single-pass <field> token substitution
// =====================================================================

test('GHSA-fq9x: substitute_script_path substitutes tokens in a single pass', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function substitute_script_path(');
	expect($functionsSource)->toContain("preg_replace_callback('/<([A-Za-z0-9_]+)>/',");
	expect($functionsSource)->toContain('array_key_exists($matches[1], $escaped_values)');
});

test('GHSA-fq9x: both path builders drop the iterative str_replace and call the helper', function () use ($functionsSource) {
	expect($functionsSource)->not->toContain("\$full_path = str_replace('<' . \$item['data_name'] . '>', \$value, \$full_path);");
	expect(substr_count($functionsSource, '$full_path = substitute_script_path($full_path, $escaped_values);'))->toBe(2);
});

/*
 * Behavioral proof. The develop unit bootstrap loads lib/functions.php via
 * include/global.php, so substitute_script_path() is normally already defined.
 * As a defensive fallback (and to document the Test-only nature of the eval),
 * the real function body is regex-extracted from this repo's own source and
 * eval()'d only when it is not already present -- the extract-and-eval pattern
 * used elsewhere in the suite. Input is this repo's source, never user input.
 */
if (!function_exists('substitute_script_path')) {
	preg_match('/\nfunction substitute_script_path\b.*?\n\}/s', "\n" . $functionsSource, $sspMatch);

	if (!empty($sspMatch)) {
		eval($sspMatch[0]);
	}
}

test('GHSA-fq9x: a field value that contains another field token is not re-substituted', function () {
	expect(function_exists('substitute_script_path'))->toBeTrue();

	$result = substitute_script_path('<field1>', [
		'field1' => '<arg2>',
		'arg2'   => 'PAYLOAD',
	]);

	expect($result)->toBe('<arg2>');
	expect($result)->not->toContain('PAYLOAD');
});

test('GHSA-fq9x: known tokens are replaced once and unknown tokens remain literal', function () {
	expect(substitute_script_path('<unknown>', []))->toBe('<unknown>');
	expect(substitute_script_path('<f>-<f>', ['f' => "'v'"]))->toBe("'v'-'v'");
	expect(substitute_script_path('<a><b>', ['a' => 'X', 'b' => 'Y']))->toBe('XY');
});

// =====================================================================
// GHSA-hh7r: Content-Disposition filename sanitization
// =====================================================================

test('GHSA-hh7r: the export filename strips CR/LF/quote/backslash from the title', function () use ($xportSource) {
	expect($xportSource)->toContain('$filename = str_replace(["\r", "\n", \'"\', \'\\\\\'], \'\', (string) $xport_array[\'meta\'][\'title_cache\']) . \'.csv\';');
	// the raw, unsanitized concatenation must be gone.
	expect($xportSource)->not->toContain('$filename = $xport_array[\'meta\'][\'title_cache\'] . \'.csv\';');
});

// =====================================================================
// GHSA-v99q: HTML <title> escaping
// =====================================================================

test('GHSA-v99q: the page title is html-escaped before entering the <title> element', function () use ($htmlSource) {
	expect($htmlSource)->toContain('<title><?php print html_escape($title); ?></title>');
	expect($htmlSource)->not->toContain('<title><?php print $title; ?></title>');
});

// =====================================================================
// GHSA-f5f5: user administration tooltip text encoding
// =====================================================================

test('GHSA-f5f5: the user-admin tooltip renders data-tooltip as escaped text', function () use ($userAdminSource) {
	expect($userAdminSource)->toContain("return $('<div>').text($(this).attr('data-tooltip') || '').html();");
	expect($userAdminSource)->not->toContain("return $(this).attr('data-tooltip');");
});

test('GHSA-f5f5: the user-group-admin tooltip also renders data-tooltip as escaped text', function () use ($userGroupSource) {
	expect($userGroupSource)->toContain("return $('<div>').text($(this).attr('data-tooltip') || '').html();");
	expect($userGroupSource)->not->toContain("return $(this).attr('data-tooltip');");
});
