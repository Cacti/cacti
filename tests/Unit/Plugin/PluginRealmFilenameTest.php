<?php

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';

// MySQL reports SELECT row counts; SQLite requires buffering to provide the
// same contract to Cacti's production db_fetch_assoc_return() helper.
class PluginRealmFilenameStatement extends PDOStatement {
	private ?array $rows = null;

	protected function __construct() {
	}

	public function execute(?array $params = null): bool {
		$result     = parent::execute($params);
		$this->rows = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;

		return $result;
	}

	public function rowCount(): int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
		return $this->rows ?? parent::fetchAll($mode, ...$args);
	}
}

function plugin_realm_filename_setup(string $plugin, string $file): bool {
	return api_plugin_register_realm($plugin, $file, 'Filename fixture', false);
}

test('plugin realm registration retains quoted filenames and existing realm IDs (#7968)', function ($file) {
	$keys  = ['database_hostname', 'database_port', 'database_default', 'database_sessions', 'database_total_queries', 'config', 'database_log', 'database_last_error', 'affected_rows', 'error_logged'];
	$saved = [];

	foreach ($keys as $key) {
		$saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}

	try {
		$db = new FakeMySQLPDO();
		$db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PluginRealmFilenameStatement::class, []]);
		$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
		$db->exec('CREATE TABLE plugin_realms (id INTEGER PRIMARY KEY, plugin TEXT, file TEXT, display TEXT)');
		$db->exec('CREATE TABLE poller (id INTEGER, last_status TEXT, disabled TEXT)');
		$db->prepare('INSERT INTO plugin_realms VALUES (7, ?, ?, ?)')->execute(['realm_fixture', $file, 'Old caption']);
		$GLOBALS['database_hostname']      = 'realm-filename-test';
		$GLOBALS['database_port']          = 0;
		$GLOBALS['database_default']       = 'realm-filename-test';
		$GLOBALS['database_sessions']      = ['realm-filename-test:0:realm-filename-test' => $db];
		$GLOBALS['database_total_queries'] = 0;
		expect(plugin_realm_filename_setup('realm_fixture', $file))->toBeTrue();
		$realms = $db->query('SELECT id, file, display FROM plugin_realms')->fetchAll(PDO::FETCH_ASSOC);
		expect($realms)->toHaveCount(1);
		expect((int) $realms[0]['id'])->toBe(7);
		expect($realms[0]['file'])->toBe($file);
		expect($realms[0]['display'])->toBe('Filename fixture');
	} finally {
		foreach ($saved as $key => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
	}
})->with([
	'index.php',
	'quote"file.php',
	"quote'file.php",
	'quote"file.php,other.php',
]);
