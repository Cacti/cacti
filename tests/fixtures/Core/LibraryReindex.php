<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise native rediscovery of a renumbered interface and its data-source identity. */
function library_reindex(int $device, int $query): void {
	$mapping = db_fetch_row_prepared('SELECT id, graph_template_id FROM snmp_query_graph WHERE snmp_query_id = ? ORDER BY id LIMIT 1', [$query]);
	library_expect(!empty($mapping), true, 'Installed interface query has a graph mapping');
	library_query_validation($device, $query, $mapping);
	$index = db_fetch_cell_prepared('SELECT snmp_index FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? AND field_value = ?', [$device, $query, 'ifDescr', 'lo']);
	library_expect(ctype_digit($index), true, 'Reindex fixture selects the agent loopback interface');
	$values = ['sg' => [$query => []]];
	create_save_graph($device, 'sg', $query, [(int) $mapping['id'] => [encode_data_query_index($index) => true]], $values);
	$graphs = db_fetch_assoc_prepared('SELECT id FROM graph_local WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]);
	library_expect(count($graphs), 1, 'Graph creation form produces one interface graph');
	$sources = db_fetch_assoc_prepared('SELECT dl.id AS local_data_id, dl.host_id, dl.snmp_query_id, dl.snmp_index, dl.data_template_id, dtd.id AS data_template_data_id FROM data_local dl JOIN data_template_data dtd ON dtd.local_data_id = dl.id WHERE dl.host_id = ? AND dl.snmp_query_id = ?', [$device, $query]);
	library_expect(count($sources) > 0, true, 'Interface graph owns actual query data sources');
	library_rrd_bounds($device, $query, $sources);
	$fieldIds = array_column(db_fetch_assoc_prepared('SELECT dif.type_code, dif.id FROM data_input_fields dif JOIN snmp_query sq ON sq.data_input_id = dif.data_input_id WHERE sq.id = ? AND dif.type_code IN (?, ?, ?)', [$query, 'index_type', 'index_value', 'output_type']), 'id', 'type_code');
	$sort     = get_best_data_query_index_type($device, $query);

	foreach ($sources as $source) {
		$input = $source + ['snmp_index_on' => $sort, 'snmp_query_graph_id' => (int) $mapping['id']];
		update_snmp_index_order($input);
		$identity = db_fetch_cell_prepared('SELECT field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND field_name = ? AND snmp_index = ?', [$device, $query, $sort, $index]);

		foreach (['index_type' => $sort, 'index_value' => $identity, 'output_type' => (string) $mapping['id']] as $type => $expected) {
			library_expect(db_fetch_cell_prepared('SELECT value FROM data_input_data WHERE data_template_data_id = ? AND data_input_field_id = ?', [$source['data_template_data_id'], $fieldIds[$type]]), $expected, 'Query creation preserves ' . $type . ' independently of its numerical interface index');
		}
		library_suggested_column($source, (int) $mapping['id']);
	}
	library_expect(db_execute_prepared('UPDATE data_local SET snmp_index = ?, orphan = 1 WHERE host_id = ? AND snmp_query_id = ?', ['999999', $device, $query]), true, 'Fixture records the interface prior to renumbering');
	db_execute_prepared('UPDATE graph_local SET snmp_index = ? WHERE host_id = ? AND snmp_query_id = ?', ['999999', $device, $query]);
	library_expect(run_data_query($device, $query), true, 'Native rediscovery remaps the renamed interface index');

	foreach ($sources as $source) {
		$row = db_fetch_row_prepared('SELECT snmp_index, orphan FROM data_local WHERE id = ?', [$source['local_data_id']]);
		library_expect($row, ['snmp_index' => $index, 'orphan' => 0], 'Rediscovery preserves data-source identity and clears orphan state');
		library_expect(db_execute_prepared('UPDATE data_local SET orphan = 1 WHERE id = ?', [$source['local_data_id']]), true, 'Returning interface starts from its previously orphaned state');
		library_expect(run_data_query($device, $query), true, 'Returning interface can recover without recreating its data source');
		library_expect((int) db_fetch_cell_prepared('SELECT orphan FROM data_local WHERE id = ?', [$source['local_data_id']]), 0, 'Recovered interface leaves orphan state');
	}

	foreach ($graphs as $graph) {
		library_expect(db_fetch_cell_prepared('SELECT snmp_index FROM graph_local WHERE id = ?', [$graph['id']]), $index, 'Graph follows the same recovered interface identity');
		library_query_aggregate((int) $graph['id'], (int) $mapping['graph_template_id']);
	}
	library_reindex_removal($device, $query, $sources[0], (int) $fieldIds['index_value'], $index);
	library_orphan_removal();
}

/** Report a vanished interface as removed unless its query declares indexes transient, then refresh the caches. */
function library_reindex_removal(int $device, int $query, array $source, int $valueField, string $index): void {
	global $data_query_xml_arrays;
	$identity  = db_fetch_cell_prepared('SELECT value FROM data_input_data WHERE data_template_data_id = ? AND data_input_field_id = ?', [$source['data_template_data_id'], $valueField]);
	$xmlPath   = db_fetch_cell_prepared('SELECT xml_path FROM snmp_query WHERE id = ?', [$query]);
	$transient = tempnam(CACTI_PATH_RESOURCE . '/snmp_queries', 'library-transient-');
	library_expect(is_string($transient), true, 'Transient query fixture owns its XML file');
	$orphan = static fn (): int => (int) db_fetch_cell_prepared('SELECT orphan FROM data_local WHERE id = ?', [$source['local_data_id']]);
	// a reindex without index changes clears the orphan flag it just set, so the operator log is the durable signal
	$removal = static fn (bool $transient): int => count(array_filter($_SESSION['debug_log']['data_query'] ?? [], static fn (string $line): bool => str_contains($line, 'Index Removal Detected! PreviousIndex: vanished-interface') && str_contains($line, 'Transient') === $transient));

	try {
		$xml = preg_replace('/<\/name>/', '</name><index_transient>true</index_transient>', file_get_contents(CACTI_PATH_RESOURCE . '/snmp_queries/interface.xml'), 1);
		library_expect(file_put_contents($transient, $xml), strlen($xml), 'Transient interface XML is written');
		library_expect(db_execute_prepared('UPDATE data_input_data SET value = ? WHERE data_template_data_id = ? AND data_input_field_id = ?', ['vanished-interface', $source['data_template_data_id'], $valueField]), true, 'Fixture records an interface the agent no longer reports');
		db_execute_prepared('UPDATE snmp_query SET xml_path = ? WHERE id = ?', [$transient, $query]);
		unset($data_query_xml_arrays[$query]);
		debug_log_clear('data_query');
		library_expect(run_data_query($device, $query), true, 'A transient query reindexes without the vanished interface');
		library_expect([$removal(true), $removal(false)], [1, 0], 'A transient index removal is reported as expected');
		library_expect($orphan(), 0, 'A transient index removal leaves the data source in service');
		db_execute_prepared('UPDATE snmp_query SET xml_path = ? WHERE id = ?', [$xmlPath, $query]);
		unset($data_query_xml_arrays[$query]);
		debug_log_clear('data_query');
		library_expect(run_data_query($device, $query), true, 'A persistent query reindexes without the vanished interface');
		library_expect([$removal(true), $removal(false)], [0, 1], 'A persistent index removal is reported to the operator');
	} finally {
		db_execute_prepared('UPDATE snmp_query SET xml_path = ? WHERE id = ?', [$xmlPath, $query]);
		unset($data_query_xml_arrays[$query]);
		db_execute_prepared('UPDATE data_input_data SET value = ? WHERE data_template_data_id = ? AND data_input_field_id = ?', [$identity, $source['data_template_data_id'], $valueField]);
		unlink($transient);
	}
	library_expect(run_data_query($device, $query), true, 'The returning interface reindexes');
	library_expect($orphan(), 0, 'The returning interface leaves orphan state');
	update_data_query_cache($device, $query);
	library_expect(db_fetch_cell_prepared('SELECT snmp_index FROM data_local WHERE id = ?', [$source['local_data_id']]), $index, 'Refreshing the query cache keeps the data-source index');
	library_expect(array_key_exists($index, get_formatted_data_query_indexes($device, $query)), true, 'Formatted query indexes include the graphed interface');
}

/** Remove orphaned poller items held by an unreachable collector and warn that a full sync is needed. */
function library_orphan_removal(): void {
	$poller = (int) sql_save(['id' => 0, 'name' => 'Orphan removal collector', 'hostname' => 'orphan-collector', 'dbhost' => '127.0.0.1', 'disabled' => '', 'last_status' => date('Y-m-d H:i:s')], 'poller');
	library_expect($poller > 1, true, 'Orphan fixture owns its collector');
	$orphan = (int) db_fetch_cell('SELECT COALESCE(MAX(id), 0) + 1000 FROM data_local');

	try {
		foreach (['connection refused' => date('Y-m-d H:i:s'), 'heartbeat lost' => '2000-01-01 00:00:00'] as $reason => $heartbeat) {
			library_expect(db_execute_prepared('UPDATE poller SET last_status = ? WHERE id = ?', [$heartbeat, $poller]), true, 'Collector heartbeat reflects ' . $reason);
			library_expect(db_execute_prepared('INSERT INTO poller_item (local_data_id, poller_id, rrd_name) VALUES (?, ?, ?)', [$orphan, $poller, 'orphan']), true, 'Collector holds an orphaned item');
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			data_query_remove_disabled_items([$orphan]);
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE local_data_id = ?', [$orphan]), 0, 'Orphan removal clears the primary copy when the collector has ' . $reason);
			library_expect($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]['level'] ?? null, MESSAGE_LEVEL_WARN, 'Orphan removal asks for a full sync when the collector has ' . $reason);
		}
	} finally {
		unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
		db_execute_prepared('DELETE FROM poller_item WHERE local_data_id = ?', [$orphan]);
		db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
	}
}
