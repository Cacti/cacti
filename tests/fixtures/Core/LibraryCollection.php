<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_collection_queries(int $snmpDevice, int $interfaceQuery): void {
	global $data_query_xml_arrays;
	library_snmp_query_formats($snmpDevice, $interfaceQuery);

	library_expect(db_execute_prepared('UPDATE host SET bulk_walk_size = 0 WHERE id = ?', [$snmpDevice]), true, 'SNMP auto-sizing with persistence is enabled');
	library_expect(run_data_query($snmpDevice, $interfaceQuery), true, 'Native SNMP auto-sizing discovers interfaces');
	library_expect((int) db_fetch_cell_prepared('SELECT bulk_walk_size FROM host WHERE id = ?', [$snmpDevice]) > 0, true, 'SNMP auto-sizing stores a usable walk size');
	library_expect(db_execute_prepared('UPDATE host SET bulk_walk_size = -1 WHERE id = ?', [$snmpDevice]), true, 'Per-query SNMP auto-sizing is enabled');
	library_expect(run_data_query($snmpDevice, $interfaceQuery), true, 'Per-query SNMP auto-sizing discovers interfaces');
	library_expect((int) db_fetch_cell_prepared('SELECT bulk_walk_size FROM host WHERE id = ?', [$snmpDevice]), -1, 'Per-query sizing preserves the configured sentinel');
	api_device_dq_change($snmpDevice, $interfaceQuery, (string) DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION);
	$checks = db_fetch_assoc_prepared('SELECT action, op, assert_value, arg1 FROM poller_reindex WHERE host_id = ? AND data_query_id = ?', [$snmpDevice, $interfaceQuery]);
	library_expect(count($checks) > 0, true, 'SNMP field verification produces checks');

	foreach ($checks as $check) {
		library_expect((int) $check['action'], POLLER_ACTION_SNMP, 'SNMP field verification polls an OID');
		library_expect($check['op'], '=', 'SNMP field verification compares equality');
		library_expect(str_starts_with($check['arg1'], '.'), true, 'SNMP field verification keeps its OID');
	}

	$scriptDevice = library_save_device(['description' => 'Script query fixture', 'snmp_version' => 0]);
	library_expect($scriptDevice > 0, true, 'Script query device exists');
	library_expect(db_execute_prepared('UPDATE host SET disabled = ?, status = ? WHERE id = ?', ['', HOST_UP, $scriptDevice]), true, 'Script query device is eligible for collection');
	$input = (int) db_fetch_cell_prepared('SELECT id FROM data_input WHERE type_id = ? ORDER BY id LIMIT 1', [DATA_INPUT_TYPE_SCRIPT_QUERY]);
	library_expect($input > 0, true, 'Installed schema provides script-query input');

	foreach (['output_delimiter', 'output_delimeter'] as $delimiter) {
		foreach ([true, false] as $explicitCount) {
			$xmlPath = tempnam(CACTI_PATH_RESOURCE . '/script_queries', 'library-query-');
			library_expect(is_string($xmlPath), true, 'Script XML gets an exclusively owned path');
			$query = 0;

			try {
				foreach (['full', 'shrink'] as $scenario) {
					$countTag = $explicitCount ? '<arg_num_indexes>count</arg_num_indexes>' : '';
					$xml      = '<query><name>Library script query</name>' .
						'<script_path>|path_php_binary| -q |path_cacti|/tests/fixtures/Core/QueryProducer.php</script_path>' .
						'<arg_prepend>' . $scenario . '</arg_prepend><arg_index>index</arg_index>' . $countTag .
						'<arg_query>query</arg_query><arg_get>get</arg_get><' . $delimiter . '>!</' . $delimiter . '>' .
						'<index_order>label:index</index_order><index_order_type>alphanumeric</index_order_type>' .
						'<index_title_format>|chosen_order_field|</index_title_format><fields>' .
						'<index><name>Index</name><direction>input</direction><query_name>index</query_name></index>' .
						'<label><name>Label</name><direction>input</direction><query_name>label</query_name></label>' .
						'<value><name>Value</name><direction>output</direction><query_name>value</query_name></value>' .
						'</fields></query>';
					library_expect(file_put_contents($xmlPath, $xml), strlen($xml), 'Complete script query XML is written');

					if ($query === 0) {
						$query = (int) sql_save(['id' => 0, 'name' => 'Library script query', 'xml_path' => $xmlPath, 'data_input_id' => $input], 'snmp_query');
						library_expect($query > 0, true, 'Script query is registered');
					}
					unset($data_query_xml_arrays[$query]);
					api_device_dq_add($scriptDevice, $query, (string) DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE);
					$rows     = db_fetch_assoc_prepared('SELECT snmp_index, field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? ORDER BY snmp_index', [$scriptDevice, $query, 'label']);
					$expected = $scenario === 'full' ? [['snmp_index' => '1', 'field_value' => 'port alpha'], ['snmp_index' => '2', 'field_value' => 'port beta']] : [['snmp_index' => '2', 'field_value' => 'port beta']];
					library_expect($rows, $expected, 'Script output reaches storage and stale indexes disappear');
					$reindex = db_fetch_assoc_prepared('SELECT action, assert_value, arg1 FROM poller_reindex WHERE host_id = ? AND data_query_id = ?', [$scriptDevice, $query]);
					library_expect(count($reindex), 1, 'Index-count policy has one check');
					library_expect((int) $reindex[0]['action'], $explicitCount ? POLLER_ACTION_SCRIPT : POLLER_ACTION_SCRIPT_COUNT, 'Count policy chooses direct or emulated counting');
					library_expect((int) $reindex[0]['assert_value'], count($expected), 'Count policy tracks the current discovered population');
					library_expect(str_contains($reindex[0]['arg1'], 'QueryProducer.php'), true, 'Poller handoff points to the tested producer');
					api_device_dq_change($scriptDevice, $query, (string) DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION);
					$reindex = db_fetch_assoc_prepared('SELECT action, op, assert_value, arg1 FROM poller_reindex WHERE host_id = ? AND data_query_id = ? ORDER BY assert_value', [$scriptDevice, $query]);
					library_expect(count($reindex), count($expected), 'Field-verification policy has one check per interface');
					library_expect(array_column($reindex, 'assert_value'), array_column($expected, 'field_value'), 'Field verification preserves labels with spaces');

					foreach ($reindex as $check) {
						library_expect((int) $check['action'], POLLER_ACTION_SCRIPT, 'Field verification uses a script command');
						library_expect($check['op'], '=', 'Field verification compares equality');
						library_expect(trim(implode("\n", exec_into_array($check['arg1']))), $check['assert_value'], 'Generated reindex command returns the stored comparison value');
					}
				}
			} finally {
				api_device_dq_remove($scriptDevice, $query);
				db_execute_prepared('DELETE FROM snmp_query WHERE id = ?', [$query]);
				unset($data_query_xml_arrays[$query]);
				unlink($xmlPath);
			}
		}
	}
	api_device_remove($scriptDevice);
}

function library_snmp_query_formats(int $device, int $interfaceQuery): void {
	global $data_query_xml_arrays;
	$loopback = db_fetch_cell_prepared('SELECT snmp_index FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? AND field_value = ?', [$device, $interfaceQuery, 'ifDescr', 'lo']);
	library_expect(ctype_digit($loopback), true, 'The native agent exposes a numeric loopback index');
	$input = (int) db_fetch_cell_prepared('SELECT data_input_id FROM snmp_query WHERE id = ?', [$interfaceQuery]);
	$path  = tempnam(CACTI_PATH_RESOURCE . '/snmp_queries', 'library-format-');
	library_expect(is_string($path), true, 'SNMP format fixture owns its XML file');
	$query = 0;

	try {
		$query = (int) sql_save(['id' => 0, 'name' => 'SNMP format fixture', 'xml_path' => $path, 'data_input_id' => $input], 'snmp_query');
		library_expect($query > 0, true, 'SNMP format query is registered');
		$cases = [
			['get', 'value', '', '', 'lo'],
			['get', 'value', '<output_format>ascii</output_format>', '', 'lo'],
			['get', 'VALUE/REGEXP:^(.).*$', '', '', 'l'],
			['get', 'VALUE/REGEXP:^(.).*$', '<output_format>ascii</output_format>', '', 'l'],
			['walk', 'VALUE/REGEXP:^(.).*$', '<output_format>ascii</output_format>', '', 'l'],
			['walk', 'VALUE/TEST:lo:loopback:other', '', '', 'loopback'],
			['walk', 'VALUE/TABLE:lo:loopback', '', '', 'loopback'],
			['walk', 'OID/REGEXP:.*\.([0-9]+)$', '', '', $loopback],
			['get', 'value', '', '<oid_rewrite_pattern>OID/REGEXP:\.999\.</oid_rewrite_pattern><oid_rewrite_replacement>.2.</oid_rewrite_replacement>', 'lo'],
		];

		foreach ($cases as [$method, $source, $format, $rewrite, $expected]) {
			$xml = '<query><name>SNMP format fixture</name><oid_index>.1.3.6.1.2.1.2.2.1.1</oid_index>' .
				'<value_index_parse>VALUE/REGEXP:^' . $loopback . '$</value_index_parse>' .
				'<oid_index_parse>OID/REGEXP:.*\.([0-9]+)$</oid_index_parse>' .
				'<index_order>index</index_order><index_order_type>numeric</index_order_type>' .
				'<index_title_format>|chosen_order_field|</index_title_format><fields>' .
				'<index><name>Index</name><source>index</source><direction>input</direction></index>' .
				'<label><name>Label</name><method>' . $method . '</method><source>' . $source . '</source>' .
				'<direction>input</direction><oid>.1.3.6.1.2.1.2.2.1.' . ($rewrite === '' ? '2' : '999') . '</oid>' . $format . $rewrite . '</label></fields></query>';
			library_expect(file_put_contents($path, $xml), strlen($xml), 'Complete SNMP XML is written');
			unset($data_query_xml_arrays[$query]);
			api_device_dq_add($device, $query, (string) DATA_QUERY_AUTOINDEX_NONE);
			$rows = db_fetch_assoc_prepared('SELECT snmp_index, field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? ORDER BY snmp_index', [$device, $query, 'label']);
			library_expect($rows, [['snmp_index' => $loopback, 'field_value' => $expected]], 'Native SNMP ' . $method . ' and ' . $source . ' preserve the filtered interface identity and transformed label');
			library_expect(db_fetch_cell_prepared('SELECT field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ?', [$device, $query, 'index']), $loopback, 'Index-source field preserves the selected index');
		}
	} finally {
		api_device_dq_remove($device, $query);
		db_execute_prepared('DELETE FROM snmp_query WHERE id = ?', [$query]);
		unset($data_query_xml_arrays[$query]);
		unlink($path);
	}
}
