<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Render production graph expressions against the fixture's actual RRD samples. */
function library_graph_options(int $graph, array $options): void {
	global $cdef_operators;
	require_once CACTI_PATH_LIBRARY . '/cdef.php';
	$item = db_fetch_row_prepared('SELECT * FROM graph_templates_item WHERE local_graph_id = ? AND task_item_id > 0 AND graph_type_id BETWEEN ? AND ? ORDER BY sequence LIMIT 1', [$graph, GRAPH_ITEM_TYPE_LINE1, GRAPH_ITEM_TYPE_STACK]);
	library_expect(!empty($item), true, 'Expression fixture uses a graphable stored series');
	$definition = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Native graph expression'], 'cdef');
	library_expect($definition > 0, true, 'Expression fixture owns its definition');
	$add = array_search('+', $cdef_operators, true);
	library_expect($add !== false, true, 'Expression fixture uses the installed addition function');
	$previousGradient = read_config_option('enable_rrdtool_gradient_support');
	$extra            = [];
	$series           = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', [$item['task_item_id']]);

	try {
		db_execute_prepared('UPDATE data_template_rrd SET rrd_maximum = ? WHERE id = ?', ['100', $series['id']]);

		foreach (['CURRENT_DATA_SOURCE', 'CURRENT_DS_MINIMUM_VALUE', 'CURRENT_DS_MAXIMUM_VALUE',
			'CURRENT_GRAPH_MINIMUM_VALUE', 'CURRENT_GRAPH_MAXIMUM_VALUE', 'ALL_DATA_SOURCES_DUPS', 'ALL_DATA_SOURCES_NODUPS',
			'SIMILAR_DATA_SOURCES_DUPS', 'SIMILAR_DATA_SOURCES_NODUPS', 'COUNT_ALL_DS_DUPS', 'COUNT_ALL_DS_NODUPS',
			'COUNT_SIMILAR_DS_DUPS', 'COUNT_SIMILAR_DS_NODUPS'] as $token) {
			db_execute_prepared('DELETE FROM cdef_items WHERE cdef_id = ?', [$definition]);

			foreach ([[4, 'CURRENT_DATA_SOURCE'], [4, $token], [2, (string) $add]] as $position => [$type, $value]) {
				library_expect((int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'cdef_id' => $definition, 'sequence' => $position + 1, 'type' => $type, 'value' => $value], 'cdef_items') > 0, true, 'Expression token persists in the real schema');
			}
			library_expect(get_cdef($definition), 'CURRENT_DATA_SOURCE,' . $token . ',+', 'Stored expression resolves without rewriting its token sequence');
			db_execute_prepared('UPDATE graph_templates_item SET cdef_id = ?, text_format = ? WHERE id = ?', [$definition, 'Expression fixture', $item['id']]);
			ob_start();

			try {
				rrdtool_function_graph($graph, '', $options + ['print_source' => true]);
				$source = html_entity_decode(ob_get_contents(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			} finally {
				ob_end_clean();
			}
			library_expect(str_contains($source, 'CDEF:'), true, 'Graph compiler emits the stored expression');
			library_expect(str_contains($source, $token), false, 'Graph compiler resolves ' . $token . ' before execution');
			$image = rrdtool_function_graph($graph, '', $options);
			library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'RRDtool executes ' . $token . ' against real samples: ' . $source);
		}
		// The trend token names the fitted line's slope and intercept, so only its compiled form is checked.
		db_execute_prepared('DELETE FROM cdef_items WHERE cdef_id = ?', [$definition]);

		foreach ([[4, 'CURRENT_DATA_SOURCE'], [4, 'CURRENT_DS_LSLFUNCTION'], [2, (string) $add]] as $position => [$type, $value]) {
			sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'cdef_id' => $definition, 'sequence' => $position + 1, 'type' => $type, 'value' => $value], 'cdef_items');
		}
		db_execute_prepared('UPDATE graph_templates_item SET cdef_id = ? WHERE id = ?', [$definition, $item['id']]);
		$source = library_graph_source($graph, $options);
		library_expect(preg_match('/VDEF:vdef(\\w+)slope=\\1,LSLSLOPE/', $source) === 1 && preg_match('/VDEF:vdef(\\w+)int=\\1,LSLINT/', $source) === 1, true, 'The trend token defines its slope and intercept: ' . $source);
		library_expect(preg_match('/,POP,COUNT,vdef\\w+slope,\\*,vdef\\w+int,\\+/', $source) === 1 && !str_contains($source, 'CURRENT_DS_LSLFUNCTION'), true, 'The trend token compiles to the fitted line');
		sql_save($item, 'graph_templates_item');

		foreach (['', 'on'] as $gradient) {
			set_config_option('enable_rrdtool_gradient_support', $gradient);
			db_execute_prepared('UPDATE graph_templates_item SET graph_type_id = ?, alpha = ?, text_format = ? WHERE id = ?', [GRAPH_ITEM_TYPE_AREA, '80', 'Gradient fixture', $item['id']]);
			$image = rrdtool_function_graph($graph, '', $options);
			library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Area rendering supports normal and expanded gradient commands');
		}
		// Native two-color areas are used only when Cacti does not emulate the gradient itself.
		set_config_option('enable_rrdtool_gradient_support', '');
		$gradient = db_fetch_row('SELECT id, hex FROM colors ORDER BY id DESC LIMIT 1');
		db_execute_prepared('UPDATE graph_templates_item SET graph_type_id = ?, color2_id = ? WHERE id = ?', [GRAPH_ITEM_TYPE_AREA, $gradient['id'], $item['id']]);
		library_expect(str_contains(library_graph_source($graph, $options), ':gradheight='), false, 'RRDtool 1.7 graphs never receive a second gradient color');
		$source = \CactiLibraryRrdtool18Fixture\graph_source($graph, $options);
		library_expect(str_contains($source, '#' . $gradient['hex']) && str_contains($source, ':gradheight='), true, 'RRDtool 1.8 graphs shade an area toward its second color: ' . $source);
		sql_save($item, 'graph_templates_item');

		foreach ([GRAPH_ITEM_TYPE_STACK => 'AREA:', GRAPH_ITEM_TYPE_LINE1 => 'LINE1:', GRAPH_ITEM_TYPE_LINESTACK => 'LINE[0-9.]+:'] as $type => $command) {
			db_execute_prepared('UPDATE graph_templates_item SET graph_type_id = ?, text_format = ? WHERE id = ?', [$type, 'Typed legend ' . $type, $item['id']]);
			$source = library_graph_source($graph, $options);
			library_expect(preg_match('/' . $command . '[^:]+:[\'"]Typed legend ' . $type . '/', $source), 1, 'Graph item type ' . $type . ' carries its legend: ' . $source);
		}
		sql_save($item, 'graph_templates_item');
		$vdef = (int) db_fetch_cell("SELECT id FROM vdef WHERE name = 'Maximum'");
		db_execute_prepared('UPDATE graph_templates_item SET cdef_id = ?, vdef_id = ? WHERE id = ?', [$definition, $vdef, $item['id']]);
		library_expect(preg_match('/VDEF:vdef\\w+=[\'"]?cdef\\w+,MAXIMUM/', library_graph_source($graph, $options)), 1, 'A calculated VDEF reads the CDEF series');
		sql_save($item, 'graph_templates_item');
		$realtime = rrdtool_function_graph($graph, '', $options + ['export_realtime' => true, 'print_source' => true]);
		library_expect(preg_match('/RRD file does not exist: .*\\/user_[0-9a-f]{64}_[0-9]+\\.rrd/', (string) $realtime), 1, 'Realtime graphs read only the per-session cache file: ' . var_export($realtime, true));
		db_execute_prepared('DELETE FROM cdef_items WHERE cdef_id = ?', [$definition]);
		sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'cdef_id' => $definition, 'sequence' => 1, 'type' => 6, 'value' => '|host_snmp_community|'], 'cdef_items');
		db_execute_prepared('UPDATE graph_templates_item SET cdef_id = ? WHERE id = ?', [$definition, $item['id']]);
		library_expect(str_contains(library_graph_source($graph, $options), 'CDEF:cdef'), false, 'A definition that resolves to nothing never reaches RRDtool');
		sql_save($item, 'graph_templates_item');
		$rule                     = $item;
		$rule['id']               = 0;
		$rule['graph_type_id']    = GRAPH_ITEM_TYPE_HRULE;
		$rule['sequence']         = 1000;
		$rule['value']            = '|95:bytes:0:current:2|';
		$rule['text_format']      = 'Percentile fixture';
		$extra[]                  = (int) sql_save($rule, 'graph_templates_item');
		$comment                  = $item;
		$comment['id']            = 0;
		$comment['graph_type_id'] = GRAPH_ITEM_TYPE_COMMENT;
		$comment['sequence']      = 1001;
		$comment['text_format']   = 'Transfer |sum:0:current:2:auto| at |date_time|';
		$extra[]                  = (int) sql_save($comment, 'graph_templates_item');
		$metadata                 = [];
		$image                    = rrdtool_function_graph($graph, '', $options, null, $metadata);
		library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Percentile and transfer legends render against stored samples');
		library_expect($metadata['NthPercentile'][0]['format'], $rule['value'], 'Percentile export metadata identifies its calculation');
		library_expect((float) $metadata['NthPercentile'][0]['value'], 12.5, 'Percentile of constant fixture samples remains 12.5');
		library_expect($metadata['Summation'][0]['format'], '|sum:0:current:2:auto|', 'Summation metadata retains its calculation format');
		library_expect(str_contains($metadata['Summation'][0]['value'], '|sum:'), false, 'Summation metadata contains the computed transfer value');
		// A broken stored definition must fail before executing RRDtool.
		$relative                = $options;
		$relative['graph_start'] = -3600;
		$relative['graph_end']   = -300;
		$image                   = rrdtool_function_graph($graph, '', $relative);
		library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Relative start and end times render through RRDtool');
		ob_start();

		try {
			rrdtool_function_graph($graph, '', $relative + ['print_source' => true]);
			$command = html_entity_decode(ob_get_contents(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			library_expect(str_contains($command, 'COMMENT:"From ') && str_contains($command, ' To '), true, 'Relative graph includes a resolved time-range legend');
		} finally {
			ob_end_clean();
		}
		db_execute_prepared('DELETE FROM cdef_items WHERE cdef_id = ?', [$definition]);
		sql_save(['id' => 0, 'cdef_id' => $definition, 'sequence' => 1, 'type' => 1, 'value' => '999999'], 'cdef_items');
		db_execute_prepared('UPDATE graph_templates_item SET cdef_id = ? WHERE id = ?', [$definition, $item['id']]);
		ob_start();

		try {
			library_expect(rrdtool_function_graph($graph, '', $options + ['export_csv' => true]), false, 'Invalid stored expression cannot publish a successful CSV export');
			library_expect(str_contains(ob_get_contents(), 'Invalid CDEF'), true, 'Invalid definition identifies the failure to the operator');
		} finally {
			ob_end_clean();
		}
	} finally {
		sql_save($series, 'data_template_rrd');
		sql_save($item, 'graph_templates_item');

		foreach ($extra as $id) {
			db_execute_prepared('DELETE FROM graph_templates_item WHERE id = ?', [$id]);
		}
		db_execute_prepared('DELETE FROM cdef_items WHERE cdef_id = ?', [$definition]);
		db_execute_prepared('DELETE FROM cdef WHERE id = ?', [$definition]);
		set_config_option('enable_rrdtool_gradient_support', $previousGradient);
	}
}

/** Peak percentiles and summation legends read the fixture's stored samples. */
function library_graph_totals(int $graph, array $options): void {
	require_once CACTI_PATH_LIBRARY . '/graph_variables.php';
	$items = db_fetch_assoc_prepared('SELECT gti.*, dtr.local_data_id, dtr.data_source_name, dtr.id AS data_template_rrd_id FROM graph_templates_item AS gti INNER JOIN data_template_rrd AS dtr ON dtr.id = gti.task_item_id WHERE gti.local_graph_id = ? ORDER BY gti.sequence', [$graph]);
	library_expect(count($items) > 0, true, 'Summation fixture uses stored series');
	$item  = $items[0];
	$local = (int) $item['local_data_id'];
	library_expect(rrdtool_function_contains_cf($local, 'MAX'), true, 'Fixture RRD keeps a MAX archive');
	$peak = nth_percentile([$local => [$item['data_source_name']]], (int) $options['graph_start'], (int) $options['graph_end'], 95, 0, true);
	library_expect((float) ($peak[$item['data_source_name']] ?? 0), 12.5, 'Peak percentile is computed from the stored MAX samples');
	$settings = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', [$graph]);
	$results  = [];
	$step     = (int) rrdtool_function_info($local)['step'];

	foreach (['total', 'atomic'] as $type) {
		$match           = ['|sum:0:' . $type . ':2:auto|', '0', $type, '2', 'auto'];
		$results[$type]  = variable_bandwidth_summation($match, $settings, $item, $items, (int) $options['graph_start'], (int) $options['graph_end'], 1, $step);
		// Three stored gauge samples of 12.5 span the legend window.
		library_expect((float) str_replace(',', '', trim($results[$type])), 12.5 * 3 * $step, 'Summation legend totals the stored samples for ' . $type);
	}
}

/** Return the RRDtool command the graph compiler builds without executing it. */
function library_graph_source(int $graph, array $options): string {
	ob_start();

	try {
		rrdtool_function_graph($graph, '', $options + ['print_source' => true]);

		return html_entity_decode((string) ob_get_contents(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	} finally {
		ob_end_clean();
	}
}
