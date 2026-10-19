<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

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
}
