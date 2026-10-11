<?php

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';

test('plugin installation compares requirements against the installed dependency version (#7968)', function ($required, $installed, $status, $expected) {
	$keys  = ['database_hostname', 'database_port', 'database_default', 'database_sessions', 'database_total_queries', 'config', 'database_log', 'database_last_error', 'affected_rows', 'error_logged'];
	$saved = [];

	foreach ($keys as $key) {
		$saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}
	$name      = 'dependency_test_' . bin2hex(random_bytes(6));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	mkdir($directory);

	try {
		$db = new FakeMySQLPDO();
		$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->exec('CREATE TABLE plugin_config (directory TEXT, version TEXT, status INTEGER)');

		if ($installed !== null) {
			$db->prepare('INSERT INTO plugin_config VALUES (?, ?, ?)')->execute(['dependency_fixture', $installed, $status]);
		}
		$GLOBALS['database_hostname']      = 'dependency-test';
		$GLOBALS['database_port']          = 0;
		$GLOBALS['database_default']       = 'dependency-test';
		$GLOBALS['database_sessions']      = ['dependency-test:0:dependency-test' => $db];
		$GLOBALS['database_total_queries'] = 0;
		file_put_contents($directory . '/INFO', "[info]\nrequires = \"$required\"\n");
		$message = '';
		expect(api_plugin_can_install($name, $message))->toBe($expected);
		expect($message === '')->toBe($expected);
	} finally {
		foreach ($saved as $key => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
		unlink($directory . '/INFO');
		rmdir($directory);
	}
})->with([
	['dependency_fixture:1.0.0', '1.2.0', 4, true],
	['dependency_fixture:1.2.0', '1.2.0', 4, true],
	['dependency_fixture:1.3.0', '1.2.0', 4, false],
	['dependency_fixture:>1.2.0', '1.2.0', 4, false],
	['dependency_fixture:>=1.2.0', '1.2.0', 1, true],
	['dependency_fixture', '1.2.0', 4, true],
	['dependency_fixture', null, 0, false],
	['dependency_fixture:1.0.0', '1.2.0', 0, false],
	['dependency_fixture:1.0.0', null, 0, false],
]);
