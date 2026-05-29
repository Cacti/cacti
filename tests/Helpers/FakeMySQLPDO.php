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

declare(strict_types=1);

/**
 * Test-only PDO decorator that translates MySQL-specific syntax in
 * lib/database.php queries to a form sqlite::memory: can execute.
 * Only the surface used by the DB unit tests is implemented.
 *
 * The handle uses PDO::ERRMODE_EXCEPTION, so any SQL that the translate()
 * pass does not rewrite into valid SQLite syntax will raise PDOException
 * from prepare()/query()/exec(). Tests that exercise unsupported syntax
 * must catch or expect-throws explicitly.
 */
class FakeMySQLPDO extends PDO {
	public function __construct() {
		parent::__construct('sqlite::memory:');
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	public function prepare(string $query, array $options = []): PDOStatement|false {
		return parent::prepare($this->translate($query), $options);
	}

	public function query(string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
		$translated = $this->translate($statement);
		return $fetchMode === null
			? parent::query($translated)
			: parent::query($translated, $fetchMode, ...$fetchModeArgs);
	}

	public function exec(string $statement): int|false {
		return parent::exec($this->translate($statement));
	}

	private function translate(string $sql): string {
		$trim = ltrim($sql);

		// SHOW TABLES LIKE '...'
		if (preg_match('/^SHOW\s+TABLES\s+LIKE\s+(.+)$/is', $trim, $m)) {
			return "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE " . trim($m[1], '; ');
		}
		// SHOW TABLES (no LIKE)
		if (preg_match('/^SHOW\s+TABLES\b/i', $trim)) {
			return "SELECT name FROM sqlite_master WHERE type='table'";
		}
		// SHOW COLUMNS FROM table  (and "SHOW columns FROM table LIKE 'col'")
		if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+`?([A-Za-z0-9_]+)`?(?:\s+LIKE\s+(.+))?\s*;?\s*$/is', $trim, $m)) {
			$table = $m[1];
			$like = isset($m[2]) ? trim($m[2], "'\" ;") : null;
			// sqlite pragma returns rows with: cid, name, type, notnull, dflt_value, pk
			// db_column_exists / db_get_table_column_types only need: Field (name), Type
			// Use a SELECT over pragma_table_info to project MySQL-shaped column names.
			$where = $like !== null ? ' WHERE name = ' . $this->quote($like) : '';
			return "SELECT name AS Field, type AS Type, CASE WHEN \"notnull\" = 0 THEN 'YES' ELSE 'NO' END AS \"Null\", '' AS Collation, '' AS Key, dflt_value AS \"Default\", '' AS Extra FROM pragma_table_info('$table')$where";
		}
		// INSERT ... ON DUPLICATE KEY UPDATE ...  -> INSERT OR REPLACE INTO ...
		if (preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]+)\)\s*VALUES\s*(.+?)\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\b.*$/is', $trim, $m)) {
			return "INSERT OR REPLACE INTO `{$m[1]}` ({$m[2]}) VALUES {$m[3]}";
		}
		// SHOW INDEXES FROM table
		if (preg_match('/^SHOW\s+(?:INDEX|INDEXES|KEYS)\s+FROM\s+`?([A-Za-z0-9_]+)`?\s*;?\s*$/i', $trim, $m)) {
			return "SELECT name AS Key_name FROM sqlite_master WHERE type='index' AND tbl_name='{$m[1]}'";
		}
		return $sql;
	}
}
