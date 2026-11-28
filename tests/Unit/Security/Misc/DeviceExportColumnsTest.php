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
 * The device list export writes the device rows as CSV. The probe runs the
 * unchanged host_export() of host.php with the device rows given here.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

test('the device export leaves out the SNMP credentials and keeps the other columns', function () {
	$device = array(
		'id'                   => '1',
		'description'          => 'router',
		'hostname'             => '192.0.2.1',
		'snmp_community'       => 'community-value',
		'snmp_version'         => '3',
		'snmp_username'        => 'monitor',
		'snmp_password'        => 'auth-value',
		'snmp_auth_protocol'   => 'SHA',
		'snmp_priv_passphrase' => 'priv-value',
		'snmp_priv_protocol'   => 'AES',
		'graphs'               => '4',
	);

	$verdict = cacti_import_export_probe(array(
		'slice'   => array(array('host.php', 'host_export'), array('lib/functions.php', 'cacti_csv_safe')),
		'returns' => array('get_device_records' => array($device, array_merge($device, array('description' => 'switch', 'hostname' => '192.0.2.2')))),
		'call'    => 'host_export',
	));

	$rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($verdict['output'])));

	expect($verdict['error'])->toBeNull()
		->and($rows[0])->toBe(array('id', 'description', 'hostname', 'snmp_version', 'snmp_username', 'snmp_auth_protocol', 'snmp_priv_protocol', 'graphs'))
		->and($rows[1])->toBe(array('1', 'router', '192.0.2.1', '3', 'monitor', 'SHA', 'AES', '4'))
		->and($rows[2][1])->toBe('switch')
		->and($verdict['output'])->not->toContain('-value');
});
