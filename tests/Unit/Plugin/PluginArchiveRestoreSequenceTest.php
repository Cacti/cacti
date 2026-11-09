<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * api_plugin_archive_restore() must read each archive through its own
 * phar:// path.  A fixed phar alias stays bound to the first archive loaded
 * in a process, so a second restore in the same request would write the
 * first archive's files.
 *
 * Both restores run in one child process against database stubs that serve
 * two archives, so the stubs do not leak into the rest of the suite.
 */

function _plugin_archive_fixture(string $directory, string $payload) : string {
	$tar = $directory . '/fixture_' . md5($payload) . '.tar';

	$archive = new PharData($tar);
	$archive->addFromString('payload.txt', $payload);
	$archive->compress(Phar::GZ);

	unset($archive);

	$data = base64_encode((string) file_get_contents($tar . '.gz'));

	unlink($tar);
	unlink($tar . '.gz');

	return $data;
}

test('a second restore in the same process writes its own archive', function () {
	$root = sys_get_temp_dir() . '/cacti_pa_' . getmypid() . '_' . uniqid();
	mkdir($root . '/plugins/fixture', 0755, true);

	$archives = $root . '/archives.json';
	file_put_contents($archives, json_encode([
		'1' => _plugin_archive_fixture($root, 'first payload'),
		'2' => _plugin_archive_fixture($root, 'second payload'),
	]));

	$stub = <<<'PHP'
<?php
define('CACTI_PATH_BASE', $argv[2]);
define('CACTI_PATH_PLUGINS', $argv[2] . '/plugins');
define('MESSAGE_LEVEL_INFO', 1);
define('MESSAGE_LEVEL_ERROR', 3);

$GLOBALS['archives'] = json_decode(file_get_contents($argv[3]), true);

function __esc($text, ...$args) {
	return vsprintf($text, $args);
}

function raise_message($id, $message = '', $level = 0) {
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
}

function validate_relative_path_within($path, $base_dir) {
	return str_contains($path, '..') ? false : $path;
}

function db_fetch_cell_prepared($sql, $params = []) {
	return str_contains($sql, 'SELECT archive') ? $GLOBALS['archives'][$params[1]] : '2026-01-01 00:00:00';
}

function db_execute_prepared($sql, $params = []) {
	return true;
}

require $argv[1];

$results = [];

foreach (['1', '2'] as $id) {
	$results[] = api_plugin_archive_restore('fixture', $id);
	$results[] = file_get_contents(CACTI_PATH_PLUGINS . '/fixture/payload.txt');
}

print json_encode($results);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_pr_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 3) . '/lib/plugins.php') . ' ' .
		escapeshellarg($root) . ' ' .
		escapeshellarg($archives) . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);
	unlink($archives);

	if (is_file($root . '/plugins/fixture/payload.txt')) {
		unlink($root . '/plugins/fixture/payload.txt');
	}

	rmdir($root . '/plugins/fixture');
	rmdir($root . '/plugins');
	rmdir($root);

	expect($status)->toBe(0, 'restoring must not error: ' . implode("\n", $output));

	expect(json_decode(implode("\n", $output), true))->toBe([true, 'first payload', true, 'second payload']);
});
