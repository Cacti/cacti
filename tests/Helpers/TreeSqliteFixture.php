<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * In-memory SQLite stand-in for the graph tree and permission tables.
 *
 * Tree tests call the real lib/auth.php, lib/api_tree.php and lib/html_tree.php
 * functions. reset() makes an SQLite database, through TreeSqlitePDO, the
 * default db_* connection, so every statement those functions run goes to
 * this schema. Each statement is counted, and a query budget turns a
 * non-terminating walk into a test failure instead of a hung suite.
 *
 * Statements against the settings tables and SHOW statements belong to the
 * configuration and schema layers that read_config_option(), set_config_option()
 * and sql_save() use; they count toward the budget but not toward $queries,
 * $writes or $log, and are recorded in $config_log instead.
 *
 * Call restore() after each test: a fixture connection left in place would
 * answer every later database call of the suite.
 */

require_once __DIR__ . '/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';
require_once dirname(__DIR__, 2) . '/lib/api_tree.php';
require_once dirname(__DIR__, 2) . '/lib/html_tree.php';

if (!class_exists('TreeSqlitePDO')) {
	class TreeSqlitePDO extends FakeMySQLPDO {
		public bool $counting = true;

		public function prepare(string $query, array $options = []): PDOStatement|false {
			if ($this->counting) {
				TreeSqliteFixture::record($query);
			}

			return parent::prepare($this->translateTree($query), $options);
		}

		public function exec(string $statement): int|false {
			return parent::exec($this->translateTree($statement));
		}

		/**
		 * MySQL syntax the tree and permission code uses beyond what
		 * FakeMySQLPDO already rewrites.
		 */
		private function translateTree(string $sql): string {
			// IF(cond, a, b) is IIF() in SQLite; IFNULL( is left alone
			$sql = preg_replace('/\bIF\s*\(/i', 'IIF(', $sql);

			$sql = preg_replace('/\bFROM_UNIXTIME\s*\(([^)]+)\)/i', "datetime($1, 'unixepoch')", $sql);

			// double quoted string literals, e.g. gt.enabled="on" and h.disabled = ""
			$sql = preg_replace('/(=|!=|<>)\s*"([A-Za-z0-9_]*)"/', "$1 '$2'", $sql);

			// MySQL hands out the next AUTO_INCREMENT value for an id of 0, as sql_save() expects
			return preg_replace('/^(\s*INSERT\s+INTO\s+`?\w+`?\s*\(\s*`?id`?\s*,.*?\)\s*VALUES\s*\()\s*0\s*,/is', '$1NULL,', $sql);
		}
	}
}

if (!class_exists('TreeSqliteFixture')) {
	final class TreeSqliteFixture {
		public static ?TreeSqlitePDO $pdo = null;

		public static int $queries = 0;

		public static int $writes = 0;

		public static int $statements = 0;

		public static int $budget = 100000;

		/** @var array<int, string> */
		public static array $log = [];

		/** @var array<int, string> */
		public static array $config_log = [];

		private static int $generation = 0;

		/** @var array<int, mixed>|null */
		private static ?array $saved = null;

		public static function reset(int $budget = 100000) : TreeSqlitePDO {
			global $config, $database_sessions, $database_hostname, $database_port, $database_default, $_CACTI_REQUEST;

			if (self::$saved === null) {
				self::$saved = [
					$database_sessions, $database_hostname, $database_port, $database_default,
					$config[OPTIONS_CLI] ?? null, $_SESSION ?? null, $_REQUEST, $_GET, $_POST, $_CACTI_REQUEST ?? null,
				];
			}

			$pdo = new TreeSqlitePDO();

			$pdo->counting = false;

			$pdo->exec("CREATE TABLE graph_tree_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				parent INTEGER NOT NULL DEFAULT 0,
				position INTEGER DEFAULT NULL,
				graph_tree_id INTEGER NOT NULL DEFAULT 0,
				local_graph_id INTEGER NOT NULL DEFAULT 0,
				title TEXT NOT NULL DEFAULT '',
				host_id INTEGER NOT NULL DEFAULT 0,
				site_id INTEGER NOT NULL DEFAULT 0,
				host_grouping_type INTEGER NOT NULL DEFAULT 1,
				sort_children_type INTEGER NOT NULL DEFAULT 0)");
			$pdo->exec('CREATE INDEX gti_parent ON graph_tree_items (graph_tree_id, parent, position)');
			$pdo->exec("CREATE TABLE graph_tree (id INTEGER PRIMARY KEY, enabled TEXT NOT NULL DEFAULT 'on',
				sort_type INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL DEFAULT '', sequence INTEGER NOT NULL DEFAULT 1)");
			$pdo->exec("CREATE TABLE host (id INTEGER PRIMARY KEY, description TEXT NOT NULL DEFAULT '',
				hostname TEXT NOT NULL DEFAULT '', site_id INTEGER NOT NULL DEFAULT 0, host_template_id INTEGER NOT NULL DEFAULT 0,
				location TEXT NOT NULL DEFAULT '', disabled TEXT NOT NULL DEFAULT '', deleted TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE host_template (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE graph_templates (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE graph_templates_graph (local_graph_id INTEGER NOT NULL, title_cache TEXT NOT NULL DEFAULT '',
				width INTEGER NOT NULL DEFAULT 0, height INTEGER NOT NULL DEFAULT 0)");
			$pdo->exec("CREATE TABLE graph_local (id INTEGER PRIMARY KEY, host_id INTEGER NOT NULL DEFAULT 0,
				graph_template_id INTEGER NOT NULL DEFAULT 0, snmp_query_id INTEGER NOT NULL DEFAULT 0,
				snmp_query_graph_id INTEGER NOT NULL DEFAULT 0, snmp_index TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
			$pdo->exec('CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY, local_graph_id INTEGER NOT NULL DEFAULT 0)');
			$pdo->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER NOT NULL, local_graph_id INTEGER NOT NULL)');
			$pdo->exec('CREATE TABLE user_auth (id INTEGER PRIMARY KEY, policy_graphs INTEGER NOT NULL DEFAULT 1,
				policy_trees INTEGER NOT NULL DEFAULT 1, policy_hosts INTEGER NOT NULL DEFAULT 1,
				policy_graph_templates INTEGER NOT NULL DEFAULT 1)');
			$pdo->exec('CREATE TABLE user_auth_perms (user_id INTEGER NOT NULL, item_id INTEGER NOT NULL, type INTEGER NOT NULL,
				PRIMARY KEY (user_id, item_id, type))');
			$pdo->exec("CREATE TABLE user_auth_group (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '',
				enabled TEXT NOT NULL DEFAULT 'on', policy_graphs INTEGER NOT NULL DEFAULT 1,
				policy_trees INTEGER NOT NULL DEFAULT 1, policy_hosts INTEGER NOT NULL DEFAULT 1,
				policy_graph_templates INTEGER NOT NULL DEFAULT 1)");
			$pdo->exec('CREATE TABLE user_auth_group_members (group_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
			$pdo->exec('CREATE TABLE user_auth_group_perms (group_id INTEGER NOT NULL, item_id INTEGER NOT NULL, type INTEGER NOT NULL,
				PRIMARY KEY (group_id, item_id, type))');
			$pdo->exec('CREATE TABLE user_auth_row_cache (user_id INTEGER NOT NULL, class TEXT NOT NULL, hash TEXT NOT NULL,
				total_rows INTEGER NOT NULL DEFAULT 0, time TEXT, PRIMARY KEY (user_id, class, hash))');
			$pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

			$pdo->counting = true;

			$database_hostname = 'tree_sqlite_fixture';
			$database_port     = '0';
			$database_default  = 'tree';

			$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;

			db_column_type_cache_reset();

			// read_config_option() answers from this cache before it asks the
			// database; a fresh time_last_change_branch keeps the per-request
			// tree visibility cache from answering for an earlier database
			$config[OPTIONS_CLI] = [
				'auth_method'             => 1,
				'graph_auth_method'       => 1,
				'log_validation'          => '',
				'dsstats_enable'          => '',
				'time_last_change_branch' => 'fixture-' . ++self::$generation,
			];

			// the permission caches describe the previous database
			unset($_SESSION[SESS_TREE_PERMS], $_SESSION[SESS_SIMPLE_PERMS]);

			self::$pdo        = $pdo;
			self::$budget     = $budget;
			self::$statements = 0;

			self::resetCounters();

			return $pdo;
		}

		/**
		 * Put back the connection, options, session and request this fixture replaced.
		 */
		public static function restore() : void {
			global $config, $database_sessions, $database_hostname, $database_port, $database_default, $_CACTI_REQUEST;

			if (self::$saved === null) {
				return;
			}

			[$database_sessions, $database_hostname, $database_port, $database_default, $options,
				$session, $_REQUEST, $_GET, $_POST, $_CACTI_REQUEST] = self::$saved;

			if ($options === null) {
				unset($config[OPTIONS_CLI]);
			} else {
				$config[OPTIONS_CLI] = $options;
			}

			if ($session === null) {
				$_SESSION = [];
			} else {
				$_SESSION = $session;
			}

			self::$saved = null;
			self::$pdo   = null;
		}

		public static function resetCounters() : void {
			self::$queries    = 0;
			self::$writes     = 0;
			self::$log        = [];
			self::$config_log = [];
		}

		/**
		 * Count one statement handed to the fixture connection.
		 */
		public static function record(string $sql) : void {
			self::$statements++;

			if (self::$statements > self::$budget) {
				throw new RuntimeException('query budget of ' . self::$budget . ' exceeded');
			}

			if (preg_match('/^\s*SHOW\s/i', $sql) || preg_match('/\b(FROM|INTO|UPDATE)\s+`?settings(_user)?`?(\s|$)/i', $sql)) {
				self::$config_log[] = $sql;

				return;
			}

			self::$queries++;

			if (!preg_match('/^\s*(SELECT|\()/i', $sql)) {
				self::$writes++;
			}

			self::$log[] = $sql;
		}

		/**
		 * Start a new tree generation, as a tree change does in production, so
		 * the per-request tree visibility cache is not consulted.
		 */
		public static function touch() : void {
			global $config;

			$config[OPTIONS_CLI]['time_last_change_branch'] = 'fixture-' . ++self::$generation;
		}

		/**
		 * @param array<string, int> $policies user_auth columns to set
		 */
		public static function user(int $id, array $policies = []) : void {
			$row = ['id' => $id] + $policies;

			self::insert('user_auth', [$row]);
		}

		/**
		 * @param array<int, array<string, mixed>> $rows
		 */
		public static function insert(string $table, array $rows) : void {
			self::$pdo->counting = false;

			try {
				foreach ($rows as $row) {
					$columns = array_keys($row);

					$statement = self::$pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')
						VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
					$statement->execute(array_values($row));
				}
			} finally {
				self::$pdo->counting = true;
			}
		}

		/**
		 * @param array<int, array<string, mixed>> $rows
		 */
		public static function items(array $rows) : void {
			self::insert('graph_tree_items', $rows);
		}

		/**
		 * @return array<int, int> id => parent
		 */
		public static function parents() : array {
			$parents = [];

			foreach (self::$pdo->query('SELECT id, parent FROM graph_tree_items ORDER BY id') as $row) {
				$parents[(int) $row['id']] = (int) $row['parent'];
			}

			return $parents;
		}

		/**
		 * @return array<int, string> titles of one parent's children in position order
		 */
		public static function childTitles(int $parent, int $tree_id = 1) : array {
			$statement = self::$pdo->query('SELECT title FROM graph_tree_items
				WHERE parent = ' . $parent . ' AND graph_tree_id = ' . $tree_id . ' ORDER BY position, id');

			return $statement->fetchAll(PDO::FETCH_COLUMN);
		}
	}
}
