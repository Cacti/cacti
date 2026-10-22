<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

ini_set('zend.exception_ignore_args', '1');

if (getenv('CACTI_INTERFACE_CATALOG_REAL_DB') !== '1') {
	throw new RuntimeException('Enable the isolated interface catalog database probe explicitly.');
}

// Disposable-schema probe using the real bootstrap, importer and SQL helpers.
require dirname(__DIR__) . '/bootstrap-unit.php';
require_once CACTI_PATH_LIBRARY . '/import.php';
require_once CACTI_PATH_LIBRARY . '/utility.php';
require_once CACTI_PATH_LIBRARY . '/xml.php';

$name = getenv('CACTI_TEST_DB_NAME') ?: '';

if (!preg_match('/^cacti_(?:test|interface_catalog_[a-z0-9]+)$/D', $name)) {
	throw new RuntimeException('Select an isolated cacti_test or cacti_interface_catalog_* database.');
}
$host = getenv('CACTI_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('CACTI_TEST_DB_PORT') ?: '3306';

if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !preg_match('/^[0-9]{1,5}$/D', $port) || (int) $port < 1 || (int) $port > 65535) {
	throw new RuntimeException('Use a loopback disposable database and a valid port.');
}
$conn = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('CACTI_TEST_DB_USER') ?: 'cacti', getenv('CACTI_TEST_DB_PASS') ?: '', [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$tables = ['snmp_query', 'snmp_query_graph', 'snmp_query_graph_rrd', 'snmp_query_graph_sv', 'snmp_query_graph_rrd_sv', 'settings'];

foreach ($tables as $table) {
	$query = $conn->prepare('SHOW TABLES LIKE ?');
	$query->execute([$table]);

	if ($query->fetchColumn() !== false) {
		throw new RuntimeException('Refusing an existing fixture table: ' . $table);
	}
}
$created = [];

try {
	$schema = file_get_contents(CACTI_PATH_BASE . '/cacti.sql');

	foreach ($tables as $table) {
		if (!preg_match('/^CREATE TABLE `?' . $table . '`? \(.*?^\)[^;]*;/ms', $schema, $definition)) {
			throw new RuntimeException('Missing canonical table: ' . $table);
		}
		$conn->exec($definition[0]);
		$created[] = $table;
	}
	$conn->exec("INSERT INTO settings (name, value) VALUES ('log_destination','0'),('log_verbosity','0'),('selective_debug',''),('selective_plugin_debug','')");
	$database_hostname                                                        = 'interface_catalog_fixture';
	$database_port                                                            = '0';
	$database_default                                                         = $name;
	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
	$config[OPTIONS_CLI]                                                      = ['log_destination' => '0', 'log_verbosity' => '0', 'selective_debug' => '', 'selective_plugin_debug' => ''];
	$preview_only                                                             = false;
	$import_debug_info                                                        = [];
	$import_messages                                                          = [];
	$legacy_template                                                          = false;

	$package  = simplexml_load_string(gzdecode(file_get_contents(CACTI_PATH_BASE . '/install/templates/Generic_SNMP_Device.xml.gz')));
	$template = null;

	foreach ($package->files->file as $file) {
		if ((string) $file->name === 'Generic_SNMP_Device.xml') {
			$template = base64_decode((string) $file->data, true);
		}
	}

	if (!is_string($template)) {
		throw new RuntimeException('Missing packaged native template.');
	}
	$objects = xml2array($template);
	$queries = array_filter($objects, static fn ($object) => ($object['xml_path'] ?? '') === '<path_cacti>/resource/snmp_queries/interface.xml');

	if (count($queries) !== 1) {
		throw new RuntimeException('Expected one native interface query.');
	}
	$hash       = parse_xml_hash(array_key_first($queries))['hash'];
	$definition = reset($queries);
	$cache      = [];
	$next       = 100;
	$remember   = static function ($value) use (&$remember, &$cache, &$next): void {
		if (is_array($value)) {
			foreach ($value as $key => $child) {
				$remember($key);
				$remember($child);
			}
		} elseif (is_string($value) && preg_match('/^hash_[a-f0-9]{38}$/D', $value)) {
			$parsed = parse_xml_hash($value);

			if (!is_array($parsed)) {
				throw new RuntimeException('Invalid native dependency hash.');
			}
			$cache[$parsed['type']][$parsed['hash']] ??= ++$next;
		}
	};
	$remember($objects);
	unset($cache['data_query'][$hash]);
	$files = [];

	if (!is_array(xml_to_data_query($hash, $definition, $cache, $files))) {
		throw new RuntimeException('Native query import failed.');
	}
	$snapshot = static function () use ($conn, $tables): array {
		$rows = [];

		foreach (array_diff($tables, ['settings']) as $table) {
			$rows[$table] = $conn->query('SELECT * FROM `' . $table . '` ORDER BY 1, 2')->fetchAll();
		}

		return $rows;
	};
	$first  = $snapshot();
	$checks = [
		'query_imported'                => count($first['snmp_query'])                                              === 1 && $import_debug_info['result'] === 'success',
		'existing_query_hash_preserved' => $first['snmp_query'][0]['hash']                                          === $hash,
		'query_resource_found'          => ($files[CACTI_PATH_BASE . '/resource/snmp_queries/interface.xml'] ?? '') === 'found',
		'all_associations_imported'     => count($first['snmp_query_graph'])                                        === count($definition['graphs']),
		'rrd_bindings_imported'         => count($first['snmp_query_graph_rrd']) > 0,
	];

	if (!is_array(xml_to_data_query($hash, $definition, $cache, $files))) {
		throw new RuntimeException('Native query reimport failed.');
	}
	$checks['reimport_identical_rows_and_ids'] = $snapshot() === $first;
	$custom                                    = md5('operator additional interface title');
	$insert                                    = $conn->prepare('INSERT INTO snmp_query_graph_sv (hash,snmp_query_graph_id,sequence,field_name,text) VALUES (?,?,999,?,?)');
	$insert->execute([$custom, $first['snmp_query_graph'][0]['id'], 'title', 'Operator interface label']);

	if (!is_array(xml_to_data_query($hash, $definition, $cache, $files, false))) {
		throw new RuntimeException('Native preserving reimport failed.');
	}
	$count = $conn->prepare('SELECT COUNT(*) FROM snmp_query_graph_sv WHERE hash = ?');
	$count->execute([$custom]);
	$checks['additional_suggested_value_preserved_by_default'] = (int) $count->fetchColumn() === 1;

	if (!is_array(xml_to_data_query($hash, $definition, $cache, $files, true))) {
		throw new RuntimeException('Native replacing reimport failed.');
	}
	$count->execute([$custom]);
	$checks['explicit_replacement_removes_additional_suggested_value'] = (int) $count->fetchColumn() === 0;
	$last                                                              = $snapshot();
	$checks['query_and_association_ids_preserved_after_replacement']   = $last['snmp_query'] === $first['snmp_query'] && $last['snmp_query_graph'] === $first['snmp_query_graph'];
	$report                                                            = ['checks' => $checks, 'php' => PHP_VERSION, 'database' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION)];

	if (in_array(false, $checks, true)) {
		throw new RuntimeException(json_encode($report, JSON_THROW_ON_ERROR));
	}
	echo json_encode($report, JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
	foreach (array_reverse($created) as $table) {
		$conn->exec('DROP TABLE `' . $table . '`');
	}
}
