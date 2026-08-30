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
 * Two places in lib/api_automation.php wrap a stored name in backticks and use
 * it as an identifier. Backticks quote an identifier; they do not escape one,
 * so a name holding a backtick closes the quoting instead of being contained
 * by it. build_rule_item_filter() guarded this on 1.2.x and the guard was lost
 * on the way to develop; build_graph_object_sql_having() never had one.
 *
 * The filter values beside them are handled by db_qstr() and are not at issue.
 */

require_once CACTI_PATH_LIBRARY . '/functions.php';

$src = file_get_contents(CACTI_PATH_LIBRARY . '/api_automation.php');

test('the column sanitiser removes the character that closes an identifier', function () {
	expect(sanitize_sql_column('if`Descr', ''))->toBe('ifDescr')
		->and(sanitize_sql_column('`', ''))->toBe('')
		->and(sanitize_sql_column('ifDescr', ''))->toBe('ifDescr');
});

test('the rule item filter confines each part of the stored field name', function () use ($src) {
	/* the whole-name form let '.' through the helper, so 'a.' split into a
	   usable part and an empty one and rendered an empty identifier */
	expect($src)->not->toContain("implode('`.`', explode('.', \$automation_rule_item['field']))")
		->and($src)->not->toContain("sanitize_sql_column(\$automation_rule_item['field'])")
		->and($src)->toContain("\$clean_part = sanitize_sql_column(\$field_part, '');")
		->and($src)->toContain('if (!cacti_sizeof($field_parts)) {')
		->and($src)->toContain("\$sql_filter .= ' 1 = 0';");
});

test('a trailing dot would have produced an empty identifier segment', function () {
	require_once CACTI_PATH_LIBRARY . '/functions.php';

	// what the whole-name form used to yield
	$whole = sanitize_sql_column('a.', 'id');

	expect($whole)->toBe('a.')
		->and(explode('.', $whole))->toBe(['a', '']);

	// what the per-part form yields for the same input
	$parts = [];

	foreach (explode('.', 'a.') as $part) {
		$clean = sanitize_sql_column($part, '');

		if ($clean === '') {
			$parts = [];

			break;
		}

		$parts[] = $clean;
	}

	expect($parts)->toBe([]);
});

test('the having clause confines each part of the field name', function () use ($src) {
	expect($src)->not->toContain("implode('`.`', explode('.', \$column['field_name']))")
		->and($src)->toContain("\$clean = sanitize_sql_column(\$part, '');");
});

test('a field that sanitises away produces a false HAVING predicate', function () use ($src) {
	expect($src)->toContain("if (\$i == 0) {\n\t\t\t\treturn ' HAVING (1 = 0)';");
});

test('the two device filters cast before interpolating', function () use ($src) {
	expect($src)->not->toContain("' gl.host_id = ' . grv('host_id')")
		->and($src)->not->toContain("' gtg.graph_template_id = ' . grv('template_id')")
		->and($src)->toContain("' gl.host_id = ' . (int) grv('host_id')")
		->and($src)->toContain("' gtg.graph_template_id = ' . (int) grv('template_id')");
});
