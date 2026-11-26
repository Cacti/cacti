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

$root = dirname(__DIR__, 4);

require_once $root . '/lib/ping.php';

test('Net_Ping::is_ipaddress validates IPv4, IPv6, and strict IPv6 zone IDs only', function () {
	$ping = new Net_Ping();

	expect($ping->is_ipaddress('192.0.2.1'))->toBeTrue()
		->and($ping->is_ipaddress('2001:db8::1'))->toBeTrue()
		->and($ping->is_ipaddress('fe80::1%eth0'))->toBeTrue()
		->and($ping->is_ipaddress('fe80::1%eth0.10'))->toBeTrue()
		->and($ping->is_ipaddress('127.0.0.1%eth0'))->toBeFalse()
		->and($ping->is_ipaddress("fe80::1%' OR 1=1--"))->toBeFalse()
		->and($ping->is_ipaddress('fe80::1%eth0;id'))->toBeFalse()
		->and($ping->is_ipaddress('fe80::1%'))->toBeFalse()
		->and($ping->is_ipaddress(''))->toBeFalse();
});

test('lib/snmp.php and lib/snmpagent.php insert end-of-options delimiter before targets', function () use ($root) {
	$snmpSource  = file_get_contents($root . '/lib/snmp.php');
	$agentSource = file_get_contents($root . '/lib/snmpagent.php');

	expect(substr_count($snmpSource, "'--', snmp_format_target_arg(\$hostname, \$port)"))->toBe(3)
		->and(substr_count($snmpSource, "\$args[] = '--';"))->toBe(2)
		->and(substr_count($agentSource, "'--',"))->toBeGreaterThanOrEqual(3);
});

test('lib/snmpagent.php filters disabled notification receivers without numeric type juggling', function () use ($root) {
	$agentSource = file_get_contents($root . '/lib/snmpagent.php');

	expect($agentSource)->not->toContain('snmpagent_managers.disabled = 0')
		->and($agentSource)->toContain("WHERE (snmpagent_managers.disabled = '' OR snmpagent_managers.disabled IS NULL)")
		->and($agentSource)->toContain("WHERE (sm.disabled = '' OR sm.disabled IS NULL)");
});

test('lib/api_device.php and managers.php reject leading dash or whitespace hostnames', function () use ($root) {
	$deviceSource   = file_get_contents($root . '/lib/api_device.php');
	$managersSource = file_get_contents($root . '/managers.php');

	expect($deviceSource)->toContain("\$save['hostname'][0] === '-'")
		->and($managersSource)->toContain("\$save['hostname'][0] === '-'");
});

test('managers.php tooltip renders via safe DOM nodes without inline handlers or innerHTML', function () use ($root) {
	$managersSource = file_get_contents($root . '/managers.php');

	expect($managersSource)->not->toContain('div.innerHTML =')
		->and($managersSource)->not->toContain('onMouseMove="showTooltip(')
		->and($managersSource)->toContain('titleEl.textContent = title;')
		->and($managersSource)->toContain('preEl.textContent = desc;')
		->and($managersSource)->toContain('data-tooltip-title=');
});

test('snmpagent MIB cache uses atomic JSON instead of executable PHP include', function () use ($root) {
	$persistSource = file_get_contents($root . '/snmpagent_persist.php');
	$childSource   = file_get_contents($root . '/snmpagent_mibcachechild.php');

	expect($persistSource)->not->toContain('include( $path_mibcache )')
		->and($persistSource)->toContain('!is_link($path_mibcache)')
		->and($persistSource)->toContain('json_decode($raw_cache, true)')
		->and($childSource)->not->toContain('var_export($cache')
		->and($childSource)->toContain('json_encode($cache)')
		->and($childSource)->toContain('chmod($tmp_cache, 0600)')
		->and($childSource)->toContain('rename($tmp_cache, $path_mibcache)');
});

test('host_templates.php enforces server-side in-use check before deleting templates', function () use ($root) {
	$source = file_get_contents($root . '/host_templates.php');

	expect($source)->toContain('SELECT DISTINCT host_template_id')
		->and($source)->toContain('host_template_in_use')
		->and($source)->not->toContain('UPDATE host SET host_template_id = 0');
});

test('lib/database.php redacts sensitive values before logging in sql_save and _db_replace', function () use ($root) {
	$source = file_get_contents($root . '/lib/database.php');

	expect($source)->toContain('cacti_redact_sensitive($array_items)')
		->and($source)->toContain("ERROR: SQL Save Failed for Table '\$table'.  Columns: '\$col_names'")
		->and($source)->not->toContain("ERROR: SQL Save Failed for Table '\$table'.  SQL:'\" . clean_up_lines(\$sql)");
});

test('installer validates PHP binary paths with cacti_exec, guards AutomationRange, and escapes debug DOM', function () use ($root) {
	$installerSource = file_get_contents($root . '/lib/installer.php');
	$functionsSource = file_get_contents($root . '/install/functions.php');
	$jsSource        = file_get_contents($root . '/install/install.js');

	expect($installerSource)->not->toContain('shell_exec(')
		->and($installerSource)->toContain("preg_match('/^php[0-9.]*(?:-cgi|-fpm)?(?:\\.exe)?$/i', basename(\$path))")
		->and($installerSource)->toContain('cacti_exec($path,')
		->and($installerSource)->toContain('if ($valid_range) {')
		->and($installerSource)->toContain("html_escape((string) \$recommend['current'])")
		->and($functionsSource)->not->toContain('shell_exec(')
		->and($functionsSource)->toContain('cacti_exec($php_binary,')
		->and($jsSource)->not->toContain("debug.html('<h5 style=\"border: 1px dashed grey\">' + JSON.stringify(debugData)")
		->and($jsSource)->toContain('.text(JSON.stringify(debugData))');
});
