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

declare(strict_types = 1);

/**
 * Test-only PDOStatement for sqlite handles. sqlite reports rowCount() as 0
 * for SELECT, and db_fetch_row_return() reads that as no rows at all, so the
 * result set is buffered on execute() and counted from there. The buffer
 * drains the cursor, so fetch(), fetchAll() and fetchColumn() are served from
 * it too. Install it with PDO::ATTR_STATEMENT_CLASS.
 */
class BufferedPDOStatement extends PDOStatement {
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
			PDO::FETCH_NUM    => array_values($row),
			PDO::FETCH_BOTH   => $row + array_values($row),
			PDO::FETCH_COLUMN => reset($row),
			PDO::FETCH_OBJ    => (object) $row,
			default           => $row,
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
