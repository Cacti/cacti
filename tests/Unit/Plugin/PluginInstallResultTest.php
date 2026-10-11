<?php

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';

test('failed install hooks stop setup and disable hooks while retaining recovery metadata (#8002)', function ($body, $expected) {
	$keys  = ['database_hostname', 'database_port', 'database_default', 'database_sessions', 'database_total_queries', 'config', 'database_log', 'database_last_error', 'affected_rows', 'error_logged', '_SESSION'];
	$saved = [];

	foreach ($keys as $key) {
		$saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}
	$name      = 'cfg_' . bin2hex(random_bytes(5));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	mkdir($directory);

	try {
		$db = new class extends FakeMySQLPDO {
			public function prepare(string $query, array $options = []): PDOStatement|false {
				return parent::prepare(str_replace('NOW()', 'CURRENT_TIMESTAMP', $query), $options);
			}
		};
		$db->exec('CREATE TABLE user_auth (id INTEGER, username TEXT)');
		$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
		$db->exec('CREATE TABLE plugin_config (id INTEGER PRIMARY KEY, directory TEXT, name TEXT, author TEXT, webpage TEXT, version TEXT, last_updated TEXT, status INTEGER DEFAULT 0)');
		$db->exec('CREATE TABLE plugin_hooks (name TEXT, status INTEGER)');
		$db->exec('CREATE TABLE poller (id INTEGER, last_status TEXT, disabled TEXT)');
		$db->prepare('INSERT INTO plugin_hooks VALUES (?, 1)')->execute([$name]);
		$GLOBALS['database_hostname']      = 'config-lifecycle-test';
		$GLOBALS['database_port']          = 0;
		$GLOBALS['database_default']       = 'config-lifecycle-test';
		$GLOBALS['database_sessions']      = ['config-lifecycle-test:0:config-lifecycle-test' => $db];
		$GLOBALS['database_total_queries'] = 0;
		$_SESSION[SESS_MESSAGES]           = [];
		file_put_contents($directory . '/INFO', "[info]\nname = $name\nversion = 1.0\n");
		$check = $expected ? 'return true;' : 'throw new RuntimeException("Configuration must not run after failed installation");';
		file_put_contents($directory . '/setup.php', '<?php function plugin_' . $name . '_version() { return ["longname" => "Configuration fixture", "author" => "Cacti tests", "version" => "1.0"]; } function plugin_' . $name . '_install() {' . $body . '} function plugin_' . $name . '_check_config() {' . $check . '}');

		expect(api_plugin_install($name))->toBe($expected);
		expect((int) $db->query('SELECT status FROM plugin_config')->fetchColumn())->toBe($expected ? 4 : 2);
		expect((int) $db->query('SELECT status FROM plugin_hooks')->fetchColumn())->toBe($expected ? 1 : 0);

		if (!$expected) {
			expect($_SESSION[SESS_MESSAGES]['install_error']['message'])->toContain($name);
			expect($_SESSION[SESS_MESSAGES]['install_error']['level'])->toBe(MESSAGE_LEVEL_ERROR);
		}
	} finally {
		foreach ($saved as $key => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
		unlink($directory . '/setup.php');
		unlink($directory . '/INFO');
		rmdir($directory);
	}
})->with([
	['return false;', false],
	['', true],
	['return true;', true],
	['throw new RuntimeException("Fixture install error");', false],
	['throw new Error("Fixture install error");', false],
]);
