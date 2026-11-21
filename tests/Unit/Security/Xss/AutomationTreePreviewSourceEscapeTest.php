<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The automation tree-rule preview shows the matched field value (a device
 * description or SNMP system name) in the "source" column through
 * filter_value(), which escapes it like the neighbouring cells.
 */

$apiSource = file_get_contents(dirname(__DIR__, 4) . '/lib/api_automation.php');

test('the tree-rule preview source cell escapes the matched value', function () use ($apiSource) {
	expect($apiSource)->toContain("form_selectable_cell(filter_value(\$template['source'], get_request_var('filter')), \$template['host_id']);")
		->and($apiSource)->not->toContain("form_selectable_cell(\$template['source'], \$template['host_id']);");
});
