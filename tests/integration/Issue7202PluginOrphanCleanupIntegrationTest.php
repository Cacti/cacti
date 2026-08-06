<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for issue #7202. A plugin directory can disappear
 * without its uninstall ever running, and every row keyed on that directory
 * then outlives it. The realm rows are the ones that matter: they carry user
 * and group grants, so a later plugin issued the same realm id inherits
 * whoever was granted the old one.
 *
 * Two of the three statements are MySQL multi-table deletes, which sqlite
 * cannot prepare at all. Those are re-expressed here as the equivalent NOT IN
 * so the function can run end to end, and the shipped text is asserted
 * separately by the structural test below. The realm cleanup, which is the half
 * with the grants in it, runs exactly as shipped.
 */

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';

foreach (['CACTI_WEB' => false, 'POLLER_ID' => 1, 'CACTI_PATH_LOG' => sys_get_temp_dir()] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

require_once dirname(__DIR__, 2) . '/lib/plugins.php';

/**
 * A FakeMySQLPDO that re-expresses the two multi-table deletes. The rewrite is
 * deliberately narrow: it matches only these two statements, so an unrelated
 * change to either would stop being translated rather than be silently mangled.
 */
class PluginCleanupPDO extends FakeMySQLPDO {
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PluginCleanupStatement::class, []]);
	}

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		return parent::prepare($this->rewrite($query), $options);
	}

	/**
	 * @param string $sql The statement to rewrite.
	 *
	 * @return string The sqlite equivalent of a MySQL multi-table delete.
	 */
	private function rewrite(string $sql) : string {
		if (str_contains($sql, 'DELETE ph')) {
			return 'DELETE FROM plugin_hooks
				WHERE name NOT IN (SELECT directory FROM plugin_config)
				AND name != ?';
		}

		if (str_contains($sql, 'DELETE pd')) {
			return 'DELETE FROM plugin_db_changes
				WHERE plugin NOT IN (SELECT directory FROM plugin_config)
				AND plugin != ?';
		}

		return $sql;
	}
}

/**
 * sqlite reports rowCount() as 0 for SELECT, which db_fetch_assoc_return()
 * never sees rows through.
 */
class PluginCleanupStatement extends PDOStatement {
	/** @var array<int,array<string,mixed>>|null rows of a SELECT, null otherwise */
	private ?array $rows = null;

	protected function __construct() {
	}

	public function execute(?array $params = null) : bool {
		$executed = parent::execute($params);

		$this->rows = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;

		return $executed;
	}

	public function rowCount() : int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args) : array {
		return $this->rows ?? parent::fetchAll($mode, ...$args);
	}
}

/**
 * Seeds one installed plugin, one that has vanished, and the internal
 * pseudo-plugin, each with rows in every table the cleanup touches.
 *
 * @return PluginCleanupPDO A connection holding the seeded schema.
 */
function plugin_cleanup_seed() : PluginCleanupPDO {
	$conn = new PluginCleanupPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

	$conn->exec('CREATE TABLE plugin_config (id INTEGER PRIMARY KEY, directory TEXT)');
	$conn->exec("INSERT INTO plugin_config VALUES (1, 'thold')");

	$conn->exec('CREATE TABLE plugin_hooks (id INTEGER PRIMARY KEY, name TEXT, hook TEXT, status INTEGER)');
	$conn->exec("INSERT INTO plugin_hooks (name, hook, status) VALUES
		('thold', 'poller_bottom', 1), ('gone', 'poller_bottom', 1), ('internal', 'poller_bottom', 1)");

	$conn->exec('CREATE TABLE plugin_db_changes (id INTEGER PRIMARY KEY, plugin TEXT, method TEXT)');
	$conn->exec("INSERT INTO plugin_db_changes (plugin, method) VALUES
		('thold', 'create'), ('gone', 'create'), ('internal', 'create')");

	$conn->exec('CREATE TABLE plugin_realms (id INTEGER PRIMARY KEY, plugin TEXT, display TEXT)');
	$conn->exec("INSERT INTO plugin_realms (id, plugin, display) VALUES
		(1, 'thold', 'Thold'), (7, 'gone', 'Gone'), (9, 'internal', 'Internal')");

	$conn->exec('CREATE TABLE user_auth_realm (realm_id INTEGER, user_id INTEGER)');
	$conn->exec('INSERT INTO user_auth_realm VALUES (101, 1), (107, 1), (109, 1)');

	$conn->exec('CREATE TABLE user_auth_group_realm (realm_id INTEGER, group_id INTEGER)');
	$conn->exec('INSERT INTO user_auth_group_realm VALUES (101, 1), (107, 1), (109, 1)');

	return $conn;
}

function plugin_cleanup_wire(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['config']                 = $GLOBALS['config'] ?? [];
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

/**
 * Reads the first column of every row. The rows come back keyed by name, so
 * array_column($rows, 0) would silently return nothing.
 *
 * @param PDO    $conn The seeded connection.
 * @param string $sql  The query to run.
 *
 * @return array<int,mixed> The first column of every row.
 */
function plugin_cleanup_column(PDO $conn, string $sql) : array {
	$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

	return array_map(static fn (array $row) => reset($row), $rows);
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$this->conn = plugin_cleanup_seed();
	plugin_cleanup_wire($this->conn);
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('a vanished plugin loses its realm, and the grants that pointed at it', function () {
	plugin_clean_old_plugin_info();

	$realms = plugin_cleanup_column($this->conn, 'SELECT id FROM plugin_realms ORDER BY id');

	expect(array_map('intval', $realms))->toBe([1, 9]);

	// realm 7 is stored as 107 in the user tables; leaving those behind is how
	// a later plugin issued realm 7 would inherit the old grants
	expect(array_map('intval', plugin_cleanup_column($this->conn, 'SELECT realm_id FROM user_auth_realm ORDER BY realm_id')))
		->toBe([101, 109])
		->and(array_map('intval', plugin_cleanup_column($this->conn, 'SELECT realm_id FROM user_auth_group_realm ORDER BY realm_id')))
		->toBe([101, 109]);
});

test('an installed plugin keeps everything it owns', function () {
	plugin_clean_old_plugin_info();

	expect(plugin_cleanup_column($this->conn, "SELECT name FROM plugin_hooks WHERE name = 'thold'"))->toBe(['thold'])
		->and(plugin_cleanup_column($this->conn, "SELECT plugin FROM plugin_db_changes WHERE plugin = 'thold'"))->toBe(['thold'])
		->and(plugin_cleanup_column($this->conn, "SELECT plugin FROM plugin_realms WHERE plugin = 'thold'"))->toBe(['thold']);
});

test('the internal pseudo-plugin is never cleaned up', function () {
	plugin_clean_old_plugin_info();

	// 'internal' has no plugin_config row by design, so an unguarded query
	// would take Cacti's own hooks and realms with it
	expect(plugin_cleanup_column($this->conn, "SELECT name FROM plugin_hooks WHERE name = 'internal'"))->toBe(['internal'])
		->and(plugin_cleanup_column($this->conn, "SELECT plugin FROM plugin_db_changes WHERE plugin = 'internal'"))->toBe(['internal'])
		->and(plugin_cleanup_column($this->conn, "SELECT plugin FROM plugin_realms WHERE plugin = 'internal'"))->toBe(['internal']);
});

test('the hook and db_changes rows of a vanished plugin are dropped', function () {
	plugin_clean_old_plugin_info();

	expect(plugin_cleanup_column($this->conn, "SELECT name FROM plugin_hooks WHERE name = 'gone'"))->toBe([])
		->and(plugin_cleanup_column($this->conn, "SELECT plugin FROM plugin_db_changes WHERE plugin = 'gone'"))->toBe([]);
});

test('running the cleanup twice changes nothing the second time', function () {
	plugin_clean_old_plugin_info();

	$after_first = plugin_cleanup_column($this->conn, 'SELECT id FROM plugin_realms ORDER BY id');

	plugin_clean_old_plugin_info();

	expect(plugin_cleanup_column($this->conn, 'SELECT id FROM plugin_realms ORDER BY id'))->toBe($after_first);
});

/**
 * The two statements the harness re-expresses are asserted here in the form
 * that actually ships, so the rewrite above cannot hide a change to them.
 */
test('the shipped statements are MySQL multi-table deletes guarding internal', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/plugins.php');

	$start = strpos($source, 'function plugin_clean_old_plugin_info(');
	expect($start)->not->toBeFalse();

	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	expect($body)->toContain('DELETE ph')
		->and($body)->toContain('FROM plugin_hooks AS ph')
		->and($body)->toContain('DELETE pd')
		->and($body)->toContain('FROM plugin_db_changes AS pd')
		->and(substr_count($body, "'internal'"))->toBe(3);
});

test('the plugin management page runs the cleanup', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/plugins.php');

	$start = strpos($source, 'function update_show_current(');
	expect($start)->not->toBeFalse();

	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	expect($body)->toContain('plugin_clean_old_plugin_info();');
});
