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
 * Tree tests load unchanged production functions into a fixture namespace with
 * SourceSlice.php. install() declares db_* functions in that namespace, so the
 * production code resolves them before the global database layer and every
 * statement runs against this SQLite schema. Each statement is counted, and a
 * query budget turns a non-terminating walk into a test failure instead of a
 * hung suite.
 */

if (!class_exists('TreeSqliteFixture')) {
	final class TreeSqliteFixture {
		public static ?PDO $pdo = null;

		public static int $queries = 0;

		public static int $writes = 0;

		public static int $budget = 100000;

		public static int $affected = 0;

		/** @var array<int, string> */
		public static array $log = [];

		/** @var array<string, bool> */
		private static array $installed = [];

		public static function reset(int $budget = 100000) : PDO {
			$pdo = new PDO('sqlite::memory:');
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
				disabled TEXT NOT NULL DEFAULT '', deleted TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
			$pdo->exec("CREATE TABLE graph_templates_graph (local_graph_id INTEGER NOT NULL, title_cache TEXT NOT NULL DEFAULT '')");
			$pdo->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY, host_id INTEGER NOT NULL DEFAULT 0,
				graph_template_id INTEGER NOT NULL DEFAULT 0, snmp_query_id INTEGER NOT NULL DEFAULT 0)');
			$pdo->exec("CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '')");
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

			self::$pdo      = $pdo;
			self::$queries  = 0;
			self::$writes   = 0;
			self::$budget   = $budget;
			self::$affected = 0;
			self::$log      = [];

			return $pdo;
		}

		public static function resetCounters() : void {
			self::$queries = 0;
			self::$writes  = 0;
			self::$log     = [];
		}

		/**
		 * @param array<int|string, mixed> $params
		 */
		public static function run(string $sql, array $params = []) : PDOStatement {
			self::$queries++;

			if (!preg_match('/^\s*(SELECT|\()/i', $sql)) {
				self::$writes++;
			}

			self::$log[] = $sql;

			if (self::$queries > self::$budget) {
				throw new RuntimeException('query budget of ' . self::$budget . ' exceeded');
			}

			$statement = self::$pdo->prepare($sql);
			$statement->execute(array_values($params));

			self::$affected = $statement->rowCount();

			return $statement;
		}

		/**
		 * @param array<int, array<string, mixed>> $rows
		 */
		public static function items(array $rows) : void {
			foreach ($rows as $row) {
				$columns = array_keys($row);

				$statement = self::$pdo->prepare('INSERT INTO graph_tree_items (' . implode(', ', $columns) . ')
					VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
				$statement->execute(array_values($row));
			}
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
			$statement = self::$pdo->prepare('SELECT title FROM graph_tree_items
				WHERE parent = ? AND graph_tree_id = ? ORDER BY position, id');
			$statement->execute([$parent, $tree_id]);

			return $statement->fetchAll(PDO::FETCH_COLUMN);
		}

		/**
		 * Declare the db_* layer inside $namespace, backed by this fixture.
		 */
		public static function install(string $namespace) : void {
			if (isset(self::$installed[$namespace])) {
				return;
			}

			self::$installed[$namespace] = true;

			// Test-only: a fixed, checked-in declaration block; nothing here is
			// derived from request data or external input.
			eval('namespace ' . $namespace . ';
				function db_fetch_cell_prepared($sql, $params = [], $col_name = "", $log = true, $db_conn = false) {
					$row = \TreeSqliteFixture::run($sql, $params)->fetch(\PDO::FETCH_ASSOC);
					if ($row === false) { return false; }
					return $col_name != "" ? $row[$col_name] : reset($row);
				}
				function db_fetch_cell($sql, $col_name = "", $log = true, $db_conn = false) {
					return db_fetch_cell_prepared($sql, [], $col_name);
				}
				function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
					$row = \TreeSqliteFixture::run($sql, $params)->fetch(\PDO::FETCH_ASSOC);
					return $row === false ? [] : $row;
				}
				function db_fetch_row($sql, $log = true, $db_conn = false) {
					return db_fetch_row_prepared($sql);
				}
				function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
					return \TreeSqliteFixture::run($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
				}
				function db_fetch_assoc($sql, $log = true, $db_conn = false) {
					return db_fetch_assoc_prepared($sql);
				}
				function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
					\TreeSqliteFixture::run($sql, $params);
					return true;
				}
				function db_execute($sql, $log = true, $db_conn = false) {
					return db_execute_prepared($sql);
				}
				function db_affected_rows($db_conn = false) {
					return \TreeSqliteFixture::$affected;
				}
				function db_qstr($value, $db_conn = false) {
					return \TreeSqliteFixture::$pdo->quote((string) $value);
				}
				function db_begin_transaction($db_conn = false) {
					return \TreeSqliteFixture::$pdo->beginTransaction();
				}
				function db_commit_transaction($db_conn = false) {
					return \TreeSqliteFixture::$pdo->inTransaction() ? \TreeSqliteFixture::$pdo->commit() : false;
				}
				function db_rollback_transaction($db_conn = false) {
					return \TreeSqliteFixture::$pdo->inTransaction() ? \TreeSqliteFixture::$pdo->rollBack() : false;
				}');
		}
	}
}
