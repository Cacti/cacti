<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_maintenance(): void {
	$original = (int) sql_save(['color_template_id' => 0, 'name' => 'Coverage source colors'], 'color_templates', 'color_template_id');
	library_expect($original > 0, true, 'Color duplication owns its source template');
	$copies = [];

	try {
		$colors = array_map('intval', array_column(db_fetch_assoc('SELECT id FROM colors ORDER BY id LIMIT 2'), 'id'));
		library_expect(count($colors), 2, 'Color duplication uses installed palette values');

		foreach ($colors as $index => $color) {
			library_expect((int) sql_save(['color_template_item_id' => 0, 'color_template_id' => $original, 'color_id' => $color, 'sequence' => $index + 1], 'color_template_items', 'color_template_item_id') > 0, true, 'Source palette item persists');
		}

		foreach (['Copy of <template_title>' => 'Copy of Coverage source colors', 'Literal copy' => 'Literal copy'] as $pattern => $title) {
			duplicate_color_template($original, $pattern);
			$id       = (int) db_fetch_cell_prepared('SELECT color_template_id FROM color_templates WHERE name = ? ORDER BY color_template_id DESC LIMIT 1', [$title]);
			$copies[] = $id;
			library_expect($id > 0 && $id !== $original, true, 'Duplicated palette has independent identity');
			library_expect(array_map('intval', array_column(db_fetch_assoc_prepared('SELECT color_id FROM color_template_items WHERE color_template_id = ? ORDER BY sequence', [$id]), 'color_id')), $colors, 'Duplicated palette preserves color order');
		}
		library_expect(db_fetch_cell_prepared('SELECT name FROM color_templates WHERE color_template_id = ?', [$original]), 'Coverage source colors', 'Duplication preserves the source title');
	} finally {
		foreach (array_merge([$original], $copies) as $id) {
			db_execute_prepared('DELETE FROM color_template_items WHERE color_template_id = ?', [$id]);
			db_execute_prepared('DELETE FROM color_templates WHERE color_template_id = ?', [$id]);
		}
	}
}

/** Repair duplicate legacy hashes without losing the canonical value or RRD identity. */
function library_input_repair(): void {
	$canonical = db_fetch_row_prepared('SELECT * FROM data_input_fields WHERE hash = ?', ['92f5906c8dc0f964b41f4253df582c38']);
	library_expect(!empty($canonical), true, 'Repair fixture uses the installed canonical SNMP field');
	$fields = $templates = $rrds = [];

	try {
		foreach (['duplicate', 'unmapped', 'unknown'] as $kind) {
			$row         = $canonical;
			$row['id']   = 0;
			$row['hash'] = bin2hex(random_bytes(16));

			if ($kind === 'unknown') {
				$row['data_name'] = 'coverageUnknownInput';
			}
			$fields[$kind] = (int) sql_save($row, 'data_input_fields');
			library_expect($fields[$kind] > 0, true, 'Repair owns its malformed legacy field');
		}

		foreach (['conflict', 'move'] as $kind) {
			$templates[$kind] = (int) sql_save(['id' => 0, 'name' => 'Repair fixture ' . $kind, 'data_input_id' => $canonical['data_input_id']], 'data_template_data');
			library_expect($templates[$kind] > 0, true, 'Repair owns its template data');
			// This case requires an absent canonical mapping, including any empty
			// default left at a reused fixture identity by earlier lifecycle tests.
			db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id = ?', [$templates[$kind]]);
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM data_input_data WHERE data_template_data_id = ?', [$templates[$kind]]), 0, 'Repair starts from the intended absent-mapping state');
			library_expect(db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (?, ?, ?)', [$fields['duplicate'], $templates[$kind], 'Legacy ' . $kind]), true, 'Malformed field has a stored value');
		}
		library_expect(db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (?, ?, ?)', [$canonical['id'], $templates['conflict'], 'Canonical value']), true, 'Conflicting mapping has an authoritative value');
		$rrd    = (int) sql_save(['id' => 0, 'data_source_name' => 'coverage_repair', 'data_input_field_id' => $fields['duplicate']], 'data_template_rrd');
		$rrds[] = $rrd;
		library_expect($rrd > 0, true, 'Legacy field has an owned RRD mapping');
		repair_system_data_input_methods();
		library_expect(db_fetch_cell_prepared('SELECT value FROM data_input_data WHERE data_input_field_id = ? AND data_template_data_id = ?', [$canonical['id'], $templates['conflict']]), 'Canonical value', 'Repair preserves an existing canonical value');
		library_expect(db_fetch_cell_prepared('SELECT value FROM data_input_data WHERE data_input_field_id = ? AND data_template_data_id = ?', [$canonical['id'], $templates['move']]), 'Legacy move', 'Repair moves an otherwise unrepresented value');
		library_expect((int) db_fetch_cell_prepared('SELECT data_input_field_id FROM data_template_rrd WHERE id = ?', [$rrd]), (int) $canonical['id'], 'Repair preserves RRD identity while correcting its field');

		foreach (['duplicate', 'unmapped'] as $kind) {
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM data_input_fields WHERE id = ?', [$fields[$kind]]), 0, 'Repair removes the redundant legacy field');
		}
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM data_input_fields WHERE id = ?', [$fields['unknown']]), 1, 'Repair preserves an unknown field rather than guessing its meaning');
		repair_system_data_input_methods();
		library_expect(db_fetch_row_prepared('SELECT * FROM data_input_fields WHERE id = ?', [$canonical['id']]), $canonical, 'Repeated repair preserves the canonical definition');
	} finally {
		foreach ($templates as $id) {
			db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id = ?', [$id]);
			db_execute_prepared('DELETE FROM data_template_data WHERE id = ?', [$id]);
		}

		foreach ($fields as $id) {
			db_execute_prepared('DELETE FROM data_input_fields WHERE id = ?', [$id]);
		}

		foreach ($rrds as $id) {
			db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', [$id]);
		}
	}
}
