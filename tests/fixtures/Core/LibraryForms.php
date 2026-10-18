<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

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
	} finally {
		db_execute_prepared('UPDATE graph_templates_item SET text_format = ? WHERE id = ?', [$original, $source]);
		db_execute_prepared('DELETE FROM aggregate_graphs_graph_item WHERE aggregate_graph_id = ?', [$aggregate['id']]);
	}
}

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
		$color = (int) db_fetch_cell('SELECT id FROM colors ORDER BY id LIMIT 1');
		$dom   = library_form_dom(static fn () => form_color_dropdown('entry', $color, 'None', 0));
		library_expect($dom->evaluate('string(//option[@selected]/@value)'), (string) $color, 'Color dropdown preserves the stored color identity');
		$dom = library_form_dom(static fn () => form_font_box('entry', $value, '', 120, 30, 'text', 1, $value));
		library_expect($dom->evaluate('string(//input/@value)'), $value, 'Font descriptions preserve punctuation');
	} finally {
		$_SESSION = $session;
		set_config_option('autocomplete', $autocomplete);
	}
}
