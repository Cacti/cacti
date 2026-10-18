<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli' || getenv('CACTI_E2E_DISPOSABLE') !== '1') {
	fwrite(STDERR, "This fixture requires an explicitly disposable Cacti database.\n");
	exit(2);
}

$complete                          = false;
$GLOBALS['cacti_fixture_contract'] = [
	'name'       => 'library-behavior',
	'complete'   => false,
	'assertions' => 0,
	'producers'  => [
		'tests/fixtures/Core/LibraryBehavior.php',
		'tests/fixtures/Core/LibraryCollection.php',
		'tests/fixtures/Core/LibraryAuthentication.php',
		'tests/fixtures/Core/LibraryStorage.php',
		'tests/fixtures/Core/LibraryForms.php',
		'tests/fixtures/Core/LibraryDatabase.php',
		'tests/fixtures/Core/LibraryPlugins.php',
		'tests/fixtures/Core/PluginProducer.php',
		'tests/fixtures/Core/QueryProducer.php',
		'tests/e2e/library-behavior.sh',
		'tests/Helpers/RequestCoverage.php',
		'tests/e2e/Dockerfile',
		'tests/e2e/entrypoint.sh',
		'tests/e2e/docker-compose.yml',
		'tests/fixtures/snmp-coverage/Dockerfile',
		'tests/fixtures/snmp-coverage/snmpd.conf',
		'cacti.sql',
		'composer.lock',
		'resource/snmp_queries/interface.xml',
		'install/templates/Local_Linux_Machine.xml.gz',
		'install/templates/NetSNMP_Device.xml.gz',
	],
];
register_shutdown_function(static function () use (&$complete): void {
	if (!$complete) {
		fwrite(STDERR, "FAIL: library behavior did not complete\n");
		exit(1);
	}
});

require __DIR__ . '/../../Helpers/RequestCoverage.php';
require __DIR__ . '/../../../include/global.php';

foreach (['xml', 'rrd', 'poller', 'data_query', 'utility', 'api_device', 'api_graph', 'api_data_source', 'api_tree', 'template', 'export', 'import', 'reports', 'api_scheduler', 'html_reports', 'aggregate', 'api_aggregate', 'spikekill'] as $library) {
	require_once CACTI_PATH_LIBRARY . '/' . $library . '.php';
}

$assertions = 0;

function library_expect(mixed $actual, mixed $expected, string $message): void {
	global $assertions;
	$assertions++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

function library_save_device(array $overrides = []): int {
	$_SESSION[SESS_ERROR_FIELDS] = [];
	$_SESSION['sess_messages']   = [];

	return api_device_save(...array_replace([
		'id'                   => 0, 'device_template_id' => 0, 'description' => 'Library coverage device',
		'hostname'             => '127.0.0.1', 'snmp_community' => "pub\tlic", 'snmp_version' => 3,
		'snmp_username'        => "us\ner", 'snmp_password' => "test\tpassword", 'snmp_port' => 161,
		'snmp_timeout'         => 100, 'disabled' => 'on', 'availability_method' => 0,
		'ping_method'          => 0, 'ping_port' => 0, 'ping_timeout' => 100, 'ping_retries' => 1,
		'notes'                => 'Fixture notes', 'snmp_auth_protocol' => 'SHA',
		'snmp_priv_passphrase' => "private\tpassword", 'snmp_priv_protocol' => 'AES',
		'snmp_context'         => "con\ntext", 'snmp_engine_id' => "en\ngine", 'site_id' => 0,
	], $overrides));
}

function library_xml_content(string $xml): string {
	$document                     = new DOMDocument();
	$document->preserveWhiteSpace = false;
	library_expect($document->loadXML($xml), true, 'Template XML parses');

	// The legacy exporter indents multiline installation instructions on each
	// export. Compare their content without that presentation indentation.
	foreach ($document->getElementsByTagName('installation') as $instructions) {
		$instructions->textContent = preg_replace('/\n[\t ]*/', "\n", $instructions->textContent);
	}

	return $document->C14N();
}

$_SESSION['sess_user_id'] = 1;
set_config_option('path_php_binary', PHP_BINARY);
set_config_option('auth_method', '1');
set_user_setting('hide_disabled', '', 1);

foreach (['snmpget', 'snmpgetnext', 'snmpwalk', 'snmpbulkwalk'] as $snmpBinary) {
	library_expect(is_executable('/usr/bin/' . $snmpBinary), true, 'The fixture has ' . $snmpBinary);
	set_config_option('path_' . $snmpBinary, '/usr/bin/' . $snmpBinary);
}

// Use the same signed packages that a fresh installation imports. Avoid
// replacing checked-in scripts: this fixture exercises database templates.
foreach (['Local_Linux_Machine', 'NetSNMP_Device'] as $package) {
	$path = CACTI_PATH_INSTALL . '/templates/' . $package . '.xml.gz';
	$info = import_package($path, info_only: true, limitex: false);
	library_expect(is_array($info) && count($info) > 0, true, 'Signed package metadata is readable');
	library_expect(import_package($path, limitex: false, import_files: ['no-files-selected']) !== false, true, 'Signed package imports');
}

foreach ([
	'host_template' => 'host_template', 'graph_template' => 'graph_templates',
	'data_template' => 'data_template', 'data_input_method' => 'data_input',
	'data_query'    => 'snmp_query', 'gprint_preset' => 'graph_templates_gprint',
	'cdef'          => 'cdef', 'data_source_profile' => 'data_source_profiles',
] as $type => $table) {
	$rows = db_fetch_assoc('SELECT id FROM ' . $table . ' ORDER BY id');
	library_expect(count($rows) > 0, true, 'Package supplies ' . $type);
	$id  = (int) $rows[0]['id'];
	$xml = get_item_xml($type, $id, true);
	library_expect(simplexml_load_string($xml) !== false, true, 'Exported ' . $type . ' is valid XML');
	$beforeImport = (int) db_fetch_cell('SELECT COUNT(*) FROM ' . $table);
	$preview_only = false;
	library_expect(import_xml_data($xml, false, 1) !== false, true, 'Exported ' . $type . ' imports');
	library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM ' . $table), $beforeImport, 'Reimport preserves ' . $type . ' identities');
	$roundTrip = get_item_xml($type, $id, true);
	library_expect(library_xml_content($roundTrip) === library_xml_content($xml), true, 'Reimport preserves ' . $type . ' and dependency values');
}

$before = (int) db_fetch_cell('SELECT COUNT(*) FROM host');
library_expect(library_save_device(['hostname' => '']), 0, 'An empty hostname is rejected');
library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM host'), $before, 'Invalid input does not insert a device');

$device = library_save_device();
library_expect($device > 0, true, 'A disabled SNMPv3 device is created');
$stored = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device]);

foreach (['snmp_community' => 'public', 'snmp_username' => 'user', 'snmp_password' => 'testpassword', 'snmp_priv_passphrase' => 'privatepassword', 'snmp_context' => 'context', 'snmp_engine_id' => 'engine'] as $field => $expected) {
	library_expect($stored[$field], $expected, 'Control bytes are removed from ' . $field);
}
library_expect($stored['disabled'], 'on', 'Creation preserves disabled state');
library_expect(library_save_device(['id' => $device, 'description' => 'Updated fixture', 'snmp_version' => 0]), $device, 'Updating preserves device identity');
$stored = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device]);
library_expect($stored['description'], 'Updated fixture', 'Device description persists');
library_expect($stored['snmp_username'], '', 'Disabling SNMP clears v3 username');
library_expect($stored['snmp_password'], '', 'Disabling SNMP clears v3 password');

// Query only the runner's isolated SNMP agent, never a real monitored device.
$queryDevice = library_save_device(['description' => 'SNMP fixture', 'hostname' => 'snmp-fixture',
	'snmp_version'                                   => 2, 'snmp_community' => 'public']);
library_expect($queryDevice > 0, true, 'The disposable SNMP device is created');
$interfaceQuery = (int) db_fetch_cell_prepared('SELECT id FROM snmp_query WHERE xml_path = ? ORDER BY id LIMIT 1', ['<path_cacti>/resource/snmp_queries/interface.xml']);
library_expect($interfaceQuery > 0, true, 'The installed package supplies the interface query');
library_expect(db_execute_prepared('INSERT INTO host_snmp_query (host_id, snmp_query_id, reindex_method) VALUES (?, ?, ?)', [$queryDevice, $interfaceQuery, 1]), true, 'Interface query is associated with its device');
library_expect(db_execute_prepared('UPDATE host SET disabled = ?, status = ?, bulk_walk_size = ? WHERE id = ?', ['', HOST_UP, 10, $queryDevice]), true, 'Only the isolated agent is enabled for the query');
library_expect(run_data_query($queryDevice, $interfaceQuery), true, 'Native SNMP interface query completes');
$interfaces = db_fetch_assoc_prepared('SELECT snmp_index, field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? ORDER BY snmp_index', [$queryDevice, $interfaceQuery, 'ifDescr']);
library_expect(count($interfaces) > 0, true, 'SNMP query persists interface descriptions');
library_expect(in_array('lo', array_column($interfaces, 'field_value'), true), true, 'The agent loopback interface reaches the database');
$indexCount = count($interfaces);
library_expect(run_data_query($queryDevice, $interfaceQuery), true, 'Repeated SNMP indexing succeeds');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ?', [$queryDevice, $interfaceQuery, 'ifDescr']), $indexCount, 'Repeated indexing does not duplicate interface rows');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_reindex WHERE host_id = ?', [$queryDevice]) > 0, true, 'Index discovery registers the production poller reindex check');
api_device_dq_change($queryDevice, $interfaceQuery, '2');
library_expect((int) db_fetch_cell_prepared('SELECT reindex_method FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$queryDevice, $interfaceQuery]), 2, 'Changing reindex policy persists');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ?', [$queryDevice, $interfaceQuery, 'ifDescr']), $indexCount, 'Changing policy preserves discovered interfaces');
api_device_dq_remove($queryDevice, $interfaceQuery);

foreach (['host_snmp_query' => 'snmp_query_id', 'host_snmp_cache' => 'snmp_query_id', 'poller_reindex' => 'data_query_id'] as $table => $column) {
	library_expect((int) db_fetch_cell_prepared("SELECT COUNT(*) FROM $table WHERE host_id = ? AND $column = ?", [$queryDevice, $interfaceQuery]), 0, 'Removing a query clears ' . $table);
}
api_device_dq_add($queryDevice, $interfaceQuery, '1');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ?', [$queryDevice, $interfaceQuery, 'ifDescr']), $indexCount, 'Readding a query rediscovers its interfaces');
api_device_dq_add($queryDevice, $interfaceQuery, '1');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$queryDevice, $interfaceQuery]), 1, 'Repeated query association keeps one mapping');
require __DIR__ . '/LibraryCollection.php';
library_collection_queries($queryDevice, $interfaceQuery);
api_device_remove($queryDevice);
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_cache WHERE host_id = ?', [$queryDevice]), 0, 'Removing the fixture clears its query cache');

$templateId       = (int) db_fetch_cell('SELECT graph_template_id FROM host_template_graph ORDER BY graph_template_id LIMIT 1');
$deviceTemplateId = (int) db_fetch_cell('SELECT host_template_id FROM host_template_snmp_query ORDER BY host_template_id LIMIT 1');
$deviceTemplate   = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$deviceTemplateId]);
library_expect($deviceTemplateId > 0, true, 'Package supplies a device template with queries');
$duplicatedTemplate = api_duplicate_device_template($deviceTemplateId, 'Fixture copy of <template_title>');
library_expect(is_int($duplicatedTemplate) && $duplicatedTemplate > 0 && $duplicatedTemplate !== $deviceTemplateId, true, 'Duplicating creates a distinct template identity');
$duplicateTemplateRow = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$duplicatedTemplate]);
library_expect($duplicateTemplateRow['name'], 'Fixture copy of ' . $deviceTemplate['name'], 'Template copy expands its title');
library_expect($duplicateTemplateRow['hash'] !== $deviceTemplate['hash'], true, 'Template copy has a new export identity');

foreach (['host_template_graph' => 'graph_template_id', 'host_template_snmp_query' => 'snmp_query_id'] as $table => $column) {
	$originalMappings = db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_template_id = ? ORDER BY $column", [$deviceTemplateId]);
	$copiedMappings   = db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_template_id = ? ORDER BY $column", [$duplicatedTemplate]);
	library_expect(count($originalMappings) > 0, true, 'Original template has ' . $table . ' associations');
	library_expect($copiedMappings, $originalMappings, 'Copy preserves ' . $table . ' associations');
	db_execute_prepared("DELETE FROM $table WHERE host_template_id = ?", [$duplicatedTemplate]);
	library_expect(db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_template_id = ? ORDER BY $column", [$deviceTemplateId]), $originalMappings, 'Deleting copy mappings preserves original template');
}
db_execute_prepared('DELETE FROM host_template WHERE id = ?', [$duplicatedTemplate]);
library_expect(db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$deviceTemplateId]), $deviceTemplate, 'Duplicating leaves the original template unchanged');
library_expect(api_duplicate_device_template(0, 'Missing template'), false, 'Missing template cannot be duplicated');
$suggested  = [];
$created    = create_complete_graph_from_template($templateId, $device, [], $suggested);
library_expect(is_array($created), true, 'Graph creation returns its identities');
$graphId = (int) $created['local_graph_id'];
library_expect($graphId > 0, true, 'Graph creation persists a graph');
library_expect((int) db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$graphId]), $device, 'Graph belongs to its device');
api_resize_graphs($graphId, 500, 200);
$dimensions = db_fetch_row_prepared('SELECT width, height FROM graph_templates_graph WHERE local_graph_id = ?', [$graphId]);
library_expect((int) $dimensions['width'], 500, 'Graph width persists');
library_expect((int) $dimensions['height'], 200, 'Graph height persists');

// Store and render actual samples through the production RRDtool boundary.
// Every data source belongs to this fixture's device in the disposable tree.
$rrdBinary = getenv('CACTI_TEST_RRDTOOL') ?: '/usr/bin/rrdtool';
library_expect(is_executable($rrdBinary), true, 'The fixture has a real RRDtool executable');
set_config_option('path_rrdtool', $rrdBinary);
set_config_option('boost_rrd_update_enable', '');
set_config_option('boost_png_cache_enable', '');
$dataIds = array_values($created['local_data_id']);
library_expect(count($dataIds) > 0, true, 'Graph creation supplies data sources');
$ownedRrdFiles = [];
$sampleEnd     = 0;
$sampleStep    = 0;

foreach ($dataIds as $dataId) {
	$dataId  = (int) $dataId;
	$rrdPath = get_data_source_path($dataId, true);
	library_expect(str_starts_with($rrdPath, CACTI_PATH_RRA . '/'), true, 'Fixture RRD stays in its disposable RRA directory');
	library_expect(file_exists($rrdPath), false, 'A new fixture never replaces an existing RRD');
	library_expect(rrdtool_function_create($dataId, false) !== false, true, 'RRDtool creates the data source');
	$ownedRrdFiles[] = $rrdPath;
	library_expect(is_file($rrdPath), true, 'The RRD file exists');
	$info = rrdtool_function_info($dataId);
	library_expect(is_array($info) && count($info['ds']) > 0, true, 'RRDtool metadata includes the stored data sources');
	$sampleStep = (int) $info['step'];
	library_expect($sampleStep > 0, true, 'The configured sample step is positive');
	$sampleStart = (intdiv(max(time(), (int) $info['last_update']), $sampleStep) + 1) * $sampleStep;
	$sampleEnd   = $sampleStart + 4 * $sampleStep;
	$values      = array_fill_keys(array_keys($info['ds']), 12.5);
	$times       = [];

	foreach (range(4, 0) as $offset) {
		$times[$sampleStart + $offset * $sampleStep] = $values;
	}
	$acknowledged = [];
	library_expect(rrdtool_function_update([$rrdPath => ['local_data_id' => $dataId, 'times' => $times]], null, $acknowledged), 5, 'Out-of-order input updates all five timestamps');
	$expectedTimes = array_keys($times);
	sort($expectedTimes);
	library_expect(array_keys($acknowledged[$rrdPath]), $expectedTimes, 'Acknowledgements follow stored timestamp order');
	$after = rrdtool_function_info($dataId);
	library_expect((int) $after['last_update'], $sampleEnd, 'RRDtool persisted the final timestamp');
	library_expect(rrdtool_function_create($dataId, false), -1, 'Creating an existing RRD preserves its samples');
	$fetched = rrdtool_function_fetch($dataId, $sampleStart, $sampleEnd, $sampleStep, true);
	library_expect($fetched['data_source_names'], array_keys($info['ds']), 'Fetch preserves data source identities');

	foreach ($fetched['values'] as $samples) {
		library_expect((float) $samples[$sampleStart + 2 * $sampleStep], 12.5, 'Fetch returns the stored gauge sample');
	}
	$fallback = rrdtool_function_fetch($dataId, $sampleStart, $sampleEnd, $sampleStep, true, '', 'invalid');
	library_expect($fallback, $fetched, 'An unsupported consolidation function falls back to AVERAGE');
}
$graphOptions = ['graph_start' => $sampleEnd - 3 * $sampleStep, 'graph_end' => $sampleEnd, 'image_format' => 'png'];
$image        = rrdtool_function_graph($graphId, '', $graphOptions);
library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Graph rendering returns a real PNG');
$imageInfo = getimagesizefromstring($image);
library_expect(is_array($imageInfo) && $imageInfo[0] >= 500 && $imageInfo[1] >= 200, true, 'Rendered image respects the graph dimensions');
$export = rrdtool_function_graph($graphId, '', $graphOptions + ['export_csv' => true]);
library_expect(is_array($export) && (int) $export['meta']['local_graph_id'] === $graphId, true, 'Graph export preserves graph identity');
library_expect(count($export['data']) > 0, true, 'Graph export includes actual samples');

$secondGraph   = create_complete_graph_from_template($templateId, $device, [], $suggested);
$secondGraphId = (int) $secondGraph['local_graph_id'];
library_expect($secondGraphId > 0 && $secondGraphId !== $graphId, true, 'Aggregate members have separate graph identities');
$sourceItems  = db_fetch_assoc_prepared('SELECT sequence FROM graph_templates_item WHERE local_graph_id = ? ORDER BY sequence', [$graphId]);
$itemDefaults = array_fill_keys(array_column($sourceItems, 'sequence'), 0);

foreach ([AGGREGATE_TOTAL_NONE, AGGREGATE_TOTAL_ALL, AGGREGATE_TOTAL_ONLY] as $totalMode) {
	$aggregateId = 0;
	$attributes  = [
		'graph_title'       => 'Library aggregate ' . $totalMode,
		'graph_template_id' => $templateId,
		'graph_type'        => AGGREGATE_GRAPH_TYPE_KEEP,
		'total'             => $totalMode, 'total_type' => (string) AGGREGATE_TOTAL_TYPE_SIMILAR,
		'total_prefix'      => 'Total', 'gprint_prefix' => '|host_description|',
		'reorder'           => AGGREGATE_ORDER_NONE, 'item_no' => count($sourceItems),
		'color_templates'   => [], 'graph_item_types' => $itemDefaults, 'cdefs' => $itemDefaults,
		'skipped_items'     => [], 'total_items' => array_combine(array_keys($itemDefaults), array_keys($itemDefaults)),
	];
	library_expect(aggregate_create_update($aggregateId, [$graphId, $secondGraphId], $attributes), true, 'Aggregate creation succeeds for total mode ' . $totalMode);
	library_expect($aggregateId > 0, true, 'Aggregate creation returns its graph identity');
	$aggregate = db_fetch_row_prepared('SELECT * FROM aggregate_graphs WHERE local_graph_id = ?', [$aggregateId]);
	library_expect($aggregate['title_format'], $attributes['graph_title'], 'Aggregate title persists');
	$members = db_fetch_assoc_prepared('SELECT local_graph_id FROM aggregate_graphs_items WHERE aggregate_graph_id = ? ORDER BY sequence', [$aggregate['id']]);
	library_expect(array_map('intval', array_column($members, 'local_graph_id')), [$graphId, $secondGraphId], 'Aggregate membership preserves source order');
	$itemsBefore = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ?', [$aggregateId]);
	library_expect($itemsBefore > 0, true, 'Aggregate has executable graph items');
	library_expect(aggregate_create_update($aggregateId, [$graphId, $secondGraphId], $attributes), true, 'Aggregate rebuild succeeds');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ?', [$aggregateId]), $itemsBefore, 'Aggregate rebuild does not duplicate graph items');
	api_aggregate_remove_multi([$aggregateId]);
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM aggregate_graphs_items WHERE aggregate_graph_id = ?', [$aggregate['id']]), 0, 'Removing aggregate clears membership');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_local WHERE id IN (?, ?)', [$graphId, $secondGraphId]), 2, 'Removing aggregate preserves member graphs');
}

// Explicit truth tables from the four documented graph permission models.
// Bits identify a graph, device, or template grant respectively.
$allowedMasks = [
	1 => [1, 2, 3, 4, 5, 6, 7],
	2 => [1, 3, 5, 6, 7],
	3 => [1, 2, 3, 5, 6, 7],
	4 => [1, 3, 4, 5, 6, 7],
];
$policyUser  = (int) sql_save(['id' => 0, 'username' => 'library-policy-user', 'password' => '', 'enabled' => 'on'], 'user_auth');
$policyGroup = (int) sql_save(['id' => 0, 'name' => 'Library policy group', 'enabled' => 'on'], 'user_auth_group');
library_expect($policyUser > 0 && $policyGroup > 0, true, 'Policy actors persist');

foreach (['user' => $policyUser, 'group' => $policyGroup] as $kind => $actor) {
	$table  = $kind === 'user' ? 'user_auth_perms' : 'user_auth_group_perms';
	$column = $kind === 'user' ? 'user_id' : 'group_id';

	foreach ([1, 2] as $defaultPolicy) {
		$policies = [['id' => $actor, 'type' => $kind, 'name' => 'Library policy group',
			'policy_graphs'   => $defaultPolicy, 'policy_hosts' => $defaultPolicy, 'policy_graph_templates' => $defaultPolicy]];

		foreach (range(0, 7) as $grants) {
			db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$actor]);

			foreach ([1 => [1, $graphId], 2 => [3, $device], 4 => [4, $templateId]] as $bit => [$type, $item]) {
				$isGranted = ($grants & $bit) !== 0;

				if ($isGranted === ($defaultPolicy === 2)) {
					library_expect(db_execute_prepared("INSERT INTO $table ($column, item_id, type) VALUES (?, ?, ?)", [$actor, $item, $type]), true, 'Permission exception persists');
				}
			}

			foreach ($allowedMasks as $method => $allowed) {
				set_config_option('graph_auth_method', (string) $method);
				$where = get_policy_where($method, $policies, 'WHERE gl.id = ' . $graphId);
				$rows  = db_fetch_assoc('SELECT gl.id FROM graph_local gl INNER JOIN host h ON h.id = gl.host_id ' . $where);
				library_expect(is_array($rows), true, 'Permission query executes');
				library_expect(count($rows) > 0, in_array($grants, $allowed, true), "$kind permissions: model $method, default $defaultPolicy, grants $grants");
				$graphPermission = ['disabled' => ''];

				foreach (['graph1' => 1, 'device1' => 2, 'template1' => 4] as $field => $bit) {
					$graphPermission[$field] = (($grants & $bit) !== 0) === ($defaultPolicy === 2) ? $actor : null;
				}
				$permissionHtml = get_permission_string($graphPermission, $policies);
				library_expect(str_contains($permissionHtml, "class='accessGranted'"), in_array($grants, $allowed, true), "Displayed $kind permission: model $method, default $defaultPolicy, grants $grants");
			}
		}
	}
	db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$actor]);
}
db_execute_prepared('DELETE FROM user_auth WHERE id = ?', [$policyUser]);
db_execute_prepared('DELETE FROM user_auth_group WHERE id = ?', [$policyGroup]);
set_config_option('graph_auth_method', '1');

$report = (int) sql_save([
	'id'          => 0, 'user_id' => 1, 'name' => 'Fixture <report>', 'enabled' => 'on',
	'font_size'   => 10, 'alignment' => 1, 'graph_columns' => 1,
	'graph_width' => 500, 'graph_height' => 200,
	'from_name'   => 'Fixture', 'from_email' => '', 'email' => '', 'bcc' => '',
], 'reports');
library_expect($report > 0, true, 'Report persists');
$textItem = (int) sql_save(['id' => 0, 'report_id' => $report, 'item_type' => REPORTS_ITEM_TEXT,
	'item_text'                     => '<script>fixture</script>', 'sequence' => 1], 'reports_items');
$graphItem = (int) sql_save(['id' => 0, 'report_id' => $report, 'item_type' => REPORTS_ITEM_GRAPH,
	'local_graph_id'                 => $graphId, 'item_text' => '', 'timespan' => GT_LAST_DAY, 'sequence' => 2], 'reports_items');
library_expect($textItem > 0 && $graphItem > 0, true, 'Report items persist');
$theme = 'modern';

foreach ([REPORTS_OUTPUT_STDOUT, REPORTS_OUTPUT_EMAIL] as $output) {
	$html = reports_generate_html($report, $output, $theme);
	library_expect(str_contains($html, 'Fixture &lt;report&gt;'), true, 'Report title is escaped');
	library_expect(str_contains($html, '&lt;script&gt;fixture&lt;/script&gt;'), true, 'Report text is escaped');
	library_expect(str_contains($html, '<script>'), false, 'Report text cannot create an executable element');
	library_expect(str_contains($html, $output === REPORTS_OUTPUT_EMAIL ? '<GRAPH:' . $graphId . ':' : 'local_graph_id=' . $graphId), true, 'Report includes the permitted graph');
}
$beforeItems            = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_items WHERE report_id = ?', [$report]);
$_SESSION[SESS_USER_ID] = 2;
library_expect(reports_add_graphs($report, $graphId, [], '1'), false, 'A different user cannot edit the report');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_items WHERE report_id = ?', [$report]), $beforeItems, 'Denied report mutation inserts nothing');
$_SESSION[SESS_USER_ID] = 1;
duplicate_reports($report, 'Copy of <name>');
$copy = db_fetch_row_prepared('SELECT * FROM reports WHERE name = ?', ['Copy of Fixture <report>']);
library_expect((int) $copy['id'] !== $report, true, 'Report copy has its own identity');
library_expect($copy['enabled'], '', 'Report copy starts disabled');
library_expect((int) $copy['user_id'], 1, 'Report copy belongs to the current user');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_items WHERE report_id = ?', [$copy['id']]), $beforeItems, 'Report copy keeps all items');
db_execute_prepared('DELETE FROM reports_items WHERE report_id IN (?, ?)', [$report, $copy['id']]);
db_execute_prepared('DELETE FROM reports WHERE id IN (?, ?)', [$report, $copy['id']]);

$_SESSION[SESS_ERROR_FIELDS] = [];
$_SESSION['sess_messages']   = [];
$tree                        = (int) sql_save(['id' => 0, 'name' => 'Library tree', 'enabled' => 'on', 'sort_type' => TREE_ORDERING_NONE], 'graph_tree');
library_expect($tree > 0, true, 'Tree persists');
ob_start();
$treeCreated = api_tree_create_node($tree, '#', 1, 'Fixture branch');
$treeJson    = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
library_expect($treeCreated, true, 'Tree branch is created');
library_expect($treeJson['text'], 'Fixture branch', 'Tree response contains its title');
$branch = (int) substr($treeJson['id'], strlen('tbranch:'));
library_expect((int) api_tree_branch_exists($tree, 0, 'Fixture branch'), $branch, 'Tree branch is persisted under the root');
$treeGraph = api_tree_item_save(0, $tree, TREE_ITEM_TYPE_GRAPH, $branch, '', $graphId, 0, 0, 1, TREE_ORDERING_INHERIT, false);
library_expect($treeGraph > 0, true, 'A graph can be added under the branch');
library_expect(api_tree_item_save(0, $tree, TREE_ITEM_TYPE_GRAPH, $branch, '', $graphId, 0, 0, 1, TREE_ORDERING_INHERIT, false), false, 'Duplicate graph leaves are refused');
api_tree_delete_node($tree, 'tbranch:' . $branch);
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_tree_items WHERE graph_tree_id = ?', [$tree]), 0, 'Deleting a branch removes its children');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_local WHERE id = ?', [$graphId]), 1, 'Deleting a tree branch preserves its graph');
db_execute_prepared('DELETE FROM graph_tree WHERE id = ?', [$tree]);

api_device_enable_devices([$device]);
library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device]), '', 'Bulk enable persists');
api_device_disable_devices([$device]);
library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device]), 'on', 'Bulk disable persists');
$_REQUEST['notes']       = 'Bulk fixture notes';
$_REQUEST['description'] = 'Must not be applied';
api_device_change_options([$device], ['t_notes' => 'on']);
$stored = db_fetch_row_prepared('SELECT description, notes FROM host WHERE id = ?', [$device]);
library_expect($stored['notes'], 'Bulk fixture notes', 'Selected bulk option persists');
library_expect($stored['description'], 'Updated fixture', 'Unselected bulk option stays unchanged');
$_REQUEST = [];
db_execute_prepared('UPDATE host SET total_polls = 10, failed_polls = 2, availability = 80 WHERE id = ?', [$device]);
api_device_clear_statistics([$device]);
$stored = db_fetch_row_prepared('SELECT total_polls, failed_polls, availability FROM host WHERE id = ?', [$device]);
library_expect((int) $stored['total_polls'], 0, 'Statistics reset clears polls');
library_expect((int) $stored['failed_polls'], 0, 'Statistics reset clears failures');
library_expect((float) $stored['availability'], 100.0, 'Statistics reset restores availability');

require __DIR__ . '/LibraryStorage.php';
library_storage($templateId);
require __DIR__ . '/LibraryForms.php';
library_forms();
require __DIR__ . '/LibraryDatabase.php';
library_spike_repair();

// An unavailable collector must retain local changes and report incomplete
// propagation. Disabled records keep every network path inside this fixture.
$offlinePoller = (int) sql_save(['id' => 0, 'name' => 'Offline fixture', 'hostname' => '127.0.0.1', 'disabled' => 'on'], 'poller');
library_expect($offlinePoller > 1, true, 'An isolated disabled collector exists');
library_expect(remote_poller_up($offlinePoller), false, 'Disabled collector is unavailable');
$remoteDevice = library_save_device(['description' => 'Remote fixture', 'snmp_version' => 0]);
library_expect($remoteDevice > 0, true, 'Remote fixture device exists');
library_expect(db_execute_prepared('UPDATE host SET poller_id = ? WHERE id = ?', [$offlinePoller, $remoteDevice]), true, 'Fixture device belongs to unavailable collector');

foreach ([
	static fn () => api_device_disable_devices([$remoteDevice]),
	static fn () => api_device_enable_devices([$remoteDevice]),
	static fn () => api_device_clear_statistics([$remoteDevice]),
] as $operation) {
	$_SESSION[SESS_MESSAGES] = [];
	$operation();
	library_expect($_SESSION[SESS_MESSAGES]['poller_down_' . $offlinePoller]['level'] ?? null, MESSAGE_LEVEL_WARN, 'Unavailable collector reports incomplete propagation');
}
library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$remoteDevice]), '', 'Remote enable still persists locally');
api_device_disable_devices([$remoteDevice]);

foreach (['add', 'change', 'remove'] as $operation) {
	$_SESSION[SESS_MESSAGES] = [];

	if ($operation === 'add') {
		api_device_dq_add($remoteDevice, $interfaceQuery, '1');
	} elseif ($operation === 'change') {
		api_device_dq_change($remoteDevice, $interfaceQuery, '2');
	} else {
		api_device_dq_remove($remoteDevice, $interfaceQuery);
	}
	library_expect($_SESSION[SESS_MESSAGES]['poller_down_' . $offlinePoller]['level'] ?? null, MESSAGE_LEVEL_WARN, 'Query mutation reports unavailable collector');
	$mapping = db_fetch_row_prepared('SELECT reindex_method FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$remoteDevice, $interfaceQuery]);
	library_expect(count($mapping), $operation === 'remove' ? 0 : 1, 'Query mutation persists locally');

	if ($operation !== 'remove') {
		library_expect((int) $mapping['reindex_method'], $operation === 'add' ? 1 : 2, 'Remote query keeps the selected reindex method');
	}
}
library_expect(db_execute_prepared('INSERT INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', [$remoteDevice, $templateId]), true, 'Remote graph association exists');
$_SESSION[SESS_MESSAGES] = [];
api_device_gt_remove($remoteDevice, $templateId);
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$remoteDevice]), 0, 'Remote graph association is removed locally');
library_expect($_SESSION[SESS_MESSAGES]['poller_down_' . $offlinePoller]['level'] ?? null, MESSAGE_LEVEL_WARN, 'Graph association warns about incomplete propagation');
api_device_remove($remoteDevice);
library_expect(db_fetch_cell_prepared('SELECT deleted FROM host WHERE id = ?', [$remoteDevice]), 'on', 'Remote deletion leaves a tombstone until synchronization');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_command WHERE poller_id = ? AND action = ? AND command = ?', [$offlinePoller, POLLER_COMMAND_PURGE, (string) $remoteDevice]), 1, 'Remote deletion queues its purge');
api_device_purge_from_remote($remoteDevice, $offlinePoller);
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_command WHERE poller_id = ? AND action = ? AND command = ?', [$offlinePoller, POLLER_COMMAND_PURGE, (string) $remoteDevice]), 1, 'Retry does not duplicate the queued purge');
db_execute_prepared('DELETE FROM host WHERE id = ?', [$remoteDevice]);
db_execute_prepared('DELETE FROM poller_command WHERE poller_id = ?', [$offlinePoller]);
db_execute_prepared('DELETE FROM poller WHERE id = ?', [$offlinePoller]);

// Bulk removal must preserve unselected devices and honor the graph-retention choice.
foreach ([1, 2] as $deleteType) {
	$batchDevices = [];
	$batchGraphs  = [];
	$batchData    = [];

	foreach (['first', 'second'] as $suffix) {
		$batchDevice = library_save_device(['description' => 'Bulk removal ' . $suffix, 'snmp_version' => 0]);
		library_expect($batchDevice > 0, true, 'A selected bulk device exists');
		$batchDevices[] = $batchDevice;
		$batchCreated   = create_complete_graph_from_template($templateId, $batchDevice, [], $suggested);
		library_expect(is_array($batchCreated) && $batchCreated['local_graph_id'] > 0, true, 'A selected device has a real graph');
		$batchGraphs[] = (int) $batchCreated['local_graph_id'];
		$batchData     = array_merge($batchData, array_map('intval', array_values($batchCreated['local_data_id'])));
		library_expect(db_execute_prepared('INSERT INTO host_value_cache (host_id, dimension, value) VALUES (?, ?, ?)', [$batchDevice, 'fixture', 'selected']), true, 'Selected cache row exists');
	}
	library_expect(count($batchData) > 0, true, 'Selected graphs have data sources');
	$unselected = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device]);
	api_device_remove_multi($batchDevices, $deleteType);

	foreach ($batchDevices as $batchDevice) {
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$batchDevice]), 0, 'Bulk removal deletes selected device');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_value_cache WHERE host_id = ?', [$batchDevice]), 0, 'Bulk removal deletes selected cache');
	}
	library_expect(db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device]), $unselected, 'Bulk removal leaves the unselected device unchanged');
	library_expect((int) db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$graphId]), $device, 'Bulk removal preserves the unselected graph');

	foreach ($batchGraphs as $batchGraph) {
		$remaining = db_fetch_row_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$batchGraph]);
		library_expect(count($remaining), $deleteType === 1 ? 1 : 0, 'Bulk removal honors graph retention');

		if ($deleteType === 1) {
			library_expect((int) $remaining['host_id'], 0, 'Retained graph is detached from the deleted device');
		}
	}

	foreach ($batchData as $batchDataId) {
		$remaining = db_fetch_row_prepared('SELECT dl.host_id, dtd.active FROM data_local AS dl INNER JOIN data_template_data AS dtd ON dtd.local_data_id = dl.id WHERE dl.id = ?', [$batchDataId]);
		library_expect(count($remaining), $deleteType === 1 ? 2 : 0, 'Bulk removal honors data-source retention');

		if ($deleteType === 1) {
			library_expect((int) $remaining['host_id'], 0, 'Retained data source is detached');
			library_expect($remaining['active'], '', 'Retained data source stops polling');
		}
	}

	if ($deleteType === 1) {
		api_delete_graphs($batchGraphs, 2);
	}
}

// Fail actual SQL statements, after earlier cleanup has already run. The retry
// must retain the device and its dependencies until the whole operation succeeds.
foreach ([false, true] as $bulk) {
	foreach (['host_value_cache', 'host'] as $failureTable) {
		$failureDevice = library_save_device(['description' => 'Rollback fixture', 'snmp_version' => 0]);
		library_expect($failureDevice > 0, true, 'Rollback device exists');
		library_expect(db_execute_prepared('INSERT INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', [$failureDevice, $templateId]), true, 'Early cleanup dependency exists');
		library_expect(db_execute_prepared('INSERT INTO host_value_cache (host_id, dimension, value) VALUES (?, ?, ?)', [$failureDevice, 'rollback', 'preserve']), true, 'Later cleanup dependency exists');
		$idColumn = $failureTable === 'host' ? 'id' : 'host_id';
		library_expect(db_execute("CREATE TRIGGER library_delete_failure BEFORE DELETE ON $failureTable FOR EACH ROW BEGIN IF OLD.$idColumn = $failureDevice THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'library cleanup failure'; END IF; END"), true, 'Database failure injection is installed');
		$_SESSION['sess_messages'] = [];

		try {
			if ($bulk) {
				api_device_remove_multi([$failureDevice]);
			} else {
				api_device_remove($failureDevice);
			}
			$messageId = $bulk ? 'device_remove_multi_failed' : 'device_remove_failed_' . $failureDevice;
			library_expect($_SESSION[SESS_MESSAGES][$messageId]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Failed deletion reports an error');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$failureDevice]), 1, 'Failed deletion preserves the device');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$failureDevice]), 1, 'Failed deletion rolls back earlier cleanup');
			library_expect(db_fetch_cell_prepared('SELECT value FROM host_value_cache WHERE host_id = ?', [$failureDevice]), 'preserve', 'Failed deletion preserves later dependencies');
		} finally {
			library_expect(db_execute('DROP TRIGGER library_delete_failure'), true, 'Only the fixture trigger is removed');
		}
		$_SESSION['sess_messages'] = [];

		if ($bulk) {
			api_device_remove_multi([$failureDevice]);
		} else {
			api_device_remove($failureDevice);
		}
		library_expect(array_filter($_SESSION[SESS_MESSAGES], static fn (array $message): bool => $message['level'] === MESSAGE_LEVEL_ERROR), [], 'Deletion retry reports no error');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$failureDevice]), 0, 'Retry deletes the preserved device');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$failureDevice]), 0, 'Retry cleans up early dependency');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_value_cache WHERE host_id = ?', [$failureDevice]), 0, 'Retry cleans up later dependency');
	}
}

api_device_remove($device);

foreach ($ownedRrdFiles as $rrdPath) {
	if (is_file($rrdPath)) {
		library_expect(unlink($rrdPath), true, 'Fixture removes its own RRD file');
	}
}
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device]), 0, 'Device removal persists');
library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM host'), $before, 'Device lifecycle leaves unrelated devices intact');
library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_local WHERE id = ?', [$graphId]), 0, 'Device removal cleans up its graph');

date_default_timezone_set('UTC');
$start = strtotime('2031-01-20 12:30:00 UTC');

foreach ([
	[REPORTS_SCHED_INTVL_MINUTE, 5, '2031-01-20 12:35:00'],
	[REPORTS_SCHED_INTVL_HOUR, 2, '2031-01-20 14:30:00'],
	[REPORTS_SCHED_INTVL_DAY, 3, '2031-01-23 12:30:00'],
	[REPORTS_SCHED_INTVL_WEEK, 2, '2031-02-03 12:30:00'],
	[REPORTS_SCHED_INTVL_MONTH_DAY, 1, '2031-02-20 12:30:00'],
	[REPORTS_SCHED_INTVL_MONTH_WEEKDAY, 1, '2031-02-17 12:30:00'],
	[REPORTS_SCHED_INTVL_YEAR, 1, '2032-01-20 12:30:00'],
] as [$interval, $count, $expected]) {
	library_expect(reports_interval_start($interval, $count, 0, $start), strtotime($expected . ' UTC'), 'Report interval ' . $interval);
}

require __DIR__ . '/LibraryAuthentication.php';
library_authentication();
require __DIR__ . '/LibraryPlugins.php';
library_plugins();
library_database();

$complete                                        = true;
$GLOBALS['cacti_fixture_contract']['complete']   = true;
$GLOBALS['cacti_fixture_contract']['assertions'] = $assertions;
print "PASS: library behavior ($assertions assertions)\n";
