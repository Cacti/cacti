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
 * Shared sqlite fixture for the template export and import tests.  The file
 * name does not end in Test.php, so PHPUnit loads it only through require.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

/**
 * sqlite reports rowCount() as 0 for SELECT and db_fetch_row_return() guards
 * on it, so the rows are buffered at execute time and counted from there.
 */
class TemplateExchangeStatement extends PDOStatement {
	/** @var array<int,array<string,mixed>>|null rows of a SELECT, null otherwise */
	private ?array $rows = null;

	private int $cursor = 0;

	protected function __construct() {
	}

	public function execute(?array $params = null) : bool {
		$executed = parent::execute($params);

		$this->rows   = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;
		$this->cursor = 0;

		return $executed;
	}

	public function rowCount() : int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args) : array {
		if ($this->rows === null) {
			return parent::fetchAll($mode, ...$args);
		}

		return $mode === PDO::FETCH_NUM ? array_map('array_values', $this->rows) : $this->rows;
	}

	public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0) : mixed {
		if ($this->rows === null) {
			return parent::fetch($mode, $cursorOrientation, $cursorOffset);
		}

		$row = $this->rows[$this->cursor++] ?? null;

		if ($row === null) {
			return false;
		}

		return match ($mode) {
			PDO::FETCH_NUM  => array_values($row),
			PDO::FETCH_BOTH => $row + array_values($row),
			PDO::FETCH_OBJ  => (object) $row,
			default         => $row,
		};
	}

	public function fetchColumn(int $column = 0) : mixed {
		if ($this->rows === null) {
			return parent::fetchColumn($column);
		}

		$row = $this->rows[$this->cursor++] ?? null;

		return $row === null ? false : (array_values($row)[$column] ?? false);
	}
}

class TemplateExchangePDO extends FakeMySQLPDO {
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [TemplateExchangeStatement::class, []]);
	}

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		// global_constants.php may define SQL_NO_CACHE for MySQL before this runs.
		$query = preg_replace('/\bSQL_NO_CACHE\b/', '', $query);

		/* update_replication_crc() uses MySQL's REPLACE ... SET form, which
		   sqlite only accepts as a column list.  Its values hold no commas. */
		if (preg_match('/^\s*REPLACE\s+INTO\s+(\w+)\s+SET\s+(.+)$/is', $query, $matches)) {
			$columns = [];
			$values  = [];

			foreach (explode(',', $matches[2]) as $assignment) {
				[$column, $value] = array_map('trim', explode('=', $assignment, 2));
				$columns[]        = $column;
				$values[]         = $value;
			}

			$query = 'REPLACE INTO ' . $matches[1] . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
		}

		return parent::prepare($query, $options);
	}
}

/**
 * Creates $table with the fixed $columns plus one TEXT column per form field.
 *
 * @param array<int,string>   $columns Column definitions that are always present
 * @param array<string,mixed> $form    A Cacti form array whose keys become columns
 */
function template_exchange_table(PDO $conn, string $table, array $columns, array $form = []) : void {
	foreach (array_keys($form) as $field) {
		if (!preg_grep('/^' . preg_quote($field, '/') . '\b/', $columns)) {
			$columns[] = '"' . $field . '" TEXT';
		}
	}

	$conn->exec("CREATE TABLE $table (" . implode(', ', $columns) . ')');
}

/**
 * Builds the arrays the exporters and importers walk.  global_arrays.php and
 * global_form.php are written for the global scope, so its locals are published to $GLOBALS here.
 *
 * @return array<string,array{0:bool,1:mixed}> The prior state of every global set
 */
function template_exchange_load_forms() : array {
	require CACTI_PATH_INCLUDE . '/global_arrays.php';
	require CACTI_PATH_INCLUDE . '/global_form.php';

	$prior = [];

	foreach (get_defined_vars() as $name => $value) {
		if ($name === 'prior') {
			continue;
		}

		$prior[$name]   = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
		$GLOBALS[$name] = $value;
	}

	return $prior;
}

/**
 * Points lib/database.php at $conn and loads the form arrays.
 *
 * @param array<int,string> $names Further globals the caller will overwrite
 *
 * @return array<string,array{0:bool,1:mixed}> The prior state to hand to template_exchange_restore()
 */
function template_exchange_wire(PDO $conn, array $names = []) : array {
	$prior = [];

	foreach (array_merge(['database_sessions', 'database_hostname', 'database_port', 'database_default'], $names) as $name) {
		$prior[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
	}

	$GLOBALS['database_hostname'] = 'template-unit';
	$GLOBALS['database_port']     = 0;
	$GLOBALS['database_default']  = 'template-unit';
	$GLOBALS['database_sessions'] = ['template-unit:0:template-unit' => $conn];

	$conn->exec('CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT)');

	return $prior + template_exchange_load_forms();
}

/**
 * @param array<string,array{0:bool,1:mixed}> $prior
 */
function template_exchange_restore(array $prior) : void {
	foreach ($prior as $name => [$existed, $value]) {
		if ($existed) {
			$GLOBALS[$name] = $value;
		} else {
			unset($GLOBALS[$name]);
		}
	}
}

/**
 * Runs $callback with cacti_log() pointed at a temporary file and returns what
 * was logged, restoring the cached log options afterwards.
 */
function template_exchange_capture_log(callable $callback, int $verbosity = POLLER_VERBOSITY_LOW) : string {
	global $config;

	$log_file = tempnam(sys_get_temp_dir(), 'cacti-template-log-');
	$saved    = [];

	foreach (['path_cactilog' => $log_file, 'log_destination' => 1, 'log_verbosity' => $verbosity, 'selective_debug' => '', 'selective_plugin_debug' => ''] as $name => $value) {
		$saved[$name]               = $config[OPTIONS_CLI][$name] ?? null;
		$config[OPTIONS_CLI][$name] = $value;
	}

	try {
		$callback();

		return (string) file_get_contents($log_file);
	} finally {
		foreach ($saved as $name => $value) {
			if ($value === null) {
				unset($config[OPTIONS_CLI][$name]);
			} else {
				$config[OPTIONS_CLI][$name] = $value;
			}
		}

		@unlink($log_file);
	}
}
