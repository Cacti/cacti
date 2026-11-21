<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Network discovery binds the resolved host name as a parameter in the
 * duplicate system name check, and escapes discovered device fields in the
 * notification email.
 */

$pollerSource = file_get_contents(dirname(__DIR__, 4) . '/poller_automation.php');

test('the discovery system name check binds the resolved host name', function () use ($pollerSource) {
	expect($pollerSource)->toContain('AND (hostname = ? OR hostname LIKE ?)')
		->and($pollerSource)->toContain("array(\$snmp_sysName, \$device['ip_address'], \$hostname . '%')")
		->and($pollerSource)->not->toContain('hostname LIKE "\' . $hostname . \'%"');
});

test('the discovery notification email escapes device fields', function () use ($pollerSource) {
	expect($pollerSource)->toContain("'<tr><td>' . html_escape(\$device['hostname'])")
		->and($pollerSource)->toContain("html_escape(\$device['sysName'])");
});
