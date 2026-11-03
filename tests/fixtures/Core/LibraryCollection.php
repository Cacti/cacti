<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_collection_queries(int $snmpDevice, int $interfaceQuery): void {
	global $data_query_xml_arrays;
	library_snmp_multi();
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

function library_snmp_multi(): void {
	$values = ['.1.3.6.1.2.1.1.6.0' => 'Cacti coverage fixture', '.1.3.6.1.2.1.1.4.0' => 'cacti@example.invalid'];

	foreach ([1, 2, 3] as $version) {
		foreach ([1, 2] as $batch) {
			$actual = cacti_snmp_get_multi('snmp-fixture', 'public', array_keys($values), $version,
				auth_user: 'user', max_oids: $batch);
			library_expect($actual, $values, 'Native SNMP v' . $version . ' preserves OID association across batch sizes');
		}
		$oid = array_key_first($values);
		library_expect(cacti_snmp_get_multi('snmp-fixture', 'public', $oid, $version, auth_user: 'user'), [$oid => $values[$oid]], 'Scalar OID uses the same result envelope');
		$hex = cacti_snmp_get_multi('snmp-fixture', 'public', array_keys($values), $version,
			auth_user: 'user', value_output_format: SNMP_STRING_OUTPUT_HEX);

		foreach ($values as $oid => $value) {
			library_expect(strtolower(str_replace(' ', '', $hex[$oid])), bin2hex($value), 'Binary SNMP fallback preserves every octet and requested OID');
		}
	}
	library_expect(cacti_snmp_get_multi('snmp-fixture', 'public', [], 2), [], 'Empty request has no fabricated samples');
	library_expect(cacti_snmp_get_multi('127.0.0.1', 'public', array_keys($values), 2, port: 9, timeout_ms: 10), array_fill_keys(array_keys($values), 'U'), 'A failed batch marks each requested sample unknown');
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
			['walk', 'OIDVALUE/REGEXP:.*\.([0-9]+)$:interface-\1', '', '', 'interface-' . $loopback],
			['walk', 'value', '<rewrite_index>|index|</rewrite_index>', '', 'lo'],
			['get', 'VALUE/REGEXP:^(.).*$', '<rewrite_index>|index|</rewrite_index>', '', 'l'],
			['get', 'OID/REGEXP:.*\\.([0-9]+)$', '<rewrite_index>|index|</rewrite_index>', '', $loopback],
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
			api_device_dq_add($device, $query, (string) DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE);
			library_expect((int) db_fetch_cell_prepared('SELECT action FROM poller_reindex WHERE host_id = ? AND data_query_id = ?', [$device, $query]), POLLER_ACTION_SNMP_COUNT, 'A query without a count OID uses native SNMP index counting');
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

/** Exercise collector HTTP transport without leaving the disposable network. */
function library_remote_transport(): void {
	$directory = sys_get_temp_dir() . '/cacti-collector-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Collector fixture owns its temporary directory');
	$server   = null;
	$poller   = 0;
	$options  = ['force_https', 'remote_agent_port', 'remote_agent_timeout', 'remote_agent_ca_file'];
	$previous = [];

	foreach ($options as $option) {
		$previous[$option] = read_config_option($option);
	}

	try {
		$hostname = gethostname();
		$address  = gethostbyname($hostname);
		library_expect(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && $address !== '127.0.0.1', true, 'Collector fixture uses its isolated container address');
		$socket = stream_socket_server('tcp://' . $address . ':0', $number, $message);
		library_expect(is_resource($socket), true, 'Collector fixture reserves an isolated port');
		$listen = stream_socket_get_name($socket, false);
		fclose($socket);
		$port   = (int) substr($listen, strrpos($listen, ':') + 1);
		$router = <<<'PHP'
<?php
file_put_contents(__DIR__ . '/requests.log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
if ($_SERVER['REQUEST_URI'] === '/redirect') {
    header('Location: /forbidden', true, 302);
} elseif ($_SERVER['REQUEST_URI'] === '/unavailable') {
    http_response_code(503);
    echo 'fixture unavailable';
} else {
    header('Content-Type: application/json');
    echo json_encode(['host' => $_SERVER['HTTP_HOST'], 'path' => $_SERVER['REQUEST_URI']]);
}
PHP;
		library_expect(file_put_contents($directory . '/router.php', $router), strlen($router), 'Collector router is written completely');
		$server = proc_open([PHP_BINARY, '-S', $listen, $directory . '/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $directory);
		library_expect(is_resource($server), true, 'Collector fixture starts its HTTP server');
		fclose($pipes[0]);
		$ready = false;

		for ($attempt = 0; $attempt < 50; $attempt++) {
			$connection = @stream_socket_client('tcp://' . $listen, $number, $message, 0.1);

			if (is_resource($connection)) {
				fclose($connection);
				$ready = true;

				break;
			}
			usleep(100000);
		}
		library_expect($ready, true, 'Collector HTTP server becomes ready');
		$poller = (int) sql_save(['id' => 0, 'name' => 'Transport fixture', 'hostname' => $hostname, 'disabled' => 'on'], 'poller');
		library_expect($poller > 1, true, 'Collector fixture owns its poller record');
		set_config_option('force_https', '');
		set_config_option('remote_agent_port', (string) $port);
		set_config_option('remote_agent_timeout', '2');
		set_config_option('remote_agent_ca_file', '');
		$sentinel = static fn (): bool => false;
		set_error_handler($sentinel);

		try {
			$response = call_remote_data_collector($poller, '/ok?fixture=1');
			library_expect(json_decode($response, true, 512, JSON_THROW_ON_ERROR), ['host' => $hostname . ':' . $port, 'path' => '/ok?fixture=1'], 'Pinned collector request retains the original Host header and exact path');

			foreach (['/redirect', '/unavailable'] as $path) {
				library_expect(call_remote_data_collector($poller, $path), false, 'Non-success collector response cannot report success');
			}
			$current = set_error_handler($sentinel);
			restore_error_handler();
			library_expect($current, $sentinel, 'Collector transport restores the caller error handler');
		} finally {
			restore_error_handler();
		}
		$requests = file($directory . '/requests.log', FILE_IGNORE_NEW_LINES);
		library_expect($requests, ['/ok?fixture=1', '/redirect', '/unavailable'], 'Collector transport never follows a redirect');

		foreach (['relative', "/ok\r\nInjected: fixture", "/ok\0"] as $path) {
			library_expect(call_remote_data_collector($poller, $path), false, 'Malformed collector path is rejected before I/O');
		}

		foreach (['0', '65536', '1.5'] as $invalidPort) {
			set_config_option('remote_agent_port', $invalidPort);
			library_expect(call_remote_data_collector($poller, '/ok'), false, 'Invalid collector port is rejected before I/O');
		}
		set_config_option('remote_agent_port', (string) $port);

		foreach (['', 'bad/host', '127.0.0.1', '169.254.169.254', '[::1]'] as $invalidHost) {
			db_execute_prepared('UPDATE poller SET hostname = ? WHERE id = ?', [$invalidHost, $poller]);
			library_expect(call_remote_data_collector($poller, '/ok'), false, 'Missing, malformed, or reserved collector host is rejected');
		}
		db_execute_prepared('UPDATE poller SET hostname = ? WHERE id = ?', [$hostname, $poller]);
		set_config_option('force_https', 'on');
		set_config_option('remote_agent_ca_file', $directory . '/missing-ca.pem');
		library_expect(call_remote_data_collector($poller, '/ok'), false, 'Unreadable configured CA fails before the TLS request');
		library_expect(file($directory . '/requests.log', FILE_IGNORE_NEW_LINES), $requests, 'Rejected collector configuration produces no network request');
	} finally {
		if (is_resource($server)) {
			proc_terminate($server);
			proc_close($server);
		}

		if ($poller > 0) {
			db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
		}

		foreach ($previous as $option => $value) {
			set_config_option($option, $value);
		}

		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
}

/** Only unique, complete input fields may identify a reindexed object. */
function library_query_indexes(int $device, int $interfaceQuery): void {
	global $data_query_xml_arrays;
	$path = tempnam(CACTI_PATH_RESOURCE . '/snmp_queries', 'coverage-index-');
	library_expect(is_string($path), true, 'Index fixture owns its XML file');
	$query = 0;

	try {
		$input = (int) db_fetch_cell_prepared('SELECT data_input_id FROM snmp_query WHERE id = ?', [$interfaceQuery]);
		$query = (int) sql_save(['id' => 0, 'name' => 'Index identity fixture', 'xml_path' => $path, 'data_input_id' => $input], 'snmp_query');
		library_expect($query > 0, true, 'Index fixture owns its query');
		$fields = '<fields>';

		foreach (['index', 'label', 'duplicate', 'partial', 'ignored', 'value'] as $name) {
			$fields .= '<' . $name . '><name>' . $name . '</name><direction>' . ($name === 'value' ? 'output' : 'input') . '</direction><oid>.1.2.3' . ($name === 'index' ? '' : '.4') . '</oid></' . $name . '>';
		}
		$fields .= '</fields>';

		foreach (['index' => ['1', '2'], 'label' => ['alpha', 'beta'], 'duplicate' => ['same', 'same'], 'partial' => ['one'], 'ignored' => ['a', 'b']] as $field => $values) {
			foreach ($values as $offset => $value) {
				library_expect(db_execute_prepared('INSERT INTO host_snmp_cache (host_id, snmp_query_id, field_name, field_value, snmp_index, oid) VALUES (?, ?, ?, ?, ?, ?)', [$device, $query, $field, $value, (string) ($offset + 1), '.1.2.3.' . ($offset + 1)]), true, 'Index fixture stores its observed field');
			}
		}
		$snapshot = db_fetch_assoc_prepared('SELECT * FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? ORDER BY field_name, snmp_index', [$device, $query]);

		foreach ([
			['<index_order>label:duplicate:partial:index</index_order><index_order_type>alphanumeric</index_order_type>', ['label', 'index']],
			['<index_order>label:duplicate:partial:index</index_order><index_order_type>unknown-fixture</index_order_type>', ['label', 'index']],
			['<index_order>label</index_order><index_order_type>alphanumeric</index_order_type>', ['label']],
			['', ['index', 'label', 'ignored']],
		] as [$order, $expected]) {
			$xml = '<query><name>Index fixture</name><oid_index>.1.2.3</oid_index>' . $order . $fields . '</query>';
			library_expect(file_put_contents($path, $xml), strlen($xml), 'Index selection uses actual query XML');
			unset($data_query_xml_arrays[$query]);
			library_expect(get_ordered_index_type_list($device, $query), $expected, 'Index selection excludes duplicates, missing values, output-only fields, and unrequested fields');
			library_expect(db_fetch_assoc_prepared('SELECT * FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? ORDER BY field_name, snmp_index', [$device, $query]), $snapshot, 'Index selection preserves observed query data');
		}
		$xml = '<query><name>Invalid output index</name><oid_index>.1.2.3</oid_index><index_order>value</index_order><index_order_type>alphanumeric</index_order_type>' . $fields . '</query>';
		file_put_contents($path, $xml);
		unset($data_query_xml_arrays[$query]);
		library_expect(query_snmp_host($device, $query), false, 'An output-only index is rejected before SNMP collection');
		library_expect(db_fetch_assoc_prepared('SELECT * FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? ORDER BY field_name, snmp_index', [$device, $query]), $snapshot, 'Rejected query preserves its previously observed fields');
	} finally {
		if ($query > 0) {
			db_execute_prepared('DELETE FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]);
			db_execute_prepared('DELETE FROM snmp_query WHERE id = ?', [$query]);
			unset($data_query_xml_arrays[$query]);
		}
		unlink($path);
	}
}
