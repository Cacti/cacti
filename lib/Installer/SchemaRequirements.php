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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

/** Reads table/column requirements from the trusted, shipped Cacti schema. */
final readonly class SchemaRequirements {
	/** @param array<string, list<string>> $tables */
	private function __construct(public array $tables) {
	}

	public static function fromSql(string $sql) : self {
		preg_match_all('/^CREATE TABLE `([a-zA-Z0-9_]+)` \((.*?)\)\s+ENGINE=/ms', $sql, $matches, PREG_SET_ORDER);
		$tables = [];

		foreach ($matches as $table) {
			// Column names may be mixed case or hyphenated; index and constraint lines start with a keyword.
			preg_match_all('/^[ \t]+(?:`([A-Za-z][A-Za-z0-9_-]*)`|(?!(?:PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FULLTEXT|SPATIAL|FOREIGN|CHECK)\b)([A-Za-z][A-Za-z0-9_-]*))[ \t]+[a-zA-Z]/mi', $table[2], $columns, PREG_SET_ORDER);
			$names = array_map(static fn (array $column) : string => $column[2] ?? $column[1] ?? '', $columns);

			if ($names === []) {
				throw new \InvalidArgumentException('Cacti schema table has no columns');
			}
			$tables[$table[1]] = $names;
		}

		preg_match_all('/^CREATE TABLE /m', $sql, $declarations);

		if (count($declarations[0]) !== count($tables)) {
			throw new \InvalidArgumentException('Cacti schema contains an incomplete table definition');
		}

		if ($tables === []) {
			throw new \InvalidArgumentException('Cacti schema contains no table definitions');
		}

		return new self($tables);
	}

	/** @param list<array{TABLE_NAME: string, COLUMN_NAME: string}> $columns
	 * @return list<string>
	 */
	public function missing(array $columns) : array {
		$actual = [];

		foreach ($columns as $column) {
			$actual[$column['TABLE_NAME']][$column['COLUMN_NAME']] = true;
		}
		$missing = [];

		foreach ($this->tables as $table => $required) {
			foreach ($required as $column) {
				if (!isset($actual[$table][$column])) {
					$missing[] = $table . '.' . $column;
				}
			}
		}

		return $missing;
	}
}
