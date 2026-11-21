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
 * The automation rule preview searches the data query fields by using each
 * field name as an identifier. Backticks quote an identifier and do not
 * escape one, so only names made of identifier characters are searched. The
 * search text is quoted as a value and is not at issue.
 *
 * build_graph_object_sql_having() is the unchanged function from
 * lib/api_automation.php, loaded into this namespace so the field names can
 * be answered here.
 */

namespace AutomationPreviewFieldNameTest;

function get_field_names($snmp_query_id) {
	return array_map(function ($name) {
		return array('field_name' => $name);
	}, $GLOBALS['automation_preview_fields']);
}

function db_qstr($value) {
	return "'" . addslashes($value) . "'";
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_automation.php', 'build_graph_object_sql_having', __NAMESPACE__);

function automation_preview_having(array $fields, $filter = 'eth') {
	$GLOBALS['automation_preview_fields'] = $fields;

	return build_graph_object_sql_having(array('snmp_query_id' => 1), $filter);
}

test('field names made of identifier characters are searched as before', function () {
	expect(automation_preview_having(array('ifDescr', 'ifName', 'hr_storage2', 'a.b')))
		->toBe(" HAVING (`ifDescr` LIKE '%eth%' OR `ifName` LIKE '%eth%' OR `hr_storage2` LIKE '%eth%' OR `a`.`b` LIKE '%eth%')");
});

test('a field name with other characters is left out of the search', function ($name) {
	expect(automation_preview_having(array('ifDescr', $name, 'ifName')))
		->toBe(" HAVING (`ifDescr` LIKE '%eth%' OR `ifName` LIKE '%eth%')");
})->with(array(
	'backtick'     => array('if`Descr'),
	'closing text' => array('x` OR 1=1 OR `y'),
	'space'        => array('if Descr'),
	'hyphen'       => array('if-Descr'),
	'quote'        => array("if'Descr"),
	'comment'      => array('ifDescr-- '),
	'empty'        => array(''),
	'leading dot'  => array('.ifDescr'),
	'trailing dot' => array('ifDescr.'),
	'double dot'   => array('a..b'),
));

test('the search matches nothing when no field name can be used', function () {
	expect(automation_preview_having(array('if`Descr', 'if Name')))->toBe(' HAVING (1 = 0)');
});

test('no search text adds no clause', function () {
	expect(automation_preview_having(array('ifDescr'), ''))->toBeNull();
});
