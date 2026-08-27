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

$source = file_get_contents(dirname(__DIR__, 4) . '/user_admin.php');

if ($source === false) {
	throw new RuntimeException('Unable to read user_admin.php');
}

test('the user group filter is validated and constrained again at the SQL boundary', function () use ($source) {
	expect($source)
		->toContain("'group' => array(\n\t\t\t'filter' => FILTER_VALIDATE_INT,")
		->toContain("\$group_id = (int) get_request_var('group');")
		->toContain("' ug.group_id = ' . \$group_id;")
		->not->toContain("' ug.group_id = ' . get_request_var('group');")
		->not->toContain("'options' => array('options' => 'sanitize_search_string')\n\t\t),\n\t\t'sort_column'");
});

test('integer validation rejects SQL expressions accepted by the old search sanitizer', function () {
	expect(filter_var('0 OR 1', FILTER_VALIDATE_INT))->toBeFalse()
		->and(filter_var('1 UNION SELECT 1', FILTER_VALIDATE_INT))->toBeFalse()
		->and(filter_var('-1', FILTER_VALIDATE_INT))->toBe(-1)
		->and(filter_var('42', FILTER_VALIDATE_INT))->toBe(42);
});
