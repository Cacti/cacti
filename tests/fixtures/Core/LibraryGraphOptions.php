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
		// An unnamed series gets a valid RRD name before graph compilation.
		db_execute_prepared('UPDATE data_template_rrd SET data_source_name = ? WHERE id = ?', ['', $series['id']]);
		$generated = get_data_source_item_name((int) $series['id']);
		library_expect(strlen($generated) <= 19 && str_ends_with($generated, (string) $series['id']), true, 'Generated series name fits RRDtool and retains its unique identity');
		library_expect((bool) preg_match('/^[a-z0-9_]+$/', $generated), true, 'Generated series name contains only RRD name characters');
		sql_save($series, 'data_template_rrd');

		$parent   = array_replace($item, ['id' => 0, 'local_graph_id' => 0, 'sequence' => 500000, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1]);
		$parentId = (int) sql_save($parent, 'graph_templates_item');
		$extra[]  = $parentId;
		$child    = array_replace($item, ['id' => 0, 'local_graph_id' => 0, 'sequence' => 500001, 'graph_type_id' => GRAPH_ITEM_TYPE_COMMENT, 'hard_return' => '']);
		$childId  = (int) sql_save($child, 'graph_templates_item');
		$extra[]  = $childId;

		foreach (['|95:bytes:0:current:2|', '|sum:0:current:2:auto|', 'Unrelated legend'] as $legend) {
			db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', [$legend, $childId]);
			library_expect(array_values(get_graph_group($parentId)), $legend === 'Unrelated legend' ? [$parentId] : [$parentId, $childId], 'Graph grouping includes calculated legends and stops before unrelated comments');
		}
		db_execute_prepared('DELETE FROM graph_templates_item WHERE id IN (?, ?)', [$parentId, $childId]);

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
		sql_save($item, 'graph_templates_item');

		foreach (['', 'on'] as $gradient) {
			set_config_option('enable_rrdtool_gradient_support', $gradient);
			db_execute_prepared('UPDATE graph_templates_item SET graph_type_id = ?, alpha = ?, text_format = ? WHERE id = ?', [GRAPH_ITEM_TYPE_AREA, '80', 'Gradient fixture', $item['id']]);
			$image = rrdtool_function_graph($graph, '', $options);
			library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Area rendering supports normal and expanded gradient commands');
		}
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
