<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The user group list sorts only on the columns it displays, like the user list.
 */

test('user_group_admin.php limits the group list sort to displayed columns', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/user_group_admin.php');
	$start  = strpos($source, 'function user_group(');
	$body   = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	$validate = strpos($body, "set_request_var('sort_column', cacti_validate_sort_column(");
	$order    = strpos($body, 'get_order_string()');

	expect($validate)->not->toBeFalse()
		->and($order)->not->toBeFalse()
		->and($validate)->toBeLessThan($order);
});
