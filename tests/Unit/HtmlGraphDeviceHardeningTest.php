<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function hardeningFunctionSource(string $source, string $function, string $nextFunction) : string {
	$start = strpos($source, 'function ' . $function . '(');
	$end   = strpos($source, 'function ' . $nextFunction . '(', $start + 1);

	expect($start)->not->toBeFalse($function . ' must exist');
	expect($end)->not->toBeFalse($nextFunction . ' must follow ' . $function);

	return substr($source, $start, $end - $start);
}

$htmlGraphSource = file_get_contents(CACTI_PATH_LIBRARY . '/html_graph.php');
$apiDeviceSource = file_get_contents(CACTI_PATH_LIBRARY . '/api_device.php');

require_once CACTI_PATH_LIBRARY . '/html_graph.php';

expect($htmlGraphSource)->not->toBeFalse();
expect($apiDeviceSource)->not->toBeFalse();

test('graph display validators accept only supported values', function () {
	global $item_rows;

	$previous_item_rows = $item_rows ?? null;
	$item_rows          = [
		-1 => 'All',
		10 => '10',
		20 => '20'
	];

	try {
		expect(html_graph_validate_columns(1))->toBe(1)
			->and(html_graph_validate_columns('6'))->toBe(6)
			->and(html_graph_validate_columns(0))->toBeFalse()
			->and(html_graph_validate_columns(7))->toBeFalse()
			->and(html_graph_validate_columns('2 rows'))->toBeFalse()
			->and(html_graph_validate_page_size(-1))->toBe(-1)
			->and(html_graph_validate_page_size('20'))->toBe(20)
			->and(html_graph_validate_page_size(21))->toBeFalse();
	} finally {
		$item_rows = $previous_item_rows;
	}
});

test('new graph overrides use one outer form and retain every rendered section', function () use ($htmlGraphSource) {
	$source = hardeningFunctionSource($htmlGraphSource, 'html_graph_new_graphs', 'html_graph_custom_data');

	expect(substr_count($source, "form_start('graphs_new.php', 'new_graphs')"))->toBe(1);
	expect($source)->toContain('$output_sections[] = $output;');
	expect($source)->toContain("print implode('', \$output_sections);");
});

test('SNMP query graph lookup binds both halves of the relationship', function () use ($htmlGraphSource) {
	$source = hardeningFunctionSource($htmlGraphSource, 'html_graph_custom_data', 'html_graph_custom_data_template');

	expect($source)->toContain('ON sqg.snmp_query_id = sq.id');
	expect($source)->toContain('WHERE sq.id = ?');
	expect($source)->toContain('AND sqg.id = ?');
});

test('graph page size and column settings use bounded callbacks', function () use ($htmlGraphSource) {
	expect($htmlGraphSource)->toContain("'options' => 'html_graph_validate_page_size'");
	expect($htmlGraphSource)->toContain("'options' => 'html_graph_validate_columns'");
	expect($htmlGraphSource)->toContain('$value < 1 || $value > 6');
	expect($htmlGraphSource)->toContain('!array_key_exists($value, $item_rows)');
});

test('graph debug stderr is returned and escaped before display', function () use ($htmlGraphSource) {
	expect($htmlGraphSource)->toContain("\$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_RETURN_STDERR;");
	expect($htmlGraphSource)->toContain('print htmle((string) @rrdtool_function_graph(');
});

test('graph HTML does not close void input elements', function () use ($htmlGraphSource) {
	expect($htmlGraphSource)->not->toContain('</input>');
});

test('device supplied ping and SNMP values are escaped at HTML sinks', function () use ($apiDeviceSource) {
	foreach (['snmp_error', 'snmp_system', 'snmp_uptime', 'snmp_hostname', 'snmp_location', 'snmp_contact'] as $variable) {
		expect($apiDeviceSource)->toContain('htmle($' . $variable . ')');
	}

	expect($apiDeviceSource)->toContain('htmle($ping->ping_response)');
	expect($apiDeviceSource)->not->toContain('print $results;');
});

test('SNMP authentication validation accepts SHA384 and rejects the SHA392 typo', function () use ($apiDeviceSource) {
	expect($apiDeviceSource)->toContain('SHA256|SHA384|SHA512');
	expect($apiDeviceSource)->not->toContain('SHA392');
});

test('device template synchronization builds its ID predicate with db_in_clause', function () use ($apiDeviceSource) {
	$source = hardeningFunctionSource($apiDeviceSource, 'api_device_template_sync_template', 'api_device_ping_device');

	expect($source)->toContain("db_in_clause('host.id', \$device_ids)");
	expect($source)->not->toContain("' AND host.id IN('");
});

test('device template downloads use a private random workspace and guaranteed cleanup', function () use ($apiDeviceSource) {
	$source = hardeningFunctionSource($apiDeviceSource, 'api_device_template_download', 'api_device_template_archive_for_export');

	expect($source)->toContain('bin2hex(random_bytes(16))');
	expect($source)->toContain('mkdir($temp_directory, 0700)');
	expect($source)->toContain('finally');
	expect($source)->not->toContain("sys_get_temp_dir() . '/' . \$filename");
});

test('data template cloning is reachable and clone all selects data templates', function () use ($apiDeviceSource) {
	$source = hardeningFunctionSource($apiDeviceSource, 'api_clone_device_template', 'api_device_template_download');

	expect($source)->not->toContain('if (1 == 1)');
	expect($source)->toContain('$selection_ids($include_dt, $objects[\'data_templates\'])');
	expect($source)->toContain('foreach ($cloned_data_templates as $id)');
	expect($source)->toContain('api_data_source_duplicate(0, $id, $new_name)');
	expect($source)->toContain('SET task_item_id = ?');
});

test('clone selections are validated and never interpolated into SQL', function () use ($apiDeviceSource) {
	$source = hardeningFunctionSource($apiDeviceSource, 'api_clone_device_template', 'api_device_template_download');

	expect($source)->toContain('ctype_digit($id)');
	expect($source)->toContain("db_in_clause('graph_template_id', \$included_graph_templates)");
	expect($source)->toContain("db_in_clause('snmp_query_id', \$included_data_queries)");
	expect($source)->not->toContain("'AND graph_template_id IN (' . \$include_gt");
	expect($source)->not->toContain("'AND snmp_query_id IN (' . \$include_dq");
});

test('device primary records are removed after graph cleanup', function () use ($apiDeviceSource) {
	$single = hardeningFunctionSource($apiDeviceSource, 'api_device_remove', 'api_device_purge_from_remote');
	$batch  = hardeningFunctionSource($apiDeviceSource, 'api_device_remove_multi', 'api_device_disable_devices');

	expect(strpos($single, 'api_delete_graphs('))->toBeLessThan(strpos($single, 'DELETE FROM host WHERE id = ?'));
	expect(strpos($batch, 'api_delete_graphs('))->toBeLessThan(strpos($batch, "DELETE FROM host WHERE '"));
	expect($single)->toContain('db_begin_transaction()');
	expect($single)->toContain('db_rollback_transaction()');
	expect($single)->toContain('db_commit_transaction()');
	expect($batch)->toContain('db_begin_transaction()');
});
