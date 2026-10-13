<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software under the GNU General Public License v2  |
 | or later.                                                             |
 +-------------------------------------------------------------------------+
*/

// Exercises the production CLI and database helpers in a disposable install.
if (getenv('CACTI_PLUGIN_GRANT_TEST') !== '1') {
	fwrite(STDERR, "Set CACTI_PLUGIN_GRANT_TEST=1 for the disposable database test.\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
require $root . '/include/config.php';

if ($database_default !== 'cacti_plugin_grant_test') {
	fwrite(STDERR, "This fixture requires the disposable database cacti_plugin_grant_test.\n");
	exit(1);
}
require $root . '/include/cli_check.php';

$assertions = 0;
$assert     = function ($condition, $message) use (&$assertions) {
	$assertions++;

	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$fixtures = [];
$admin    = read_config_option('admin_user');
$cli      = function ($names, $enable = false) use ($root) {
	$command = [PHP_BINARY, '-d', 'auto_prepend_file=', $root . '/cli/plugin_manage.php', '--install', '--allperms'];

	if ($enable) {
		$command[] = '--enable';
	}

	foreach ($names as $name) {
		$command[] = '--plugin=' . $name;
	}
	$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [proc_close($process), $output];
};
$grants = function ($name) {
	return (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm AS uar
		INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', [$name]);
};

try {
	foreach (['pgrant_one', 'pgrant_two', 'pgrant_empty'] as $name) {
		$directory = $root . '/plugins/' . $name;

		if (file_exists($directory) || db_fetch_cell_prepared('SELECT id FROM plugin_config WHERE directory = ?', [$name])) {
			throw new RuntimeException('Refusing to overwrite existing fixture: ' . $name);
		}
		mkdir($directory);
		$fixtures[] = $name;
		file_put_contents($directory . '/INFO', "[info]\nname = $name\nversion = 1.0\nlongname = Grant fixture\nauthor = Cacti tests\n");
		$registration = $name === 'pgrant_empty' ? '' : "api_plugin_register_realm('$name', 'index.php', 'Grant fixture', false);";
		file_put_contents($directory . '/setup.php', '<?php function plugin_' . $name . '_version() { return ["longname" => "Grant fixture", "author" => "Cacti tests", "version" => "1.0"]; } function plugin_' . $name . '_install() {' . $registration . '} function plugin_' . $name . '_check_config() { return true; }');
		api_plugin_install($name);

		if ($name !== 'pgrant_empty') {
			$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_realms WHERE plugin = ?', [$name]) === 1, 'Install hook must register an existing realm');
			$assert($grants($name) === 0, 'Realm registration must not implicitly grant permissions');
		}
	}

	[$code, $output] = $cli(['pgrant_one', 'pgrant_two']);
	$assert($code === 0, 'Batch grant exit status: ' . $output);

	foreach (['pgrant_one', 'pgrant_two'] as $name) {
		$assert($grants($name) === 1, 'Existing realm must be granted once');
		$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm AS uar
			INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ? AND uar.user_id = ?', [$name, $admin]) === 1, 'Grant must belong to configured administrator');
	}
	[$code, $output] = $cli(['pgrant_one']);
	$assert($code === 0 && $grants('pgrant_one') === 1, 'Repeated grants must be idempotent: ' . $output);

	foreach (['pgrant_bad' => null, 'pgrant_cfg' => 'return false;', 'pgrant_flip' => 'static $checks = 0; return ++$checks === 1;'] as $name => $check) {
		$directory = $root . '/plugins/' . $name;

		if (file_exists($directory)) {
			throw new RuntimeException('Fixture collision: ' . $name);
		}
		mkdir($directory);
		$fixtures[] = $name;
		file_put_contents($directory . '/INFO', "[info]\nname = $name\nversion = 1.0\n");
		$install = $check === null ? '' : "function plugin_{$name}_install() { api_plugin_register_realm('$name', 'index.php', 'Grant fixture', false); }";
		file_put_contents($directory . '/setup.php', '<?php function plugin_' . $name . '_version() { return ["longname" => "Grant fixture", "author" => "Cacti tests", "version" => "1.0"]; } ' . $install . ' function plugin_' . $name . '_check_config() {' . ($check ?? 'return true;') . '}');
		[$code, $output] = $cli([$name], true);
		$message         = $name === 'pgrant_bad' ? 'installation failed' : ($name === 'pgrant_cfg' ? 'needs configuration' : 'could not be enabled');
		$assert($code === 1 && str_contains($output, $message), 'CLI must report failure: ' . $output);
		$assert(!str_contains($output, "Plugin $name enabled.") && !str_contains($output, 'permissions for'), 'Failure must skip automatic grants');
		$assert($grants($name) === 0, 'Failed plugin must have no grants');
	}

	// Retrying an existing plugin in configuration-issue status must not grant it.
	db_execute_prepared('UPDATE plugin_config SET status = 2 WHERE directory = ?', ['pgrant_one']);
	db_execute_prepared('DELETE uar FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', ['pgrant_one']);
	[$code, $output] = $cli(['pgrant_one']);
	$assert($code === 1 && str_contains($output, 'needs configuration'), 'Retry must report configuration failure');
	$assert($grants('pgrant_one') === 0, 'Retry must not grant a plugin needing configuration');
	db_execute_prepared('UPDATE plugin_config SET status = 4 WHERE directory = ?', ['pgrant_one']);

	db_execute_prepared('DELETE uar FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', ['pgrant_one']);
	set_config_option('admin_user', '9999999');
	[$code, $output] = $cli(['pgrant_one', 'pgrant_empty']);
	$assert($code === 1, 'Permission failure must produce a nonzero batch exit');
	$assert(str_contains($output, 'administrative account not found'), 'Failure must explain missing administrator');
	$assert(str_contains($output, "Plugin 'pgrant_empty' processing started"), 'Batch must continue after a permission failure');
	$assert($grants('pgrant_one') === 0, 'Invalid administrator must receive no grants');
	[$code, $output] = $cli(['pgrant_empty']);
	$assert($code === 0, 'A plugin without realms needs no administrator grant: ' . $output);

	print json_encode(['assertions' => $assertions, 'result' => 'passed']) . PHP_EOL;
} finally {
	set_config_option('admin_user', $admin);

	foreach ($fixtures as $name) {
		db_execute_prepared('DELETE uar FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', [$name]);
		db_execute_prepared('DELETE FROM plugin_realms WHERE plugin = ?', [$name]);
		db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', [$name]);
		unlink($root . '/plugins/' . $name . '/setup.php');
		unlink($root . '/plugins/' . $name . '/INFO');
		rmdir($root . '/plugins/' . $name);
	}
}
