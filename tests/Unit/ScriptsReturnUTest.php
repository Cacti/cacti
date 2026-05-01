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
 * Source-scan tests for the scripts-return-U fix.
 *
 * Before the fix, ss_webseer.php and ss_gexport.php initialised $value
 * to '0' and returned '0' when a DB column came back empty.  RRDtool
 * treats '0' as a valid sample, which corrupts graphs when the plugin
 * table is missing or the row doesn't exist.  query_host_cpu.php had
 * no fallback at all for a missing index.
 *
 * The fix changes all three scripts to return 'U' (RRDtool unknown)
 * on any error or missing-data path.
 */

// --- helpers ---

function getScriptSource(string $filename): string {
	$path = __DIR__ . '/../../scripts/' . $filename;
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse("Failed to read scripts/{$filename}");

	return $src;
}

// --- ss_webseer.php ---

test('ss_webseer initialises $value to U', function () {
	$src = getScriptSource('ss_webseer.php');

	// The get branch must set $value = 'U', not '0'
	expect(str_contains($src, "\$value = 'U'"))->toBeTrue(
		"ss_webseer.php must initialise \$value to 'U'"
	);
	expect(str_contains($src, "\$value = '0'"))->toBeFalse(
		"ss_webseer.php must not initialise \$value to '0'"
	);
});

test('ss_webseer get branch returns U for empty/false/null', function () {
	$src = getScriptSource('ss_webseer.php');

	// The return statement must coalesce empty/false/null to 'U'
	$pattern = '/return\s*\(\s*\$value\s*===\s*\'\'\s*\|\|\s*\$value\s*===\s*false\s*\|\|\s*\$value\s*===\s*null\s*\?\s*\'U\'/';
	expect(preg_match($pattern, $src))->toBe(1,
		"ss_webseer.php get branch must return 'U' for empty, false, or null values"
	);
});

test('ss_webseer has trailing return U for unhandled commands', function () {
	$src = getScriptSource('ss_webseer.php');

	// After the closing brace of the last elseif, there should be a return 'U'
	$pattern = '/\}\s+return\s+\'U\';\s*\}\s*$/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"ss_webseer.php must have a trailing return 'U' for unhandled command paths"
	);
});

// --- ss_gexport.php ---

test('ss_gexport initialises $value to U', function () {
	$src = getScriptSource('ss_gexport.php');

	expect(str_contains($src, "\$value = 'U'"))->toBeTrue(
		"ss_gexport.php must initialise \$value to 'U'"
	);
	expect(str_contains($src, "\$value = '0'"))->toBeFalse(
		"ss_gexport.php must not initialise \$value to '0'"
	);
});

test('ss_gexport get branch returns U for empty/false/null', function () {
	$src = getScriptSource('ss_gexport.php');

	$pattern = '/return\s*\(\s*\$value\s*===\s*\'\'\s*\|\|\s*\$value\s*===\s*false\s*\|\|\s*\$value\s*===\s*null\s*\?\s*\'U\'/';
	expect(preg_match($pattern, $src))->toBe(1,
		"ss_gexport.php get branch must return 'U' for empty, false, or null values"
	);
});

test('ss_gexport has trailing return U for unhandled commands', function () {
	$src = getScriptSource('ss_gexport.php');

	$pattern = '/\}\s+return\s+\'U\';\s*\}\s*$/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"ss_gexport.php must have a trailing return 'U' for unhandled command paths"
	);
});

// --- query_host_cpu.php ---

test('query_host_cpu prints U when index is absent', function () {
	$src = getScriptSource('query_host_cpu.php');

	// The else branch for missing index must print 'U'
	expect(str_contains($src, "print 'U'"))->toBeTrue(
		"query_host_cpu.php must print 'U' when the requested index is absent"
	);
});

test('query_host_cpu does not silently skip missing index', function () {
	$src = getScriptSource('query_host_cpu.php');

	// The get command block must have both the isset check and the else with 'U'
	$pattern = '/if\s*\(isset\(\$arr_index\[\$index\]\)\)\s*\{.*?print\s+\$arr\[\$index\].*?\}\s*else\s*\{.*?print\s+\'U\'/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"query_host_cpu.php must have an else branch that prints 'U' when index is absent"
	);
});

// --- negative: no script returns '0' as fallback ---

test('none of the three scripts use 0 as error fallback', function () {
	foreach (array('ss_webseer.php', 'ss_gexport.php') as $file) {
		$src = getScriptSource($file);

		// Old pattern: return (empty($value) ? '0' : $value)
		// or:          return ($value == '' ? '0' : $value)
		$pattern = '/return\s*\(.*\?\s*\'0\'\s*:/';
		expect(preg_match($pattern, $src))->toBe(0,
			"{$file} must not use '0' as an error fallback in return statements"
		);
	}
});
