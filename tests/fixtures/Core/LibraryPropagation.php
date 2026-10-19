<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Template updates preserve per-source overrides and unrelated identities. */
function library_template_propagation(): void {
	$template    = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Propagation fixture'], 'data_template');
	$definitions = $series = [];

	try {
		$parent        = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'name' => 'Template name', 't_name' => 'on', 'rrd_step' => 60, 't_rrd_step' => ''], 'data_template_data');
		$definitions[] = $parent;
		$child         = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'local_data_template_data_id' => $parent, 'name' => 'Local name', 'rrd_step' => 300], 'data_template_data');
		$definitions[] = $child;
		$other         = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'name' => 'Unrelated name', 'rrd_step' => 900], 'data_template_data');
		$definitions[] = $other;
		library_expect(push_out_data_source($parent), true, 'Template metadata can propagate to associated sources');
		library_expect(db_fetch_row_prepared('SELECT name, rrd_step FROM data_template_data WHERE id = ?', [$child]), ['name' => 'Local name', 'rrd_step' => 60], 'Inherited step changes while an allowed name override survives');
		library_expect((int) db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE id = ?', [$other]), 900, 'Template propagation leaves an unrelated source untouched');
		$parentSeries = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'data_source_name' => 'value', 't_data_source_name' => 'on', 'rrd_minimum' => '5', 't_rrd_minimum' => ''], 'data_template_rrd');
		$series[]     = $parentSeries;
		$childSeries  = (int) sql_save(['id' => 0, 'data_template_id' => $template, 'local_data_template_rrd_id' => $parentSeries, 'data_source_name' => 'local_value', 'rrd_minimum' => '0'], 'data_template_rrd');
		$series[]     = $childSeries;
		library_expect(push_out_data_source_item($parentSeries), true, 'Template series constraints can propagate');
		library_expect(db_fetch_row_prepared('SELECT data_source_name, rrd_minimum FROM data_template_rrd WHERE id = ?', [$childSeries]), ['data_source_name' => 'local_value', 'rrd_minimum' => '5'], 'Inherited minimum changes while an allowed series name survives');
	} finally {
		foreach ($series as $id) {
			db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', [$id]);
		}

		foreach ($definitions as $id) {
			db_execute_prepared('DELETE FROM data_template_data WHERE id = ?', [$id]);
		}
		db_execute_prepared('DELETE FROM data_template WHERE id = ?', [$template]);
	}
}

/** Reject incomplete mappings and a sort field that loses interface identities. */
function library_query_validation(int $device, int $query, array $mapping): void {
	$session      = $_SESSION;
	$previousSort = db_fetch_cell_prepared('SELECT sort_field FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]);

	try {
		library_expect(api_data_query_errors((int) $mapping['id'], []), true, 'Incomplete output mappings produce a validation error');
		library_expect(isset($_SESSION['sess_messages']['mapping_error']), true, 'Missing output selection identifies the mapping error');
		$post = [];

		foreach (db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_template_id FROM data_template_rrd dtr JOIN graph_templates_item gti ON gti.task_item_id = dtr.id WHERE dtr.local_data_id = 0 AND gti.graph_template_id = ?', [$mapping['graph_template_id']]) as $source) {
			$post['dsdt_' . $source['data_template_id'] . '_' . $source['id'] . '_check'] = 'on';
		}
		library_expect(count($post) > 0, true, 'Installed graph has actual source mappings');
		library_expect(api_data_query_errors((int) $mapping['id'], $post), false, 'Complete selections pass mapping validation');
		library_expect(api_data_query_errors(0, []), true, 'An absent graph cannot pass mapping validation');
		library_expect(isset($_SESSION['sess_messages']['assign_error']), true, 'Absent graph produces an assignment error');
		library_expect(query_check_suitable('coverage_missing_field', 'ifDescr', $device, $query), false, 'A proposed sort field missing all interfaces is rejected');
		library_expect(db_fetch_cell_prepared('SELECT sort_field FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]), 'ifDescr', 'Rejected sort change restores the prior cached identity field');
		library_expect(query_check_suitable('ifIndex', 'ifDescr', $device, $query), true, 'A complete alternate sort field is accepted');
	} finally {
		db_execute_prepared('UPDATE host_snmp_query SET sort_field = ? WHERE host_id = ? AND snmp_query_id = ?', [$previousSort, $device, $query]);
		$_SESSION = $session;
	}
}

/** Invalid suggested columns preserve valid source values and report the configuration error. */
function library_suggested_column(array $source, int $mapping): void {
	$id = (int) $source['local_data_id'];
	api_reapply_suggested_data_source_data($id);
	$before      = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?', [$id]);
	$series      = db_fetch_assoc_prepared('SELECT * FROM data_template_rrd WHERE local_data_id = ? ORDER BY id', [$id]);
	$log         = tempnam(sys_get_temp_dir(), 'cacti-suggestion-');
	$previousLog = read_config_option('path_cactilog');
	$suggestion  = (int) sql_save(['id' => 0, 'snmp_query_graph_id' => $mapping, 'data_template_id' => $source['data_template_id'], 'sequence' => 9999, 'field_name' => 'coverage_missing_column', 'text' => 'Cannot persist'], 'snmp_query_graph_rrd_sv');

	try {
		library_expect($suggestion > 0, true, 'Malformed suggested column is a persisted template mapping');
		set_config_option('path_cactilog', $log);
		api_reapply_suggested_data_source_data($id);
		library_expect(db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?', [$id]), $before, 'Invalid column cannot alter source metadata');
		library_expect(db_fetch_assoc_prepared('SELECT * FROM data_template_rrd WHERE local_data_id = ? ORDER BY id', [$id]), $series, 'Invalid column cannot alter series metadata');
		library_expect(str_contains(file_get_contents($log), 'Suggested value column error.  Column coverage_missing_column'), true, 'Invalid column supplies an actionable template diagnostic');
	} finally {
		set_config_option('path_cactilog', $previousLog);
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd_sv WHERE id = ?', [$suggestion]);
		unlink($log);
	}
}

/** Query-backed aggregate legends must use the member's cached interface identity. */
function library_query_aggregate(int $graph, int $template): void {
	$items     = db_fetch_assoc_prepared('SELECT id, sequence, text_format FROM graph_templates_item WHERE local_graph_id = ? ORDER BY sequence', [$graph]);
	$defaults  = array_fill_keys(array_column($items, 'sequence'), 0);
	$aggregate = 0;

	try {
		db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', ['Interface |query_ifDescr|', $items[0]['id']]);
		$attributes = ['graph_title' => 'Query aggregate fixture', 'graph_template_id' => $template,
			'graph_type'                => AGGREGATE_GRAPH_TYPE_KEEP, 'total' => AGGREGATE_TOTAL_NONE,
			'total_type'                => (string) AGGREGATE_TOTAL_TYPE_SIMILAR, 'total_prefix' => 'Total',
			'gprint_prefix'             => 'Interface |query_ifDescr|', 'reorder' => AGGREGATE_ORDER_NONE,
			'item_no'                   => count($items), 'color_templates' => [], 'graph_item_types' => $defaults,
			'cdefs'                     => $defaults, 'skipped_items' => [], 'total_items' => []];
		library_expect(aggregate_create_update($aggregate, [$graph], $attributes), true, 'Query-backed member can produce an aggregate');
		$labels = array_column(db_fetch_assoc_prepared('SELECT text_format FROM graph_templates_item WHERE local_graph_id = ?', [$aggregate]), 'text_format');
		library_expect(str_contains(implode(' ', $labels), 'Interface lo'), true, 'Aggregate resolves the loopback identity from the member query cache');
		library_expect(str_contains(implode(' ', $labels), '|query_'), false, 'Aggregate persists no unresolved interface tokens');
	} finally {
		if ($aggregate > 0) {
			api_aggregate_remove_multi([$aggregate]);
		}

		foreach ($items as $item) {
			db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', [$item['text_format'], $item['id']]);
		}
	}
}
