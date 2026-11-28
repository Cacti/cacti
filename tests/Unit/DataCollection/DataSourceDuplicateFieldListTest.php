<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * api_data_source_duplicate() must not copy the source's RRD path, but the
 * global $struct_data_source it walks is shared with every later form,
 * export, import and template push in the same request, so skipping the
 * field must not remove it from that shared definition.
 *
 * The function runs in a child process against database stubs that record
 * each save, so the stubs do not leak into the rest of the suite.
 */

test('duplicating a data template skips the RRD path without changing the shared field list', function () {
	$stub = <<<'PHP'
<?php
$struct_data_source = [
	'name'             => ['flags' => ''],
	'data_source_path' => ['flags' => ''],
];
$struct_data_source_item = [];
$GLOBALS['saves']        = [];

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'FROM data_template_data')) {
		return ['id' => 9, 'name' => 'source', 't_name' => '', 'name_cache' => 'source',
			'data_source_path' => '<path_rra>/source.rrd', 't_data_source_path' => ''];
	}

	return ['id' => 3, 'name' => 'Template'];
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return [];
}

function get_hash_data_template($id, $type = 'data_template') {
	return 'hash';
}

function sql_save($save, $table) {
	$GLOBALS['saves'][$table] = $save;

	return 20;
}

require $argv[1];

api_data_source_duplicate(0, 3, '<template_title> copy');

print json_encode([
	'fields' => array_keys($struct_data_source),
	'saved'  => array_keys($GLOBALS['saves']['data_template_data']),
]);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_dd_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 3) . '/lib/api_data_source.php') . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'duplicating must not error: ' . implode("\n", $output));

	$result = json_decode(implode("\n", $output), true);

	expect($result['fields'])->toBe(['name', 'data_source_path'])
		->and($result['saved'])->toContain('name')
		->and($result['saved'])->not->toContain('data_source_path');
});
