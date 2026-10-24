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
			'coverageGroup'         => ['Group', '.1.3.6.1.4.1.999999.4'], 'coverageAlerts' => ['Group', '.1.3.6.1.4.1.999999.5']] as $object => [$kind, $oid]) {
			$row = $cache->object($object)->get();
			library_expect([$row['kind'], $row['oid'], $row['mib']], [$kind, $oid, $name], 'Parser classifies and resolves ' . $object);
		}
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmpagent_cache_textual_conventions WHERE mib = ?', [$name]), 1, 'Textual convention is stored separately');
		library_expect(array_column(db_fetch_assoc_prepared('SELECT attribute FROM snmpagent_cache_notifications WHERE mib = ? ORDER BY sequence_id', [$name]), 'attribute'), ['coverageValue', 'coverageLabel'], 'Notification preserves declared varbind order');
		library_expect($cache->object('coverageValue')->set('4294967295'), true, 'Scalar value persists');
		library_expect($cache->count(), true, 'Counter update succeeds');
		library_expect((string) $cache->get()['value'], '0', 'Counter32 wraps at its declared width');

		foreach ([1 => "first ' label", 2 => 'second & label'] as $index => $label) {
			library_expect($cache->table('coverageTable')->row($index)->insert(['coverageIndex' => (string) $index, 'coverageLabel' => $label]), true, 'Cache inserts a typed row');
			$rows = db_fetch_assoc_prepared('SELECT name, value FROM snmpagent_cache WHERE mib = ? AND otype = ? AND oid LIKE ? ORDER BY name', [$name, 'DATA', '.1.3.6.1.4.1.999999.2.1.%.' . $index]);
			library_expect(array_column($rows, 'value', 'name'), ['coverageIndex' => (string) $index, 'coverageLabel' => $label], 'Inserted typed row preserves literal values in storage');
		}
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
}
