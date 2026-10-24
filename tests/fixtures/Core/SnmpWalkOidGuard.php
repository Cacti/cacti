<?php
declare(strict_types=1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Test\SnmpWalkOidGuard;

use RuntimeException;

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require dirname(__DIR__, 2) . '/bootstrap-unit.php';
$root = dirname(__DIR__, 3);
require $root . '/lib/snmp.php';

$config['library_path'] = $root . '/lib';
$config['include_path'] = $root . '/include';
$config['php_snmp_support'] = false;
$config['config_options_array']['log_verbosity'] = POLLER_VERBOSITY_NONE;
$config['config_options_array']['oid_increasing_check_disable'] = '';
$queryXml = array();
$writes = array();
$pushes = array();

// Keep database and remote-collector I/O inside this isolated process.
function db_fetch_row_prepared($sql, $parameters) {
	return strpos($sql, 'host_snmp_query') !== false
		? array('reindex_method' => DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE)
		: array('id' => $parameters[0]);
}
function db_fetch_cell_prepared($sql, $parameters) {
	return DATA_INPUT_TYPE_SNMP_QUERY;
}
function db_fetch_assoc_prepared($sql, $parameters) {
	return array(array('snmp_index' => '1'), array('snmp_index' => '2'));
}
function get_data_query_array($id) {
	global $queryXml;

	return $queryXml;
}
function db_qstr($value) {
	return "'" . str_replace("'", "''", (string) $value) . "'";
}
function db_execute($sql) {
	global $writes;
	$writes[] = $sql;

	return true;
}
function db_execute_prepared($sql, $parameters) {
	return true;
}
function poller_push_reindex_only_data_to_main($host, $query) {
	global $pushes;
	$pushes[] = array($host, $query);
}
function cacti_log(...$arguments) {
}

\cacti_fixture_source_function($root . '/lib/snmp.php', 'cacti_snmp_walk', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/poller.php', 'update_reindex_cache', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/poller.php', 'poller_update_poller_reindex_from_buffer', __NAMESPACE__);

$directory = sys_get_temp_dir() . '/cacti-snmp-oid-' . bin2hex(random_bytes(8));

if (!mkdir($directory, 0700)) {
	throw new RuntimeException('Cannot create the owned walk fixture directory');
}

try {
	$script = "#!/bin/sh\nprintf '%s\\n' \"\$@\" > " . escapeshellarg($directory . '/args') . "\n";

	if (file_put_contents($directory . '/walk', $script) === false || !chmod($directory . '/walk', 0700)) {
		throw new RuntimeException('Cannot create the walk fixture executable');
	}
	$config['config_options_array']['path_snmpbulkwalk'] = $directory . '/walk';
	$config['config_options_array']['path_snmpwalk'] = $directory . '/walk';
	$walks = 0;

	foreach (array('', '  ', '.1.3.6.1.2.1.2.2.1.1') as $oid) {
		if (file_exists($directory . '/args')) {
			unlink($directory . '/args');
		}
		$result = cacti_snmp_walk('192.0.2.1', 'public', $oid, 2);
		$arguments = file_exists($directory . '/args') ? file($directory . '/args', FILE_IGNORE_NEW_LINES) : null;

		if ($oid === '' || $oid === '  ') {
			if ($arguments !== null || $result !== array()) {
				throw new RuntimeException('An empty OID reached the walk executable');
			}
		} elseif ($arguments === null || end($arguments) !== $oid) {
			throw new RuntimeException('The valid OID did not reach the walk executable');
		}
		$walks++;
	}

	$cases = array(
		array(array(), null, null),
		array(array('oid_index' => ''), null, null),
		array(array('oid_index' => '.1.3.6.1.2.1.2.2.1.1'), POLLER_ACTION_SNMP_COUNT, '.1.3.6.1.2.1.2.2.1.1'),
		array(array('oid_num_indexes' => '.1.3.6.1.2.1.2.1.0'), POLLER_ACTION_SNMP, '.1.3.6.1.2.1.2.1.0'),
		array(array('oid_index' => '.1.3.6.1.2.1.2.2.1.1', 'oid_num_indexes' => '.1.3.6.1.2.1.2.1.0'), POLLER_ACTION_SNMP, '.1.3.6.1.2.1.2.1.0'),
	);

	foreach ($cases as list($queryXml, $action, $oid)) {
		$writes = array();
		$pushes = array();
		update_reindex_cache(1, 2);

		if ($action === null) {
			if ($writes !== array() || $pushes !== array()) {
				throw new RuntimeException('A missing index OID created an unusable reindex check');
			}
		} elseif (count($writes) !== 1 || !preg_match('/\(1, 2,\s*(\d+),/', $writes[0], $match)
			|| (int) $match[1] !== $action || strpos($writes[0], db_qstr($oid)) === false
			|| $pushes !== array(array(1, 2))) {
			throw new RuntimeException('The valid reindex check lost its action, OID, or collector handoff');
		}
	}
	print json_encode(array('walk_cases' => $walks, 'reindex_cases' => count($cases)), JSON_THROW_ON_ERROR) . "\n";
} finally {
	foreach (array('args', 'walk') as $file) {
		if (file_exists($directory . '/' . $file)) {
			unlink($directory . '/' . $file);
		}
	}
	rmdir($directory);
}
