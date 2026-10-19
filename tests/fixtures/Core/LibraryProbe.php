<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Validate graph-creation probes against an actual script and installed schema. */
function library_probe(): void {
	global $data_query_xml_arrays, $called_by_script_server;
	$previousCalled = $called_by_script_server ?? null;
	$device         = $input = $template = $definition = $series = $graph = $query = $local = 0;
	$xmlPath        = tempnam(CACTI_PATH_RESOURCE . '/script_queries', 'coverage-probe-');
	library_expect(is_string($xmlPath), true, 'Probe fixture owns its query definition');

	try {
		$device     = library_save_device(['description' => 'Probe fixture', 'snmp_version' => 0]);
		$input      = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Probe fixture', 'type_id' => DATA_INPUT_TYPE_SCRIPT_QUERY], 'data_input');
		$template   = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Probe fixture'], 'data_template');
		$definition = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'data_input_id' => $input, 'active' => 'on', 'rrd_step' => 300, 'name' => 'Probe fixture'], 'data_template_data');
		$series     = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'data_template_id' => $template, 'data_source_name' => 'value'], 'data_template_rrd');
		$graph      = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Probe graph', 'test_source' => 'on'], 'graph_templates');
		library_expect(db_execute_prepared('INSERT INTO graph_templates_item (hash, graph_template_id, task_item_id) VALUES (?, ?, ?)', [bin2hex(random_bytes(16)), $graph, $series]), true, 'Probe graph references its template series');
		$query = (int) sql_save(['id' => 0, 'name' => 'Probe query', 'xml_path' => $xmlPath, 'data_input_id' => $input], 'snmp_query');
		library_expect(min($device, $input, $template, $definition, $series, $graph, $query) > 0, true, 'Probe fixture owns all template identities');
		library_expect(db_execute_prepared('INSERT INTO snmp_query_graph_rrd (snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name) VALUES (0, ?, ?, ?)', [$template, $series, 'value']), true, 'Probe output maps to its real series');

		foreach ([DATA_INPUT_TYPE_SCRIPT_QUERY, DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER] as $type) {
			db_execute_prepared('UPDATE data_input SET type_id = ? WHERE id = ?', [$type, $input]);
			$prefix = $type === DATA_INPUT_TYPE_SCRIPT_QUERY ? '|path_php_binary| -q ' : '';
			$xml    = '<query><name>Probe query</name><script_path>' . $prefix . '|path_cacti|/tests/fixtures/Core/QueryProducer.php</script_path><arg_prepend>full</arg_prepend><arg_get>get</arg_get><fields><value><name>Value</name><direction>output</direction><query_name>value</query_name></value></fields></query>';
			library_expect(file_put_contents($xmlPath, $xml), strlen($xml), 'Probe query is written completely');
			unset($data_query_xml_arrays[$query]);

			foreach (['1' => true, 'missing' => false] as $index => $expected) {
				library_expect(test_data_source($template, $device, $query, (string) $index), $expected, 'Native probe accepts an existing sample and rejects a missing index');
				library_expect(test_data_sources($graph, $device, $query, (string) $index), $expected, 'Graph preflight preserves the actual data-source probe outcome');
			}
		}
		// Reindexing must retain the script-server function and count argument.
		$invalid = '<query><name>Invalid index query</name><script_path>|path_cacti|/tests/fixtures/Core/QueryProducer.php</script_path><index_order>value</index_order><fields><value><direction>output</direction></value></fields></query>';
		file_put_contents($xmlPath, $invalid);
		unset($data_query_xml_arrays[$query]);
		library_expect(query_script_host($device, $query), false, 'An output field cannot define query index identity');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]), 0, 'Invalid index definition cannot populate the device cache');
		$xml = '<query><name>Probe query</name><script_path>|path_cacti|/tests/fixtures/Core/QueryProducer.php</script_path><script_function>probe_function</script_function><arg_prepend>full</arg_prepend><arg_num_indexes>count</arg_num_indexes><fields><value><name>Value</name><direction>output</direction></value></fields></query>';
		file_put_contents($xmlPath, $xml);
		unset($data_query_xml_arrays[$query]);
		db_execute_prepared('INSERT INTO host_snmp_query (host_id, snmp_query_id, reindex_method) VALUES (?, ?, ?)', [$device, $query, DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE]);
		db_execute_prepared('INSERT INTO host_snmp_cache (host_id, snmp_query_id, field_name, field_value, snmp_index) VALUES (?, ?, ?, ?, ?)', [$device, $query, 'index', '1', '1']);
		update_reindex_cache($device, $query);
		$check = db_fetch_row_prepared('SELECT action, assert_value, arg1 FROM poller_reindex WHERE host_id = ? AND data_query_id = ?', [$device, $query]);
		library_expect((int) $check['action'], POLLER_ACTION_SCRIPT_PHP, 'Script-server count uses the PHP poller action');
		library_expect((int) $check['assert_value'], 1, 'Reindex baseline counts actual cached indexes');
		library_expect(str_ends_with($check['arg1'], implode(' ', array_map('cacti_escapeshellarg', ['probe_function', 'full', 'count']))), true, 'Reindex command retains the configured function and count arguments');
		db_execute_prepared('UPDATE graph_templates SET test_source = ? WHERE id = ?', ['', $graph]);
		library_expect(test_data_sources($graph, $device, $query, 'missing'), true, 'A template with probing disabled permits creation without a sample');
		db_execute_prepared('UPDATE data_template_data SET active = ? WHERE id = ?', ['', $definition]);
		library_expect(test_data_source($template, $device, $query, '1'), false, 'An inactive data template does not produce a valid sample');
		$command = '<path_php_binary> <missing_probe> <missing_probe>';
		db_execute_prepared('UPDATE data_input SET type_id = ?, input_string = ? WHERE id = ?', [DATA_INPUT_TYPE_SCRIPT, $command, $input]);
		library_expect(get_full_test_script_path($template, $device), PHP_BINARY . '  ', 'Unresolved template tokens are removed without shifting the executable identity');
		$local = (int) sql_save(['id' => 0, 'host_id' => $device, 'data_template_id' => $template], 'data_local');
		db_execute_prepared('UPDATE data_template_data SET local_data_id = ? WHERE id = ?', [$local, $definition]);
		library_expect(get_full_script_path($local), PHP_BINARY . '  ', 'Stored data-source commands apply the same unresolved-token rule');
		db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE id = ?', ['<path_rra>/../../coverage-escape.rrd', $definition]);
		$path = get_data_source_path($local, true);
		library_expect(data_source_path_within_rra($path), true, 'A stored traversal falls back to a contained RRD path');
		library_expect(str_contains($path, '..'), false, 'Fallback path contains no traversal components');
		$external = tempnam(sys_get_temp_dir(), 'cacti-query-outside-');

		try {
			file_put_contents($external, '<query><name>Outside fixture</name></query>');
			db_execute_prepared('UPDATE snmp_query SET xml_path = ? WHERE id = ?', [$external, $query]);
			unset($data_query_xml_arrays[$query]);
			library_expect(get_data_query_array($query), [], 'Existing XML outside the application root is rejected');
			library_expect(file_get_contents($external), '<query><name>Outside fixture</name></query>', 'Rejected query path preserves the external file');
		} finally {
			unlink($external);
			db_execute_prepared('UPDATE snmp_query SET xml_path = ? WHERE id = ?', [$xmlPath, $query]);
			unset($data_query_xml_arrays[$query]);
		}
	} finally {
		foreach (['graph_templates_item' => 'graph_template_id', 'graph_templates' => 'id'] as $table => $column) {
			db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$graph]);
		}

		foreach (['snmp_query_graph_rrd', 'data_template_rrd', 'data_template_data', 'data_local'] as $table) {
			db_execute_prepared("DELETE FROM $table WHERE data_template_id = ?", [$template]);
		}
		db_execute_prepared('DELETE FROM data_template WHERE id = ?', [$template]);
		db_execute_prepared('DELETE FROM data_input WHERE id = ?', [$input]);
		db_execute_prepared('DELETE FROM snmp_query WHERE id = ?', [$query]);
		unset($data_query_xml_arrays[$query]);

		if ($device) {
			api_device_remove($device);
		}
		unlink($xmlPath);
		$called_by_script_server = $previousCalled;
	}
}
