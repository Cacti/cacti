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
	$assert($permissions ? $grants > 0 : $grants === 0, $name . ': unexpected permission grants');
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

function plugin_realm_filename_setup($plugin, $file) {
	return api_plugin_register_realm($plugin, $file, 'Filename fixture', false);
}

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

	// Malformed schema definitions must leave an actionable diagnostic without
	// dumping the definition. Use the real logger, validators and DDL helpers.
	$logfile = cacti_log_file();
	$before = (string) file_get_contents($logfile);
	$invalid = ['columns' => [['name' => 'event', 'type' => 'invalid_type', 'default' => 'definition-marker']], 'type' => 'InnoDB'];
	$assert(db_table_create('pinstall_bad_schema', $invalid) === false, 'Malformed definition must fail');
	$assert(db_table_create('invalid table name', $invalid) === false, 'Invalid identifier must fail');
	api_plugin_db_table_create('pinstall_schema', 'pinstall_bad_schema', $invalid);
	api_plugin_db_table_create('pinstall_schema', 'invalid table name', $invalid);
	api_plugin_db_table_create('pinstall_a_null', 'pinstall_a_null_data', ['columns' => [], 'type' => 'invalid engine']);
	$logged = substr((string) file_get_contents($logfile), strlen($before));
	$assert(str_contains($logged, "Cannot create table 'pinstall_bad_schema': invalid table definition"), 'Missing schema validation diagnostic');
	$assert(str_contains($logged, 'Table creation rejected an invalid table identifier'), 'Missing identifier diagnostic');
	$assert(str_contains($logged, "Plugin 'pinstall_schema' could not create table 'pinstall_bad_schema'"), 'Missing plugin create diagnostic');
	$assert(str_contains($logged, 'Plugin table creation rejected an invalid table identifier'), 'Missing plugin identifier diagnostic');
	$assert(str_contains($logged, "Plugin 'pinstall_a_null' could not update table 'pinstall_a_null_data'"), 'Missing plugin update diagnostic');
	$assert(!str_contains($logged, 'definition-marker'), 'Diagnostic must not dump column defaults');
	$before = (string) file_get_contents($logfile);
	$assert(db_table_create('pinstall_quiet_schema', $invalid, false) === false, 'Quiet validation still fails');
	$assert((string) file_get_contents($logfile) === $before, 'Explicit log=false must remain quiet');
	$assert(!db_table_exists('pinstall_bad_schema', false), 'Malformed definition created a table');
	$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_db_changes WHERE plugin = ?', ['pinstall_schema']) === 0, 'Failed schema creation recorded ownership');

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
		} else {
			$grants_before = db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', [$name]);
			[$retry_code, $retry_output] = $cli([$name]);
			$assert($retry_code === 0 && str_contains($retry_output, 'already installed'), $name . ': idempotent permission retry');
			$assert(db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', [$name]) === $grants_before, $name . ': duplicate permission grants');
			$inspect($name, true, true, true, true);
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

	$admin_user = read_config_option('admin_user');
	try {
		set_config_option('admin_user', '9999999');
		$create('pinstall_admin', $cases['null']);
		[$code, $output] = $cli(['pinstall_admin']);
		$assert($code === 1 && str_contains($output, 'administrative account not found'), 'Invalid administrative account must report permission failure');
		$inspect('pinstall_admin', true, true, true, false);
	} finally {
		set_config_option('admin_user', $admin_user);
	}

	$create('pinstall_cfg', $cases['cfg']);
	[$code, $output] = $cli(['pinstall_cfg']);
	$assert($code === 1 && str_contains($output, 'needs configuration'), 'CLI must report a failed configuration check');
	$assert(!str_contains($output, 'Plugin pinstall_cfg enabled.') && !str_contains($output, 'permissions for'), 'Configuration failure must skip enable and permissions');
	$inspect('pinstall_cfg', true, false, false, false);

	$create('pinstall_flip', $cases['null']);
	$setup_file = $root . '/plugins/pinstall_flip/setup.php';
	file_put_contents($setup_file, str_replace('return true;', 'static $checks = 0; return ++$checks === 1;', file_get_contents($setup_file)));
	[$code, $output] = $cli(['pinstall_flip']);
	$assert($code === 1 && str_contains($output, 'could not be enabled'), 'CLI must report an enable-time configuration failure');
	$assert(!str_contains($output, 'Plugin pinstall_flip enabled.') && !str_contains($output, 'permissions for'), 'Enable failure must skip permission grants');
	$assert((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['pinstall_flip']) === 4, 'Failed enable must leave the plugin disabled');
	$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_realm AS uar INNER JOIN plugin_realms AS pr ON uar.realm_id = pr.id + 100 WHERE pr.plugin = ?', ['pinstall_flip']) === 0, 'Failed enable must not grant realms');

	$create('pinstall_error', $cases['null']);
	$directory = $root . '/plugins/pinstall_error';
	$outside = sys_get_temp_dir() . '/cacti_error_' . bin2hex(random_bytes(6));
	mkdir($directory . '/vendor/plugins/thold', 0700, true);
	mkdir($outside . '/plugins/thold', 0700, true);
	try {
		file_put_contents($directory . '/vendor/plugins/thold/Foo.php', '<?php');
		file_put_contents($outside . '/plugins/thold/Foo.php', '<?php');
		$assert(cacti_error_plugin($directory . '/setup.php') === 'pinstall_error', 'Actual plugin error attribution');
		$assert(cacti_error_plugin($directory . '/vendor/plugins/thold/Foo.php') === 'pinstall_error', 'Nested vendor directory must retain owning plugin');
		$assert(cacti_error_plugin($outside . '/plugins/thold/Foo.php') === '', 'Other application must not disable a Cacti plugin');
		$assert(cacti_error_plugin($root . '/lib/functions.php') === '', 'Core file must not be attributed to a plugin');
		$assert(cacti_error_plugin($directory . '/missing.php') === '', 'Missing file must not be attributed to a plugin');
		symlink($outside . '/plugins/thold', $directory . '/external');
		$assert(cacti_error_plugin($directory . '/external/Foo.php') === '', 'External symlink target must not be attributed to a plugin');
	} finally {
		if (is_link($directory . '/external')) {
			unlink($directory . '/external');
		}
		unlink($directory . '/vendor/plugins/thold/Foo.php');
		unlink($outside . '/plugins/thold/Foo.php');
		rmdir($directory . '/vendor/plugins/thold');
		rmdir($directory . '/vendor/plugins');
		rmdir($directory . '/vendor');
		rmdir($outside . '/plugins/thold');
		rmdir($outside . '/plugins');
		rmdir($outside);
	}

	set_error_handler(function ($level, $message) {
		throw new RuntimeException($message);
	});
	try {
		$assert(api_plugin_register_realm('pinstall_scope', 'index.php', 'Fixture', false) === false, 'File-scope realm registration must be rejected without warnings');
		$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_realms WHERE plugin = ?', ['pinstall_scope']) === 0, 'Rejected file-scope registration must not create a realm');
	} finally {
		restore_error_handler();
	}

	$create('pinstall_realm', $cases['null']);
	foreach (['index.php', 'quote"file.php', "quote'file.php", 'quote"file.php,other.php'] as $file) {
		plugin_realm_filename_setup('pinstall_realm', $file);
		$id = db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE plugin = ? AND file = ?', ['pinstall_realm', $file]);
		$assert($id !== false && $id !== null, 'Register quoted realm filename');
		$assert(plugin_realm_filename_setup('pinstall_realm', $file) !== false, 'Re-register quoted realm filename');
		$assert(db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE plugin = ? AND file = ?', ['pinstall_realm', $file]) === $id, 'Re-registering must retain realm ID');
		$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_realms WHERE plugin = ? AND file = ?', ['pinstall_realm', $file]) === 1, 'Re-registering must not duplicate realm');
	}

	foreach ([['foo_bar.php', 'other.php,fooXbar.php'], ['foo%bar.php', 'other.php,fooZZbar.php'], ['bang!file.php,third.php', 'other.php,bangfile.php']] as $index => [$file, $unrelated]) {
		$name = 'pinstall_w' . $index;
		$create($name, $cases['null']);
		db_execute_prepared('INSERT INTO plugin_realms (plugin, file, display) VALUES (?, ?, ?)', [$name, $unrelated, 'Unrelated']);
		db_execute_prepared('INSERT INTO plugin_realms (plugin, file, display) VALUES (?, ?, ?)', [$name, $file, 'Original']);
		$id = db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE plugin = ? AND file = ?', [$name, $file]);
		plugin_realm_filename_setup($name, $file);
		$assert((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_realms WHERE plugin = ?', [$name]) === 2, 'Literal filename must not merge unrelated realms');
		$assert(db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE plugin = ? AND file = ?', [$name, $file]) === $id, 'Literal filename must retain realm ID');
		$assert(db_fetch_cell_prepared('SELECT display FROM plugin_realms WHERE plugin = ? AND file = ?', [$name, $unrelated]) === 'Unrelated', 'Unrelated realm must remain unchanged');
		$assert(db_fetch_cell_prepared('SELECT display FROM plugin_realms WHERE plugin = ? AND file = ?', [$name, $file]) === 'Filename fixture', 'Target realm must receive its new caption');
	}

	print json_encode(['assertions' => $assertions, 'result' => 'passed']) . PHP_EOL;
} finally {
	foreach ($fixtures as $name) {
		api_plugin_uninstall($name);
		unlink($root . '/plugins/' . $name . '/setup.php');
		unlink($root . '/plugins/' . $name . '/INFO');
		rmdir($root . '/plugins/' . $name);
	}
}
