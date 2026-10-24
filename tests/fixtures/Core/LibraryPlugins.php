<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_plugins_setup(): void {
	library_expect(api_plugin_register_hook('coveragefixture', 'coveragefixture_notify', 'coveragefixture_notify', 'setup.php', true), true, 'Plugin registers a notification hook');
	library_expect(api_plugin_register_hook('coveragefixture', 'coveragefixture_transform', 'coveragefixture_transform', 'setup.php', true), true, 'Plugin registers a transforming hook');
}

function library_plugins(): void {
	$directory = CACTI_PATH_PLUGINS . '/coveragefixture';
	library_expect(file_exists($directory), false, 'Plugin fixture never replaces an installed plugin');
	library_expect(mkdir($directory, 0700), true, 'Fixture owns its plugin directory');
	$cwd = getcwd();

	try {
		library_expect(copy(__DIR__ . '/PluginProducer.php', $directory . '/setup.php'), true, 'Fixture installs its real plugin entry point');
		library_expect(file_put_contents($directory . '/payload.txt', 'original plugin payload'), 23, 'Archive fixture has a known payload');
		library_expect(db_execute_prepared('INSERT INTO plugin_config (directory, name, author, version, status) VALUES (?, ?, ?, ?, ?)', ['coveragefixture', 'Disposable lifecycle fixture', 'Cacti tests', '1.0.0', 1]), true, 'Fixture seeds an installed plugin');
		library_plugins_setup();
		library_expect(function_exists('coveragefixture_notify'), false, 'Hook entry point has not been eagerly loaded');
		$GLOBALS['coveragefixture_calls'] = [];
		api_plugin_hook('coveragefixture_notify', ['id' => 17, 'label' => 'Fixture']);
		library_expect($GLOBALS['coveragefixture_calls'], [['coveragefixture_notify', ['id' => 17, 'label' => 'Fixture']]], 'Lazy-loaded notification receives the complete argument list once');
		library_expect(api_plugin_hook_function('coveragefixture_transform', ['id' => 17]), ['id' => 17, 'plugin' => 'coveragefixture'], 'Transform hook preserves and extends the caller payload');
		api_plugin_disable('coveragefixture');
		library_expect((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['coveragefixture']), 4, 'Disable persists the disabled status');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND status = 1', ['coveragefixture']), 0, 'Disable removes active hooks');
		$GLOBALS['coveragefixture_ready'] = false;
		api_plugin_enable('coveragefixture');
		library_expect((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['coveragefixture']), 4, 'Failed configuration preserves the disabled state');
		$GLOBALS['coveragefixture_ready'] = true;
		api_plugin_enable('coveragefixture');
		library_expect((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['coveragefixture']), 1, 'Valid configuration enables the plugin');
		library_expect(api_plugin_archive_restore('coveragefixture', '-1'), false, 'A missing archive cannot report restoration');
		api_plugin_archive('coveragefixture', 'Fixture backup');
		$archive = db_fetch_row_prepared('SELECT id, archive FROM plugin_archive WHERE plugin = ? ORDER BY id DESC LIMIT 1', ['coveragefixture']);
		library_expect(!empty($archive['archive']), true, 'Plugin backup persists a real archive');
		file_put_contents($directory . '/payload.txt', 'changed plugin payload');
		library_expect(api_plugin_archive_restore('coveragefixture', (string) $archive['id']), true, 'Plugin archive restores successfully');
		library_expect(file_get_contents($directory . '/payload.txt'), 'original plugin payload', 'Restore recovers the original bytes');
		api_plugin_archive_remove('coveragefixture', (string) $archive['id']);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_archive WHERE plugin = ?', ['coveragefixture']), 0, 'Archive removal deletes only the owned backup');

		foreach ([false, 'throw', null] as $outcome) {
			$GLOBALS['coveragefixture_install'] = $outcome;
			library_expect(api_plugin_install('coveragefixture'), $outcome === null, 'Install reports the hook outcome including legacy void success');
			library_expect((int) db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', ['coveragefixture']), $outcome === null ? 4 : 2, 'Install status matches its outcome');

			if ($outcome !== null) {
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND status = 1', ['coveragefixture']), 0, 'Failed installation disables every hook');
			}
		}
		library_plugin_install_errors();
		set_config_option('coveragefixture_owned_data', 'owned');
		api_plugin_remove_data('coveragefixture');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM settings WHERE name = ?', ['coveragefixture_owned_data']), 0, 'Plugin data removal invokes its actual cleanup hook');
		library_expect($_SESSION[SESS_MESSAGES]['rmdata_complete']['level'] ?? null, MESSAGE_LEVEL_INFO, 'Completed plugin data removal reaches the operator');
		api_plugin_uninstall('coveragefixture');
		library_expect($GLOBALS['coveragefixture_uninstalled'] ?? false, true, 'Uninstall calls the plugin lifecycle hook');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_config WHERE directory = ?', ['coveragefixture']), 0, 'Uninstall removes the plugin configuration');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ?', ['coveragefixture']), 0, 'Uninstall removes its hook registrations');
	} finally {
		chdir($cwd);
		api_plugin_uninstall('coveragefixture');
		db_execute_prepared('DELETE FROM plugin_archive WHERE plugin = ?', ['coveragefixture']);
		db_execute_prepared('DELETE FROM settings WHERE name = ?', ['coveragefixture_owned_data']);
		unset($GLOBALS['config'][OPTIONS_CLI]['coveragefixture_owned_data']);

		foreach (glob($directory . '/*') as $owned) {
			unlink($owned);
		}
		rmdir($directory);
	}
}

function library_plugin_install_errors(): void {
	foreach (['plugin_cov_' => 'directory_error', 'cov_version_' => 'version_error', 'cov_install_' => 'install_error'] as $prefix => $message) {
		$name = $prefix . bin2hex(random_bytes(5));
		$path = CACTI_PATH_PLUGINS . '/' . $name;
		library_expect(mkdir($path, 0700), true, 'Invalid plugin fixture owns its directory');
		$code = '<?php';

		if ($message === 'install_error') {
			$code .= ' function plugin_' . $name . '_version() { return ["longname" => "Incomplete fixture", "author" => "Cacti tests", "version" => "1.0"]; }';
		}

		try {
			library_expect(file_put_contents($path . '/setup.php', $code), strlen($code), 'Incomplete plugin entry point is written completely');
			unset($_SESSION[SESS_MESSAGES][$message]);
			library_expect(api_plugin_install($name), false, 'Incomplete plugin cannot report successful installation');
			library_expect($_SESSION[SESS_MESSAGES][$message]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Installation explains the missing plugin contract');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_config WHERE directory = ? AND status = 1', [$name]), 0, 'Failed installation never activates its plugin');
			api_plugin_remove_data($name);
			library_expect($_SESSION[SESS_MESSAGES]['rmdata_not_complete']['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Missing cleanup hook cannot report successful data removal');
		} finally {
			db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', [$name]);
			unlink($path . '/setup.php');
			rmdir($path);
		}
	}
	library_expect(api_plugin_status_run('unsupported_fixture_hook', [], ''), false, 'An unsupported remote hook cannot run without capabilities');
}
