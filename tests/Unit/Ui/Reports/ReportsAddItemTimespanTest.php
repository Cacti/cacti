<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * reports_add_devices() and reports_add_graphs() receive the scalar
 * timespan that host.php, graphs.php and aggregate_graphs.php read from the
 * request, and store it in the integer reports_items.timespan column.
 *
 * The functions run in a child process against database stubs that record
 * the INSERT, so the stubs do not leak into the rest of the suite.
 */

function _reports_add_item(string $kind) : array {
	$stub = <<<'PHP'
<?php
define('SESS_USER_ID', 'sess_user_id');
define('MESSAGE_LEVEL_INFO', 1);
define('MESSAGE_LEVEL_WARN', 2);
define('MESSAGE_LEVEL_ERROR', 3);

$_SESSION = [SESS_USER_ID => 4];
$GLOBALS['inserts'] = [];

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function __($text, ...$args) {
	return vsprintf($text, $args);
}

function __esc($text, ...$args) {
	return vsprintf($text, $args);
}

function raise_message($id, $message = '', $level = 0) {
}

function db_fetch_cell_prepared($sql, $params = []) {
	if (str_contains($sql, 'SELECT user_id')) {
		return 4;
	}

	if (str_contains($sql, 'FROM host') && str_contains($sql, 'SELECT id')) {
		return $params[0];
	}

	if (str_contains($sql, 'FROM reports_items') && str_contains($sql, 'SELECT id')) {
		return false;
	}

	return 0;
}

function db_fetch_row_prepared($sql, $params = []) {
	return ['id' => $params[0], 'host_id' => 3, 'graph_template_id' => 2];
}

function db_execute_prepared($sql, $params = []) {
	$GLOBALS['inserts'][] = $params;

	return true;
}

require $argv[1];

$result = $argv[2] === 'graphs'
	? reports_add_graphs(1, 10, 7, 'center')
	: reports_add_devices(1, [5], 7, 'left');

print json_encode(['result' => $result, 'inserts' => $GLOBALS['inserts']]);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_ri_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/reports.php') . ' ' .
		escapeshellarg($kind) . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'adding the report item must not error: ' . implode("\n", $output));

	return json_decode(implode("\n", $output), true);
}

test('reports_add_graphs stores the integer timespan of a new graph item', function () {
	$out = _reports_add_item('graphs');

	expect($out['result'])->toBeTrue()
		->and($out['inserts'])->toHaveCount(1)
		->and($out['inserts'][0])->toBe([1, 0, 3, 2, 10, 7, 'center', 1]);
});

test('reports_add_devices stores the integer timespan of each new device item', function () {
	$out = _reports_add_item('devices');

	expect($out['result'])->toBeTrue()
		->and($out['inserts'])->toHaveCount(1)
		->and($out['inserts'][0])->toContain(7)
		->and($out['inserts'][0])->toContain('left');
});
