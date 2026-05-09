<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Mutation protection for the data_input_data column-expansion fix on
 * develop. Two write paths (change_data_template in lib/template.php and
 * api_data_source_duplicate in lib/api_data_source.php) had to grow from
 * the four-column 1.2.x shape to the seven-column develop shape. A
 * single-character mutation against either site is detectable through
 * the source-level guards below.
 */

$templateSource      = file_get_contents(__DIR__ . '/../../lib/template.php');
$apiDataSourceSource = file_get_contents(__DIR__ . '/../../lib/api_data_source.php');

if (!function_exists('_mut_extract_function')) {
	function _mut_extract_function(string $source, string $name): string {
		$pattern = '/^function\s+' . preg_quote($name, '/') . '\b[^{]*\{.*?^\}/sm';
		return preg_match($pattern, $source, $m) ? $m[0] : '';
	}
}

test('change_data_template REPLACE keeps seven placeholders (Mutation Protection)', function () use ($templateSource) {
	/* If a mutation narrows the placeholder list back to four `?`, the
	 * seven-element bind array no longer matches and db_execute_prepared()
	 * silently fails on every template-to-child copy. */
	$body = _mut_extract_function($templateSource, 'change_data_template');
	$slice = substr($body, strpos($body, 'REPLACE INTO data_input_data'), 800);
	expect(substr_count($slice, '?'))->toBe(7);
});

test('api_data_source_duplicate INSERT keeps seven placeholders (Mutation Protection)', function () use ($apiDataSourceSource) {
	$body = _mut_extract_function($apiDataSourceSource, 'api_data_source_duplicate');
	$slice = substr($body, strpos($body, 'INSERT IGNORE INTO data_input_data'), 800);
	expect(substr_count($slice, '?'))->toBe(7);
});

test('change_data_template binds function-scope identity, not parent template row (Mutation Protection)', function () use ($templateSource) {
	/* The whole point of the fix is to stamp the child row with the new
	 * data source's identity tuple. If a mutation reverts the bind list
	 * to the parent's `$item['local_data_id']` / `$item['host_id']`
	 * (always 0 for templates), the child rows go out orphaned again. */
	$body = _mut_extract_function($templateSource, 'change_data_template');
	$slice = substr($body, strpos($body, 'REPLACE INTO data_input_data'), 800);

	expect($slice)->toContain('$data_template_id,');
	expect($slice)->toContain('$local_data_id,');
	expect($slice)->toContain('$host_id,');
	expect(strpos($slice, "\$item['local_data_id']"))->toBeFalse();
	expect(strpos($slice, "\$item['host_id']"))->toBeFalse();
});

test('change_data_template looks up host_id from data_local for the new row (Mutation Protection)', function () use ($templateSource) {
	/* If a mutation drops the SELECT host_id, we lose the ability to
	 * stamp the child row with the device the data source actually
	 * belongs to. */
	$body = _mut_extract_function($templateSource, 'change_data_template');
	expect($body)->toContain('SELECT host_id');
	expect($body)->toContain('FROM data_local');
	expect($body)->toContain('[$local_data_id]');
});

test('seven-column INSERT/REPLACE shape is consistent across both write paths (Mutation Protection)', function () use ($templateSource, $apiDataSourceSource) {
	/* The two write paths must agree on the column list. A mutation in
	 * one path only would create a schema-shape skew that downstream
	 * consumers (update_poller_cache) can't reconcile. */
	$expectedColumns = '(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)';
	expect($templateSource)->toContain($expectedColumns);
	expect($apiDataSourceSource)->toContain($expectedColumns);
});
