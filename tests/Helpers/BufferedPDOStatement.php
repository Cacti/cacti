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

	/** @var int next buffered row fetch() or fetchColumn() returns */
	private int $cursor = 0;

	/**
	 * PDO creates the statement through ATTR_STATEMENT_CLASS, which needs a
	 * non-public constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Executes the statement and buffers a SELECT result for counting.
	 *
	 * @param array<int|string,mixed>|null $params Bound parameter values
	 *
	 * @return bool True when the statement executed
	 */
	public function execute(?array $params = null) : bool {
		$executed = parent::execute($params);

		$this->rows   = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;
		$this->cursor = 0;

		return $executed;
	}

	/**
	 * Counts buffered SELECT rows, or defers to the driver for other statements.
	 *
	 * @return int The number of rows
	 */
	public function rowCount() : int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	/**
	 * Returns every buffered row in the requested shape.
	 *
	 * @param int   $mode    A PDO::FETCH_* mode
	 * @param mixed ...$args Mode arguments passed to the driver when nothing is buffered
	 *
	 * @return array<int,mixed> The rows
	 */
	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args) : array {
		if ($this->rows === null) {
			return parent::fetchAll($mode, ...$args);
		}

		return $mode === PDO::FETCH_NUM ? array_map('array_values', $this->rows) : $this->rows;
	}

	/**
	 * Returns the next buffered row in the requested shape.
	 *
	 * @param int $mode              A PDO::FETCH_* mode
	 * @param int $cursorOrientation Passed to the driver when nothing is buffered
	 * @param int $cursorOffset      Passed to the driver when nothing is buffered
	 *
	 * @return mixed The row, or false after the last one
	 */
	public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0) : mixed {
		if ($this->rows === null) {
			return parent::fetch($mode, $cursorOrientation, $cursorOffset);
		}

		$row = $this->rows[$this->cursor++] ?? null;

		if ($row === null) {
			return false;
		}

		switch ($mode) {
			case PDO::FETCH_NUM:
				return array_values($row);
			case PDO::FETCH_BOTH:
				return $row + array_values($row);
			case PDO::FETCH_COLUMN:
				return reset($row);
			case PDO::FETCH_OBJ:
				return (object) $row;
			default:
				return $row;
		}
	}

	/**
	 * Returns one column of the next buffered row.
	 *
	 * @param int $column Zero-based column index
	 *
	 * @return mixed The value, or false after the last row
	 */
	public function fetchColumn(int $column = 0) : mixed {
		if ($this->rows === null) {
			return parent::fetchColumn($column);
		}

		$row = $this->rows[$this->cursor++] ?? null;

		return $row === null ? false : (array_values($row)[$column] ?? false);
	}
}
