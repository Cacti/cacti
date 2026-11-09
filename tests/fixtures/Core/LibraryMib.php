<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Parse a complete owned MIB and exercise the installed cache through its public API. */
function library_mib(): void {
	require_once CACTI_PATH_LIBRARY . '/mib_cache.php';
	require_once CACTI_PATH_LIBRARY . '/mib_parser.php';
	$name = 'COVERAGE-' . strtoupper(bin2hex(random_bytes(6))) . '-MIB';
	$path = tempnam(sys_get_temp_dir(), 'cacti-mib-');
	library_expect(is_string($path), true, 'MIB fixture owns its source file');
	$memory    = ini_get('memory_limit');
	$reporting = error_reporting();
	$cache     = new MibCache($name);
	$text      = <<<'MIB'
COVERAGE-MIB DEFINITIONS ::= BEGIN
coverageRoot MODULE-IDENTITY
 LAST-UPDATED "202610190000Z"
 ORGANIZATION "Isolated test fixture"
 CONTACT-INFO "No external contact"
 DESCRIPTION "Disposable parser fixture"
 ::= { enterprises 999999 }
CoverageText ::= TEXTUAL-CONVENTION
 STATUS current
 DESCRIPTION "Fixture text"
 SYNTAX OCTET STRING (SIZE (0..80))
coverageValue OBJECT-TYPE
 SYNTAX Counter32
 MAX-ACCESS read-only
 STATUS current
 DESCRIPTION "Counter with 'quotes' and
 multiple lines"
 ::= { coverageRoot 1 }
coverageTable OBJECT-TYPE
 SYNTAX SEQUENCE OF CoverageEntry
 MAX-ACCESS not-accessible
 STATUS current
 DESCRIPTION "Fixture table"
 ::= { coverageRoot 2 }
coverageEntry OBJECT-TYPE
 SYNTAX CoverageEntry
 MAX-ACCESS not-accessible
 STATUS current
 DESCRIPTION "Fixture row"
 INDEX { coverageIndex }
 ::= { coverageTable 1 }
coverageIndex OBJECT-TYPE
 SYNTAX INTEGER (1..999)
 MAX-ACCESS read-only
 STATUS current
 DESCRIPTION "Fixture index"
 ::= { coverageEntry 1 }
coverageLabel OBJECT-TYPE
 SYNTAX OCTET STRING (SIZE (0..80))
 MAX-ACCESS read-write
 STATUS current
 DESCRIPTION "Fixture label"
 ::= { coverageEntry 2 }
coverageAlert NOTIFICATION-TYPE
 OBJECTS { coverageValue, coverageLabel }
 STATUS current
 DESCRIPTION "Fixture notification"
 ::= { coverageRoot 3 }
coverageGroup OBJECT-GROUP
 OBJECTS { coverageValue }
 STATUS current
 DESCRIPTION "Fixture group"
 ::= { coverageRoot 4 }
coverageAlerts NOTIFICATION-GROUP
 NOTIFICATIONS { coverageAlert }
 STATUS current
 DESCRIPTION "Fixture notification group"
 ::= { coverageRoot 5 }
CoverageOid ::= OBJECT IDENTIFIER
coverageType OBJECT-TYPE
 SYNTAX CoverageOid
 MAX-ACCESS read-only
 STATUS current
 DESCRIPTION "Fixture object identifier"
 ::= { coverageRoot 6 }
END
MIB;
	$text = str_replace('COVERAGE-MIB', $name, $text);

	try {
		library_expect(file_put_contents($path, $text), strlen($text), 'MIB source is complete');
		library_expect($cache->install($path), true, 'Complete MIB installs through the parser');
		library_expect($cache->install($path), false, 'Duplicate MIB installation is refused');

		foreach (['coverageRoot' => ['Node', '.1.3.6.1.4.1.999999'], 'coverageValue' => ['Scalar', '.1.3.6.1.4.1.999999.1.0'],
			'coverageTable'         => ['Table', '.1.3.6.1.4.1.999999.2'], 'coverageEntry' => ['Row', '.1.3.6.1.4.1.999999.2.1'],
			'coverageLabel'         => ['Column', '.1.3.6.1.4.1.999999.2.1.2'], 'coverageAlert' => ['Notification', '.1.3.6.1.4.1.999999.3'],
			'coverageGroup'         => ['Group', '.1.3.6.1.4.1.999999.4'], 'coverageAlerts' => ['Group', '.1.3.6.1.4.1.999999.5'],
			'coverageType'          => ['Scalar', '.1.3.6.1.4.1.999999.6.0']] as $object => [$kind, $oid]) {
			$row = $cache->object($object)->get();
			library_expect([$row['kind'], $row['oid'], $row['mib']], [$kind, $oid, $name], 'Parser classifies and resolves ' . $object);
		}
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmpagent_cache_textual_conventions WHERE mib = ?', [$name]), 1, 'Textual convention is stored separately');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmpagent_cache WHERE mib = ? AND name = ?', [$name, 'CoverageOid']), 0, 'A type assignment is not cached as an object');
		library_expect(array_column(db_fetch_assoc_prepared('SELECT attribute FROM snmpagent_cache_notifications WHERE mib = ? ORDER BY sequence_id', [$name]), 'attribute'), ['coverageValue', 'coverageLabel'], 'Notification preserves declared varbind order');
		library_expect($cache->object('coverageValue')->set('4294967295'), true, 'Scalar value persists');
		library_expect($cache->count(), true, 'Counter update succeeds');
		library_expect((string) $cache->get()['value'], '0', 'Counter32 wraps at its declared width');

		foreach ([1 => "first ' label", 2 => 'second & label'] as $index => $label) {
			library_expect($cache->table('coverageTable')->row($index)->insert(['coverageIndex' => (string) $index, 'coverageLabel' => $label]), true, 'Cache inserts a typed row');
			$rows = db_fetch_assoc_prepared('SELECT name, value FROM snmpagent_cache WHERE mib = ? AND otype = ? AND oid LIKE ? ORDER BY name', [$name, 'DATA', '.1.3.6.1.4.1.999999.2.1.%.' . $index]);
			library_expect(array_column($rows, 'value', 'name'), ['coverageIndex' => (string) $index, 'coverageLabel' => $label], 'Inserted typed row preserves literal values in storage');
		}
		library_expect($cache->table('coverageTable')->row(1)->update(['coverageLabel' => "updated ' label"]), true, 'Cache updates an existing typed row');
		library_expect(db_fetch_cell_prepared('SELECT value FROM snmpagent_cache WHERE mib = ? AND name = ? AND oid = ?', [$name, 'coverageLabel', '.1.3.6.1.4.1.999999.2.1.2.1']), "updated ' label", 'Updated typed row preserves literal values in storage');
		library_expect($cache->install($path, true), true, 'Explicit replacement reinstalls a parsed definition');
		library_expect($cache->uninstall(), true, 'Owned MIB can be uninstalled');

		foreach (['snmpagent_mibs' => 'name', 'snmpagent_cache' => 'mib', 'snmpagent_cache_notifications' => 'mib', 'snmpagent_cache_textual_conventions' => 'mib'] as $table => $column) {
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?', [$name]), 0, 'Uninstall clears all owned MIB metadata');
		}
	} finally {
		$cache->uninstall();
		unlink($path);
		ini_set('memory_limit', $memory);
		error_reporting($reporting);
	}
	// plugin hooks stop dispatching once any plugin install runs in this process, so this precedes the plugin fixtures
	library_snmpagent_poller();
}

/** Register the fixture plugin's maintenance hook so the agent treats devices as in service. */
function library_snmpagent_plugin_setup(): void {
	library_expect(api_plugin_register_hook('coverageagent', 'is_device_in_maintenance', 'coverageagent_maintenance', 'setup.php', true), true, 'Plugin registers a maintenance hook');
}

/** Rebuild the agent cache and refresh it after a device transition so the transition raises its notifications. */
function library_snmpagent_poller(): void {
	global $config;
	require_once CACTI_PATH_LIBRARY . '/snmpagent.php';
	$directory     = CACTI_PATH_PLUGINS . '/coverageagent';
	$previous      = read_config_option('enable_snmp_agent');
	$initialized   = snmpagent_cache_initialized();
	$notifications = ['cactiNotifyDeviceFailedPoll', 'cactiNotifyDeviceRecovering'];
	$device        = library_save_device(['description' => 'Agent transition fixture', 'snmp_version' => 0]);
	library_expect($device > 0, true, 'Agent transition device exists');
	library_expect(file_exists($directory), false, 'Agent fixture never replaces an installed plugin');
	library_expect(mkdir($directory, 0700), true, 'Agent fixture owns its plugin directory');

	try {
		// without a maintenance plugin the hook echoes the device id back, which reads as in maintenance
		$setup = "<?php\nfunction coverageagent_maintenance(mixed \$device): bool {\n\treturn false;\n}\n";
		library_expect(file_put_contents($directory . '/setup.php', $setup), strlen($setup), 'Agent fixture writes its plugin entry point');
		library_expect(db_execute_prepared('INSERT INTO plugin_config (directory, name, author, version, status) VALUES (?, ?, ?, ?, ?)', ['coverageagent', 'Disposable agent fixture', 'Cacti tests', '1.0.0', 1]), true, 'Agent fixture seeds an installed plugin');
		library_snmpagent_plugin_setup();
		library_expect(db_execute_prepared('UPDATE host SET disabled = ?, status = ?, failed_polls = 0 WHERE id = ?', ['', HOST_DOWN, $device]), true, 'Agent fixture starts from a down device');
		set_config_option('enable_snmp_agent', 'on');

		foreach ($notifications as $notification) {
			unset($config['snmpagent']['notifications']['ignore'][$notification]);
		}
		library_expect(snmpagent_cache_install(), true, 'Enabled agent rebuilds its cache from the shipped MIBs');
		$mc = new MibCache();
		library_expect((string) $mc->mib('CACTI-BOOST-MIB')->object('boostApplRrdUpdateMaxRecordsPerSelect')->get()['value'], (string) read_config_option('boost_rrd_update_max_records_per_select', true), 'Agent cache publishes the boost select size');
		library_expect(db_execute_prepared('UPDATE host SET status = ?, failed_polls = 1 WHERE id = ?', [HOST_RECOVERING, $device]), true, 'Device recovers after a failed poll');
		library_expect(snmpagent_poller_bottom(), true, 'Agent poller refresh completes');

		foreach ($notifications as $notification) {
			library_expect($config['snmpagent']['notifications']['ignore'][$notification] ?? 0, 1, 'Device transition raises ' . $notification);
		}
		$states = array_column((new MibCache())->mib('CACTI-MIB')->table('cactiApplDeviceTable')->select(['cactiApplDeviceIndex', 'cactiApplDeviceStatus']), 'cactiApplDeviceStatus', 'cactiApplDeviceIndex');
		library_expect((int) ($states[$device] ?? 0), HOST_RECOVERING, 'Agent cache publishes the recovering device state');
	} finally {
		foreach ($notifications as $notification) {
			unset($config['snmpagent']['notifications']['ignore'][$notification]);
		}

		if (!$initialized) {
			snmpagent_cache_uninstall();
		}
		set_config_option('enable_snmp_agent', $previous);
		db_execute_prepared('DELETE FROM plugin_hooks WHERE name = ?', ['coverageagent']);
		db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', ['coverageagent']);

		foreach (glob($directory . '/*') as $owned) {
			unlink($owned);
		}
		rmdir($directory);
		api_device_remove($device);
	}
}
