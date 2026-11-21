<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The remote database connection of a Data Collector decides where Full Sync
 * sends the main database's tables, so setting it, testing it and running
 * Full Sync take the Settings permission, not only the device management
 * permission that opens pollers.php.
 */

$pollers_src = file_get_contents(dirname(__DIR__, 4) . '/pollers.php');

function pollers_function_body(string $src, string $name): string {
	$start = strpos($src, 'function ' . $name . '(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\n}\n", $start);
	expect($end)->not->toBeFalse();

	return substr($src, $start, $end - $start);
}

test('form_save only reads the database fields with the Settings permission', function () use ($pollers_src) {
	$body  = pollers_function_body($pollers_src, 'form_save');
	$guard = strpos($body, "if (\$save['id'] != 1 && is_realm_allowed(15)) {");

	expect($guard)->not->toBeFalse();

	foreach (array('dbdefault', 'dbhost', 'dbuser', 'dbpass', 'dbport', 'dbretries', 'dbssl', 'dbsslkey', 'dbsslcert', 'dbsslca') as $field) {
		$pos = strpos($body, "\$save['" . $field . "']");

		expect($pos)->not->toBeFalse();
		expect($pos)->toBeGreaterThan($guard);
	}

	expect($body)->toContain("isset(\$save['dbhost']) && poller_host_duplicate(");
});

test('Full Sync is refused without the Settings permission', function () use ($pollers_src) {
	$body  = pollers_function_body($pollers_src, 'form_actions');
	$deny  = strpos($body, "get_request_var('drp_action') == '4' && !is_realm_allowed(15)");
	$work  = strpos($body, 'replicate_out(');

	expect($deny)->not->toBeFalse();
	expect($work)->not->toBeFalse();
	expect($deny)->toBeLessThan($work);
});

test('the connection test requires the Settings permission', function () use ($pollers_src) {
	$start = strpos($pollers_src, "case 'ping':");
	expect($start)->not->toBeFalse();

	$case = substr($pollers_src, $start, strpos($pollers_src, 'break;', $start) - $start);

	expect($case)->toContain('if (is_realm_allowed(15)) {');
	expect(strpos($case, 'is_realm_allowed(15)'))->toBeLessThan(strpos($case, 'test_database_connection('));
});

test('the edit form hides the database fields without the Settings permission', function () use ($pollers_src) {
	$body = pollers_function_body($pollers_src, 'poller_edit');

	expect($body)->toContain("|| !is_realm_allowed(15)) {");
	expect($body)->toContain("'spacer_remotedb', 'dbdefault', 'dbhost', 'dbuser', 'dbpass'");
});
