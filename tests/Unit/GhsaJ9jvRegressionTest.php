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

// GHSA-j9jv-6xjq-9hhj: SQL injection via cacti_unserialize in managers.php.
// The form_actions handler imploded the post-unserialize array straight into
// IN (...) clauses. Even though cacti_unserialize blocks object injection, it
// still returns string values, which then reach the SQL layer verbatim.
// The fix casts each value via intval() before implode().

test('selected_items passed through intval coercion rejects string payloads', function () {
	$payload = ["1 OR 1=1", "2; DROP TABLE snmpagent_managers"];

	$coerced = array_values(array_filter(array_map('intval', $payload)));

	expect($coerced)->toBe([1, 2]);
});

test('intval coercion drops non-numeric entries that would leak into IN clause', function () {
	$payload = ['xyz', 'DROP', '42'];

	$coerced = array_values(array_filter(array_map('intval', $payload)));

	expect($coerced)->toBe([42]);
});

test('intval coercion preserves legitimate integer ids', function () {
	$payload = [1, 2, 3, 10];

	$coerced = array_values(array_filter(array_map('intval', $payload)));

	expect($coerced)->toBe([1, 2, 3, 10]);
});

test('managers.php form_actions applies array_map intval before implode', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/managers.php');

	expect($source)->toContain("array_map('intval', \$selected_items)");
});

test('managers.php form_actions no longer implodes unsanitized selected_items', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/managers.php');
	$lines  = explode("\n", $source);

	$unsafePattern = "/implode\([^)]*,\s*\\\$selected_items\s*\)/";
	$imploded      = [];

	foreach ($lines as $n => $line) {
		if (preg_match($unsafePattern, $line)) {
			$imploded[] = $n + 1;
		}
	}

	foreach ($imploded as $lineNo) {
		$context = implode("\n", array_slice($lines, max(0, $lineNo - 30), 30));
		expect($context)->toContain("array_map('intval', \$selected_items)");
	}
});
