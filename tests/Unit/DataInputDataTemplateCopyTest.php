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

/*
 * Source-level guards for the data_input_data column-expansion bugs in
 * change_data_template() (lib/template.php) and api_data_source_duplicate()
 * (lib/api_data_source.php).
 *
 * Background. The data_input_data table on develop carries five identity
 * columns (data_input_field_id, data_template_data_id, data_template_id,
 * local_data_id, host_id) plus the value pair (t_value, value). Two
 * write paths copy rows from the template (parent) row into the child:
 *
 *   - change_data_template() in lib/template.php
 *   - api_data_source_duplicate() in lib/api_data_source.php
 *
 * Two regressions were live in the develop branch:
 *
 *   (1) change_data_template() copied $item['data_template_id'],
 *       $item['local_data_id'], $item['host_id'] from the parent row
 *       verbatim. For template rows those are 0, so child data_input_data
 *       rows ended up with local_data_id = 0 and host_id = 0, orphaned
 *       from the new data source. The fix reads host_id from
 *       data_local for the new $local_data_id and stamps the child row
 *       with the function's own ($data_template_id, $local_data_id,
 *       $host_id) instead of the parent's.
 *
 *   (2) api_data_source_duplicate() expanded the INSERT column list to
 *       seven (data_input_field_id, data_template_data_id,
 *       data_template_id, local_data_id, host_id, t_value, value) but
 *       left the placeholder list at four (?, ?, ?, ?), while still
 *       binding seven values. db_execute_prepared() rejects the
 *       mismatch, so duplicating a data source or template silently
 *       produced no data_input_data rows.
 *
 * Both bugs cascade into the same downstream symptom TheWitness reported
 * for 1.2.31: data sources created from a template (or duplicated from
 * an existing one) end up without populated data_input_data, which
 * breaks update_poller_cache() and leaves poller_item empty.
 *
 * The 1.2.x branch is unaffected. Its data_input_data schema only
 * carries the four columns (data_input_field_id, data_template_data_id,
 * t_value, value) and both call sites already match.
 */

$templateSource      = file_get_contents(__DIR__ . '/../../lib/template.php');
$apiDataSourceSource = file_get_contents(__DIR__ . '/../../lib/api_data_source.php');

/**
 * Extract the body of one PHP function definition out of a source blob.
 * Caller supplies the function name; returns the matched body or '' if
 * the definition is not found.
 */
function _extract_function_body(string $source, string $name): string {
	$pattern = '/^function\s+' . preg_quote($name, '/') . '\b[^{]*\{.*?^\}/sm';
	if (!preg_match($pattern, $source, $m)) {
		return '';
	}
	return $m[0];
}

test('change_data_template stamps child data_input_data rows with the new identity tuple', function () use ($templateSource) {
	$body = _extract_function_body($templateSource, 'change_data_template');
	expect($body)->not->toBe('');

	/* The function must look up the child host_id from data_local using
	 * the new $local_data_id. The pre-fix code did not do this and
	 * relied on $item['host_id'] from the parent row, which is 0 on a
	 * template. */
	expect($body)
		->toContain('SELECT host_id')
		->toContain('FROM data_local')
		->toContain('[$local_data_id]');

	/* The REPLACE INTO must use the function's own identity tuple
	 * ($data_template_id, $local_data_id, $host_id) for the three
	 * identity columns that previously came from the parent row. */
	$replacePos = strpos($body, 'REPLACE INTO data_input_data');
	expect($replacePos)->not->toBeFalse('REPLACE INTO data_input_data must remain present');

	$insertSlice = substr($body, $replacePos, 800);

	/* Column count and placeholder count must match. */
	expect(substr_count($insertSlice, '?'))
		->toBe(7);

	/* The seven bound values must include the function's own variables,
	 * not just $item[...] readbacks of the parent row. */
	expect($insertSlice)
		->toContain('$data_template_id,')
		->toContain('$local_data_id,')
		->toContain('$host_id,');

	/* The pre-fix bind list pulled these from $item; that pattern must
	 * not survive in the fixed code for the identity columns. */
	expect(strpos($insertSlice, "\$item['local_data_id']"))->toBeFalse(
		'must not bind $item[local_data_id] for the child row (template default is 0)'
	);
	expect(strpos($insertSlice, "\$item['host_id']"))->toBeFalse(
		'must not bind $item[host_id] for the child row (template default is 0)'
	);
});

test('api_data_source_duplicate INSERT column count matches placeholder count', function () use ($apiDataSourceSource) {
	$body = _extract_function_body($apiDataSourceSource, 'api_data_source_duplicate');
	expect($body)->not->toBe('');

	$insertPos = strpos($body, 'INSERT IGNORE INTO data_input_data');
	expect($insertPos)->not->toBeFalse('INSERT IGNORE INTO data_input_data must remain present');

	$insertSlice = substr($body, $insertPos, 800);

	/* Placeholder count must match the seven-column form the caller
	 * already binds. The pre-fix code had four placeholders against
	 * seven bound values; db_execute_prepared() rejects that. */
	expect(substr_count($insertSlice, '?'))
		->toBe(7);

	/* All seven columns must be named in the INSERT list. */
	foreach (['data_input_field_id', 'data_template_data_id', 'data_template_id', 'local_data_id', 'host_id', 't_value', 'value'] as $col) {
		expect($insertSlice)->toContain($col);
	}
});

test('1.2.x schema regression check: develop INSERT must not silently drop columns', function () use ($apiDataSourceSource, $templateSource) {
	/* If anyone re-narrows the develop INSERT/REPLACE column lists back
	 * to the 1.2.x four-column form (data_input_field_id,
	 * data_template_data_id, t_value, value) the column / placeholder
	 * count check above will still pass on length but the cross-source
	 * grammar drifts. Pin the seven-column shape explicitly so a
	 * narrowing change has to delete the columns from the SQL string
	 * rather than just re-shrink the bind array. */
	foreach ([$templateSource, $apiDataSourceSource] as $source) {
		$pos = strpos($source, '(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)');
		expect($pos)->not->toBeFalse('seven-column data_input_data write must remain present');
	}
});
