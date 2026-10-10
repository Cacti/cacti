<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software under the GNU General Public License v2  |
 | or later.                                                             |
 +-------------------------------------------------------------------------+
*/

// Run only in an explicitly configured disposable Cacti installation.
// php -d auto_prepend_file= tests/integration/fixtures/plugin_install_lifecycle.php
if (getenv('CACTI_PLUGIN_INSTALL_TEST') !== '1') {
	fwrite(STDERR, "Set CACTI_PLUGIN_INSTALL_TEST=1 for the disposable database test.\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
require $root . '/include/config.php';
if ($database_default !== 'cacti_plugin_test') {
	fwrite(STDERR, "This fixture requires the disposable database cacti_plugin_test.\n");
	exit(1);
}

require $root . '/include/cli_check.php';

$assertions = 0;
$assert = function ($condition, $message) use (&$assertions) {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$fixtures = [];
$cases = [
	'false' => ['return false;', true, false],
	'null' => ['', true, true],
	'true' => ['return true;', true, true],
	'throw' => ['throw new RuntimeException("Recoverable fixture failure");', true, false],
	'error' => ['throw new Error("Fixture error");', true, false],
	'cfg' => ['', false, true]
];
$template = file_get_contents(__DIR__ . '/plugin_install_setup.php');

$create = function ($name, $case) use ($root, $template, &$fixtures) {
	$directory = $root . '/plugins/' . $name;
	if (file_exists($directory)) {
		throw new RuntimeException('Refusing to overwrite an existing fixture directory: ' . $name);
	}
	mkdir($directory);
	$fixtures[] = $name;
	file_put_contents($directory . '/setup.php', str_replace(
		['__PLUGIN__', '__OUTCOME__', '__READY__'],
		[$name, $case[0], $case[1] ? 'true' : 'false'], $template
	));
	file_put_contents($directory . '/INFO', "[info]\nname = $name\nversion = 1.0\nlongname = Install fixture\nauthor = Cacti tests\n");
};

$inspect = function ($name, $success, $ready, $enabled, $permissions) use ($assert) {
	$status = (int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', [$name]);
	$assert($status === (!$success || !$ready ? 2 : ($enabled ? 1 : 4)), $name . ': unexpected plugin status');
	$events = array_column(db_fetch_assoc('SELECT event FROM `' . $name . '_data`'), 'event');
	$expected = $success ? ['install', 'check_config'] : ['install'];
	if ($enabled) {
		$expected[] = 'check_config';
	}
	$assert($events === $expected, $name . ': lifecycle ordering');
	$hooks = db_fetch_assoc_prepared('SELECT hook, status FROM plugin_hooks WHERE name = ?', [$name]);
	$assert(count($hooks) === 2, $name . ': hook registration');
	foreach ($hooks as $hook) {
		$assert($success || (int) $hook['status'] === 0, $name . ': failed install left a hook enabled');
	}
	$realm = db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE plugin = ?', [$name]);
	$assert($realm !== false && $realm !== null, $name . ': realm registration');
	$grants = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm WHERE realm_id = ?', [(int) $realm + 100]);
	if (!$permissions) {
		$assert($grants === 0, $name . ': failed install granted permissions');
	}
	$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_db_changes WHERE plugin = ? AND method = \'create\'', [$name]) === 1, $name . ': table ownership retained');
};

$cli = function ($names) use ($root) {
	$command = [PHP_BINARY, '-d', 'auto_prepend_file=', $root . '/cli/plugin_manage.php', '--install', '--enable', '--allperms'];
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

try {
	foreach ($cases as $outcome => $case) {
		$name = 'pinstall_a_' . $outcome;
		$create($name, $case);
		unset($_SESSION['sess_messages']['install_error']);
		$assert(api_plugin_install($name) === $case[2], $name . ': API result');
		$inspect($name, $case[2], $case[1], false, false);
		if (!$case[2]) {
			$assert(isset($_SESSION['sess_messages']['install_error']), $name . ': web error message missing');
		}
	}

	foreach (['false', 'null', 'true', 'throw', 'error'] as $outcome) {
		$name = 'pinstall_c_' . $outcome;
		$case = $cases[$outcome];
		$create($name, $case);
		[$code, $output] = $cli([$name]);
		$assert($code === ($case[2] ? 0 : 1), $name . ': CLI exit status: ' . $output);
		$assert(str_contains($output, $case[2] ? 'installed successfully' : 'installation failed'), $name . ': CLI result message');
		$assert(str_contains($output, 'permissions for') === $case[2], $name . ': automatic permissions step');
		if (!$case[2]) {
			$assert(!str_contains($output, 'installed successfully') && !str_contains($output, 'permissions for'), $name . ': false success');
		}
		$inspect($name, $case[2], true, $case[2], $case[2]);
		if (!$case[2]) {
			[$retry_code, $retry_output] = $cli([$name]);
			$assert($retry_code === 1 && str_contains($retry_output, 'needs configuration'), $name . ': retry must not report success');
			$inspect($name, false, true, false, false);
		}
	}

	$create('pinstall_b_false', $cases['false']);
	$create('pinstall_b_null', $cases['null']);
	[$code, $output] = $cli(['pinstall_b_false', 'pinstall_b_null']);
	$assert($code === 1, 'Batch must report a failure');
	$assert(str_contains($output, 'Plugin pinstall_b_null installed successfully'), 'Batch must continue after failure');
	$inspect('pinstall_b_false', false, true, false, false);
	$inspect('pinstall_b_null', true, true, true, true);

	[$code, $output] = $cli(['pinstall_missing']);
	$assert($code === 1 && str_contains($output, 'missing plugin directory'), 'Missing directory must report failure');

	print json_encode(['assertions' => $assertions, 'result' => 'passed']) . PHP_EOL;
} finally {
	foreach ($fixtures as $name) {
		api_plugin_uninstall($name);
		unlink($root . '/plugins/' . $name . '/setup.php');
		unlink($root . '/plugins/' . $name . '/INFO');
		rmdir($root . '/plugins/' . $name);
	}
}
