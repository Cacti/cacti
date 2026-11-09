<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Capture the output of a render callback and return it as an XPath-queryable document. */
function library_form_dom(callable $render): DOMXPath {
	ob_start();

	try {
		$render();
		$html = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);

	try {
		library_expect($document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>'), true, 'Rendered form parses as HTML');
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
	}

	return new DOMXPath($document);
}

/** Return what cacti_log() appended to the active log while the callback ran. */
function library_log_written_by(callable $callback): string {
	$log = cacti_log_file();
	clearstatcache(true, $log);
	$offset = is_file($log) ? filesize($log) : 0;
	$callback();
	clearstatcache(true, $log);

	return (string) file_get_contents($log, false, null, $offset);
}

/** Render and submit the aggregate graph item editor for an installed template. */
function library_aggregate_editor(int $template, array $aggregate): void {
	$items = db_fetch_assoc_prepared('SELECT id, graph_type_id FROM graph_templates_item WHERE graph_template_id = ? AND local_graph_id = 0 ORDER BY sequence', [$template]);
	library_expect(count($items) > 0, true, 'Aggregate editor uses installed template items');
	$dom = library_form_dom(static fn () => draw_aggregate_graph_items_list(0, $template));
	library_expect($dom->evaluate('string(//input[@name="item_no"]/@value)'), (string) count($items), 'New aggregate submits the actual number of source items');
	library_expect($dom->evaluate('count(//input[starts-with(@name,"agg_total_")][@checked])'), 0.0, 'New aggregate does not preselect totals');
	$submitted = [];
	$save      = [];

	foreach ($items as $index => $item) {
		$id        = (int) $item['id'];
		$save[$id] = ['aggregate_graph_id' => $aggregate['id'], 'graph_templates_item_id' => $id,
			'item_skip'                       => '', 'item_total' => '', 'sequence' => $index + 1];
		$submitted[($index % 2 === 0 ? 'agg_total_' : 'agg_skip_') . $id] = 'on';
	}
	aggregate_validate_graph_items($submitted, $save);
	library_expect(aggregate_graph_items_save($save, 'aggregate_graphs_graph_item'), true, 'Submitted editor selections persist through the production save helper');
	$values = db_fetch_assoc_prepared('SELECT * FROM aggregate_graphs_graph_item WHERE aggregate_graph_id = ?', [$aggregate['id']]);
	library_expect(count($values) > 0, true, 'Saved aggregate has persisted editor values');
	$dom = library_form_dom(static fn () => draw_aggregate_graph_items_list(0, $template, $aggregate));
	library_expect($dom->evaluate('string(//input[@name="item_no"]/@value)'), (string) count($items), 'Editing retains the full source item count');

	foreach ($values as $value) {
		$id = (int) $value['graph_templates_item_id'];
		library_expect($dom->query('//input[@name="agg_total_' . $id . '"]')->length, 1, 'Every saved item has its own rendered total control');
		library_expect($value['item_total'], $submitted['agg_total_' . $id] ?? '', 'Stored total matches the submitted selection');
		library_expect($value['item_skip'], $submitted['agg_skip_' . $id] ?? '', 'Stored skip matches the submitted selection');
		library_expect($dom->query('//input[@name="agg_total_' . $id . '"][@checked]')->length > 0, $value['item_total'] === 'on', 'Saved total selection returns to its own item');
		library_expect($dom->query('//input[@name="agg_skip_' . $id . '"][@checked]')->length > 0, $value['item_skip'] === 'on', 'Saved skip selection returns to its own item');
	}
	$source   = (int) $items[0]['id'];
	$original = db_fetch_cell_prepared('SELECT text_format FROM graph_templates_item WHERE id = ?', [$source]);

	try {
		library_expect(db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', ['<em>Fixture & legend</em>', $source]), true, 'Template legend persists before rendering');
		$dom = library_form_dom(static fn () => draw_aggregate_graph_items_list(0, $template, $aggregate));
		library_expect(str_contains($dom->document->textContent, '<em>Fixture & legend</em>'), true, 'Aggregate legend remains literal text');
		library_expect($dom->query('//em[text()="Fixture & legend"]')->length, 0, 'Legend cannot inject an element into the editor');
		$sourceItem = db_fetch_row_prepared('SELECT * FROM graph_templates_item WHERE id = ?', [$source]);

		try {
			foreach ([[GRAPH_ITEM_TYPE_HRULE, '42', 'Rule'], [GRAPH_ITEM_TYPE_VRULE, '1000', 'Time'], [GRAPH_ITEM_TYPE_COMMENT, '', '']] as [$type, $value, $legend]) {
				db_execute_prepared('UPDATE graph_templates_item SET graph_type_id = ?, value = ?, text_format = ? WHERE id = ?', [$type, $value, $legend, $source]);

				foreach ([[], $aggregate] as $object) {
					$dom = library_form_dom(static fn () => draw_aggregate_graph_items_list(0, $template, $object));
					library_expect($dom->query('//input[@id="dummy_' . $source . '"][@disabled]')->length, 1, 'Non-series item shows a disabled skip control');
					library_expect($dom->query('//input[@id="dummy1_' . $source . '"][@disabled]')->length, 1, 'Non-series item cannot be selected for totals');
					library_expect($dom->query('//input[@name="agg_skip_' . $source . '"][@checked]')->length, $object ? 1 : 0, 'Editing carries forced exclusion through its successful hidden control');
					library_expect($dom->query('//input[@name="agg_total_' . $source . '"][@checked]')->length, 0, 'Forced exclusion never submits a total');
				}
			}
		} finally {
			sql_save($sourceItem, 'graph_templates_item');
		}
	} finally {
		db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', [$original, $source]);
		db_execute_prepared('DELETE FROM aggregate_graphs_graph_item WHERE aggregate_graph_id = ?', [$aggregate['id']]);
	}
}

/** Render the generic form helpers with hostile values and check escaping and attributes. */
function library_forms(): void {
	global $form_id;
	require_once CACTI_PATH_LIBRARY . '/html_form.php';
	$session      = $_SESSION;
	$autocomplete = read_config_option('autocomplete');
	$form_id      = 'coverage_form';
	$value        = 'Café "quoted" & <tag>';

	try {
		foreach ([false, true] as $redisplay) {
			$_SESSION[SESS_ERROR_FIELDS] = $redisplay ? ['entry' => true] : [];
			$_SESSION[SESS_FIELD_VALUES] = $redisplay ? ['entry' => $value] : [];
			$dom                         = library_form_dom(static fn () => form_text_box('entry', '', 'default', 120, 30, 'text', 0, $value, $value));
			library_expect($dom->evaluate('string(//input/@value)'), $redisplay ? $value : 'default', 'Textbox preserves defaults and rejected input');
			library_expect($dom->evaluate('string(//input/@placeholder)'), $value, 'Placeholder survives HTML escaping');
			library_expect($dom->evaluate('string(//input/@title)'), $value, 'Title survives HTML escaping');
			library_expect($dom->evaluate('count(//tag)'), 0.0, 'Textbox data never becomes an element');
			$_SESSION[SESS_ERROR_FIELDS] = $redisplay ? ['entry' => true] : [];
			$dom                         = library_form_dom(static fn () => form_text_area('entry', '', 3, 40, 'default', 'fixture', '', $value));
			library_expect($dom->evaluate('string(//textarea)'), $redisplay ? $value : 'default', 'Textarea preserves defaults and rejected input');
			library_expect($dom->evaluate('string(//textarea/@aria-multiline)'), 'true', 'Textarea exposes multiline semantics');
			library_expect($dom->evaluate('count(//tag)'), 0.0, 'Textarea data never becomes markup');
		}
		$_SESSION[SESS_FIELD_VALUES] = [];

		foreach ([[null, 0, true], ['', 12, false], ['on', 12, true]] as [$stored, $id, $checked]) {
			$dom = library_form_dom(static fn () => form_checkbox('entry', $stored, $value, 'on', $id, 'fixture', '', $value, true));
			library_expect($dom->evaluate('count(//input[@checked])') > 0, $checked, 'Checkbox distinguishes create defaults from stored false');
			library_expect($dom->evaluate('string(//input/@aria-checked)'), $checked ? 'true' : 'false', 'Checkbox accessibility matches its state');
			library_expect($dom->evaluate('string(//label[@for="entry"])'), $value, 'Checkbox caption remains text');
		}

		foreach (['', '2'] as $selected) {
			$dom = library_form_dom(static fn () => form_dropdown('entry', [1 => $value, 2 => 'Other'], '', '', $selected, 'None', '1', 'fixture', '', $value));
			library_expect($dom->evaluate('string(//option[@selected]/@value)'), $selected === '' ? '1' : '2', 'Dropdown respects stored identity and default');
			library_expect($dom->evaluate('string(//option[@value="1"])'), $value, 'Dropdown label is escaped');
			library_expect($dom->evaluate('string(//select/@form)'), 'coverage_form', 'Detached dropdown belongs to its form');
		}

		foreach (['0', '1'] as $mode) {
			set_config_option('autocomplete', $mode);
			$dom = library_form_dom(static fn () => form_callback('entry', "SELECT 1 AS id, 'Fixture' AS name", 'name', 'id', 'get_devices', '1', 'Fixture', 'None', '', '', '', '', 'site=1'));
			library_expect($dom->evaluate('string(//option[@selected]/@value)'), '1', 'Both callback modes retain the selected identity');
			library_expect($dom->evaluate('string(//option[@selected])'), 'Fixture', 'Both callback modes retain the selected label');

			if ($mode === '0') {
				library_expect($dom->evaluate('string(//select/@data-action)'), 'get_devices', 'AJAX callback preserves its action');
			}
		}
		set_config_option('autocomplete', '0');
		$dom = library_form_dom(static fn () => form_callback('entry', '', '', '', 'get_devices', '7', $value, 'None', '', 'select2-callback', '', '', $value));
		library_expect($dom->evaluate('string(//option[@selected])'), $value, 'Callback display value remains text');
		library_expect($dom->evaluate('string(//select/@data-variables)'), $value, 'Callback variables preserve their attribute value');
		$dom = library_form_dom(static fn () => form_multi_dropdown('entry', [1 => 'One', 2 => 'Two', 3 => 'Three'], '1,3', 'id'));
		library_expect($dom->evaluate('count(//option[@selected])'), 2.0, 'Multi-select retains both stored identities');
		library_expect($dom->evaluate('count(//option[@value="2"][@selected])'), 0.0, 'Multi-select leaves unselected identities alone');

		foreach (['create', 'save', 'close', 'import', 'export', 'return'] as $action) {
			$dom = library_form_dom(static function () use ($action): void {
				form_start('host.php', 'coverage_form', $action === 'import');
				form_hidden_box('fixture_id', '17', '0');
				form_save_button('host.php', $action, 'fixture_id', false);
			});
			library_expect($dom->evaluate('string(//form/@method)'), 'post', 'Save actions use POST');
			library_expect($dom->evaluate('string(//input[@name="fixture_id"]/@value)'), '17', 'Save actions preserve the object identity');
			library_expect($dom->evaluate('string(//button[@type="submit"]/@value)'), $action === 'return' ? 'create' : $action, 'Save button selects the intended operation');
			library_expect($dom->evaluate('string(//button[@type="submit"]/@form)'), 'coverage_form', 'Save button belongs to the form');
		}
		$_SESSION[SESS_ERROR_FIELDS] = ['upload' => true];
		$dom                         = library_form_dom(static fn () => form_file('upload', 30, '.xml'));
		library_expect($dom->evaluate('string(//input/@accept)'), '.xml', 'File picker retains its supported extension');
		library_expect(str_contains($dom->evaluate('string(//input/@class)'), 'txtErrorTextBox'), true, 'Failed file input exposes validation state');
		$dom = library_form_dom(static fn () => form_confirm_buttons('host.php?action=delete&id=17', 'host.php'));
		library_expect($dom->evaluate('count(//button[@value="cancel"])'), 1.0, 'Confirmation retains its cancel control');
		library_expect($dom->evaluate('count(//button[@value="delete"])'), 1.0, 'Confirmation retains its delete control');
		library_expect($dom->evaluate('string(//button[@value="cancel"]/@onclick)'), 'cactiReturnTo("' . CACTI_PATH_URL . 'host.php")', 'Cancel returns to the original page');
		library_expect($dom->evaluate('string(//button[@value="delete"]/@onclick)'), 'cactiReturnTo("' . CACTI_PATH_URL . 'host.php?action=delete&id=17&confirm=true")', 'Confirmation preserves query parameters and adds explicit consent');
		$missing = 'coverage_missing_control_' . bin2hex(random_bytes(4));
		$field   = ['method' => 'missing'];
		$dom     = library_form_dom(static function () use ($missing, &$field): void {
			draw_edit_control($missing, $field);
		});
		library_expect($dom->evaluate('count(//input | //select | //textarea)'), 0.0, 'An invalid field definition does not render a misleading control');
		library_expect(str_contains(file_get_contents(read_config_option('path_cactilog')), 'Field Name: ' . $missing . ' includes Method: missing does not include a value'), true, 'Invalid field definitions report their name and method in the log');
		$color = (int) db_fetch_cell('SELECT id FROM colors ORDER BY id LIMIT 1');
		$dom   = library_form_dom(static fn () => form_color_dropdown('entry', $color, 'None', 0));
		library_expect($dom->evaluate('string(//option[@selected]/@value)'), (string) $color, 'Color dropdown preserves the stored color identity');
		$dom = library_form_dom(static fn () => form_font_box('entry', $value, '', 120, 30, 'text', 1, $value));
		library_expect($dom->evaluate('string(//input/@value)'), $value, 'Font descriptions preserve punctuation');
		$_SESSION[SESS_ERROR_FIELDS] = [];
		$_SESSION[SESS_FIELD_VALUES] = [];
		$dom                         = library_form_dom(static fn () => form_dirpath_box('entry', __FILE__, '', 255));
		library_expect($dom->evaluate('string(//input/@value)'), __FILE__, 'Directory field retains a file path for correction');
		library_expect(str_contains($dom->evaluate('string(//span[contains(@class, "fa-circle-x")]/@title)'), 'Path is a File and not a Directory'), true, 'A file in a directory field is flagged');
		$log = library_log_written_by(static function () use (&$dom): void {
			$dom = library_form_dom(static fn () => draw_edit_form(['config' => ['no_form_tag' => true], 'fields' => ['coverage_sub' => [
				'method'       => 'textbox', 'friendly_name' => 'Coverage field', 'value' => 'stored', 'max_length' => 10,
				'sub_checkbox' => ['name' => 'coverage_sub_enabled', 'friendly_name' => 'Enabled']]]]));
		});
		library_expect($dom->evaluate('string(//input[@name="coverage_sub_enabled"]/@type)'), 'checkbox', 'A sub-checkbox without a value still renders');
		library_expect($dom->evaluate('count(//input[@name="coverage_sub_enabled"][@checked])'), 0.0, 'A sub-checkbox without a value or default is unchecked');
		library_expect(str_contains($log, "Cacti Form field 'coverage_sub' does not include a sub_checkbox 'value' Column"), true, 'A sub-checkbox without a value is reported to developers');
	} finally {
		$_SESSION = $session;
		set_config_option('autocomplete', $autocomplete);
	}
}

/** Render the graph and data template forms for a stored graph and its data source. */
function library_template_forms(int $graph): void {
	global $struct_graph_item;
	require_once CACTI_PATH_LIBRARY . '/html_form_template.php';
	$graphValues   = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', [$graph]);
	$template      = (int) $graphValues['graph_template_id'];
	$graphTemplate = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE graph_template_id = ? AND local_graph_id = 0', [$template]);
	$dataId        = (int) db_fetch_cell_prepared('SELECT dtr.local_data_id FROM graph_templates_item gti JOIN data_template_rrd dtr ON dtr.id = gti.task_item_id WHERE gti.local_graph_id = ? LIMIT 1', [$graph]);
	$dataValues    = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?', [$dataId]);
	$dataTemplate  = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE data_template_id = ? AND local_data_id = 0', [$dataValues['data_template_id']]);
	$rrds          = db_fetch_assoc_prepared('SELECT * FROM data_template_rrd WHERE local_data_id = ? ORDER BY id', [$dataId]);
	library_expect(count($rrds) > 0, true, 'Template editor uses a real graph with data sources');
	$structure = $struct_graph_item;

	try {
		library_expect(db_execute_prepared('UPDATE graph_templates_graph SET t_title = ?, t_width = ? WHERE id = ?', ['on', '', $graphTemplate['id']]), true, 'Graph template field ownership is configured');
		library_expect(db_execute_prepared('UPDATE data_template_data SET t_name = ? WHERE id = ?', ['on', $dataTemplate['id']]), true, 'Data template allows instance names');

		foreach ([false, true] as $hidden) {
			$dom = library_form_dom(static function () use ($template, &$graphValues, $hidden): void {
				$count = draw_nontemplated_fields_graph($template, $graphValues, 'g_|field|', 'Graph fields', $hidden, $hidden);
				library_expect($count > 0, true, 'Graph template exposes its per-graph fields');
			});
			library_expect($dom->evaluate('string(//input[@name="g_title"]/@value)'), $graphValues['title'], 'Graph editor preserves the stored title');
			library_expect($dom->query('//input[@name="g_width"][@type="hidden"]')->length, $hidden ? 1 : 0, 'Template-owned width is hidden or omitted according to the form contract');

			foreach ([0, $dataId] as $local) {
				$dom = library_form_dom(static fn () => draw_nontemplated_fields_data_source((int) $dataValues['data_template_id'], $local, $dataValues, 'd_|field|', 'Data fields', $hidden, $hidden));
				library_expect($dom->evaluate('string(//input[@name="d_name"]/@value)'), $dataValues['name'], 'Data source editor retains the instance name');
				library_expect($dom->query('//input[@name="d_data_source_path"][@type="hidden"]')->length, $local === 0 && $hidden ? 1 : 0, 'Pre-creation form does not expose an editable RRD path');
			}
			$dom = library_form_dom(static fn () => draw_nontemplated_fields_data_source_item((int) $dataValues['data_template_id'], $rrds, 'r_|field|_|id|', 'Series fields', $hidden, $hidden, true));

			foreach ($rrds as $rrd) {
				library_expect($dom->evaluate('string(//input[@name="r_data_source_name_' . $rrd['id'] . '"]/@value)'), $rrd['data_source_name'], 'Series editor preserves each stored RRD identity');
			}

			foreach ([0, $graph] as $local) {
				$dom = library_form_dom(static function () use ($template, $local, $hidden): void {
					$count = draw_nontemplated_fields_graph_item($template, $local, '|field|_|id|', 'Graph item fields', $hidden, $hidden ? 'true' : 'false');
					library_expect($count >= 0, true, 'Graph item form reports the rendered input count');
				});
				library_expect($dom->query('//select[starts-with(@name,"task_item_id")]')->length > 0, $local !== 0 && !$hidden, 'Data-source selection is available only for existing unlocked graphs');
			}
		}
		library_template_query_forms($graph, $rrds, (int) $dataValues['data_template_id'], (int) $dataValues['id']);
	} finally {
		sql_save($graphTemplate, 'graph_templates_graph');
		sql_save($dataTemplate, 'data_template_data');
		$struct_graph_item = $structure;
	}
}

/** Query-owned fields are hidden or replaced with guidance when the editor has no query context to fill them. */
function library_template_query_forms(int $graph, array $rrds, int $dataTemplate, int $dataTemplateData): void {
	$templateRrd = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', [$rrds[0]['local_data_template_rrd_id']]);
	$device      = (int) db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [$graph]);
	$missing     = (int) db_fetch_cell('SELECT COALESCE(MAX(id), 0) + 1000 FROM snmp_query');
	$local       = $definition = 0;

	try {
		library_expect(db_execute_prepared('UPDATE data_template_rrd SET t_rrd_maximum = ? WHERE id = ?', ['on', $templateRrd['id']]), true, 'Series maximum is editable per data source');
		$dom = library_form_dom(static fn () => draw_nontemplated_fields_data_source_item($dataTemplate, $rrds, 'r_|field|_|id|', 'Series fields', true, true, false, $missing));
		library_expect($dom->evaluate('count(//div[contains(@class,"tableSubHeaderColumn")][contains(., "Series fields [' . $rrds[0]['data_source_name'] . ']")])') > 0, true, 'Per-series header names the data source');
		library_expect($dom->query('//input[@name="r_rrd_maximum_' . $rrds[0]['id'] . '"]')->length, 1, 'A query graph without suggested values leaves the series field editable');

		$input = (int) db_fetch_cell("SELECT data_input_id FROM data_input_fields WHERE type_code = 'index_type' ORDER BY id LIMIT 1");
		$local = (int) sql_save(['id' => 0, 'host_id' => $device, 'data_template_id' => $dataTemplate, 'snmp_query_id' => $missing, 'snmp_index' => '1'], 'data_local');
		library_expect($local > 0, true, 'Fixture owns a data source of an undiscovered query');
		$definition = (int) sql_save(['id' => 0, 'local_data_id' => $local, 'data_template_id' => $dataTemplate, 'data_input_id' => $input, 'name' => 'Undiscovered query source'], 'data_template_data');
		library_expect($definition > 0, true, 'Undiscovered query source has metadata');
		$custom = library_form_dom(static fn () => draw_nontemplated_fields_custom_data($definition, 'c_|id|', 'Custom fields', true, true, $missing));
		$index  = (int) db_fetch_cell_prepared("SELECT id FROM data_input_fields WHERE data_input_id = ? AND type_code = 'index_type'", [$input]);
		library_expect($custom->query('//input[@name="c_' . $index . '"][@type="hidden"]')->length, 1, 'Query-owned index fields are carried hidden');

		foreach (['index_type', 'output_type'] as $type) {
			$field = (int) db_fetch_cell_prepared('SELECT id FROM data_input_fields WHERE type_code = ? ORDER BY id LIMIT 1', [$type]);
			library_expect($field > 0, true, 'Installed query input supplies ' . $type);
			$dom = library_form_dom(static fn () => draw_custom_data_row('c_' . $type, $field, $definition, ''));
			library_expect(str_contains($dom->document->textContent, 'Data Query Data Sources must be created through'), true, 'Undiscovered ' . $type . ' points the operator at graph creation');
			library_expect($dom->query('//select')->length, 0, 'Undiscovered ' . $type . ' offers no stale choices');
		}
	} finally {
		db_execute_prepared('UPDATE data_template_rrd SET t_rrd_maximum = ? WHERE id = ?', [$templateRrd['t_rrd_maximum'], $templateRrd['id']]);
		db_execute_prepared('DELETE FROM data_template_data WHERE id = ?', [$definition]);
		db_execute_prepared('DELETE FROM data_local WHERE id = ?', [$local]);
	}
}
