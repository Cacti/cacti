<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Tests\Helpers;

/**
 * Reads the CREATE TABLE statements of cacti.sql without a database, and
 * compares what it finds with the recorded baseline of known exceptions.
 */
final class CactiSqlSchema {
	/** Logical key columns that must use one type everywhere. */
	public const KEY_COLUMNS = [
		'host_id', 'user_id', 'local_data_id', 'local_graph_id', 'poller_id',
		'site_id', 'graph_template_id', 'data_template_id', 'snmp_query_id',
	];

	public const CANONICAL_KEY_TYPE = 'int(10) unsigned';

	public const ENGINES = ['innodb', 'memory'];

	/** Number of CREATE TABLE statements in the SQL text. */
	public static function countStatements(string $sql) : int {
		return preg_match_all('/^\s*CREATE TABLE\b/mi', $sql);
	}

	/**
	 * @return array<string, array{engine: string, columns: array<string, array{type: string, zero_date: bool}>, has_primary_key: bool}>
	 */
	public static function parse(string $sql) : array {
		$tables = [];

		preg_match_all('/^\s*CREATE TABLE\s+`?([\w-]+)`?\s*\(/mi', $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

		foreach ($matches as $match) {
			[$items, $end] = self::splitDefinition($sql, $match[0][1] + strlen($match[0][0]));

			$options = substr($sql, $end, strpos($sql, ';', $end) - $end);
			$engine  = preg_match('/\bENGINE\s*=\s*(\w+)/i', $options, $found) ? strtolower($found[1]) : '';
			$table   = ['engine' => $engine, 'columns' => [], 'has_primary_key' => false];

			foreach ($items as $item) {
				$line = trim($item);

				if (preg_match('/^PRIMARY\s+KEY\b/i', $line)) {
					$table['has_primary_key'] = true;
				} elseif (preg_match('/^(UNIQUE\s+(KEY|INDEX)|KEY|INDEX|FULLTEXT)\b/i', $line)) {
					continue;
				} elseif (preg_match('/^`?([\w-]+)`?\s+(\w+(?:\([^)]*\))?(?:\s+unsigned)?)/i', $line, $column)) {
					$table['columns'][strtolower($column[1])] = [
						'type'      => strtolower(preg_replace('/\s+/', ' ', $column[2])),
						'zero_date' => (bool) preg_match("/\\bdefault\\s+'0000-00-00 00:00:00'/i", $line),
					];
				}
			}

			$tables[strtolower($match[1][0])] = $table;
		}

		return $tables;
	}

	/**
	 * Splits the text after a CREATE TABLE opening parenthesis into its comma
	 * separated items, ignoring commas and parentheses inside quoted strings
	 * and nested parentheses such as decimal(10,2).
	 *
	 * @return array{0: string[], 1: int} The items and the offset after the closing parenthesis.
	 */
	private static function splitDefinition(string $sql, int $offset) : array {
		$items  = [];
		$buffer = '';
		$depth  = 1;
		$quoted = false;

		for ($i = $offset; $depth > 0; $i++) {
			$char = $sql[$i];

			if ($quoted) {
				$quoted = $char !== "'";
			} elseif ($char === "'") {
				$quoted = true;
			} elseif ($char === '(') {
				$depth++;
			} elseif ($char === ')') {
				$depth--;
			} elseif ($char === ',' && $depth === 1) {
				$items[] = $buffer;
				$buffer  = '';

				continue;
			}

			if ($depth > 0) {
				$buffer .= $char;
			}
		}

		$items[] = $buffer;

		return [$items, $i];
	}

	/**
	 * Returns one message per violation that the baseline does not cover.
	 * Baseline entries that no longer match anything are ignored, so a change
	 * that removes an exception can merge before or after the baseline edit.
	 *
	 * @param  array<string, mixed> $tables   Result of parse().
	 * @param  array<string, mixed> $baseline Decoded baseline file.
	 * @return string[]
	 */
	public static function violations(array $tables, array $baseline) : array {
		$messages = [];
		$noKey    = array_column($baseline['missing_primary_key'], 'table');
		$memory   = array_column($baseline['memory_tables'], 'table');
		$typed    = [];

		foreach ($baseline['key_column_types'] as $entry) {
			$typed[$entry['table'] . '.' . $entry['column'] . ':' . $entry['type']] = true;
		}

		foreach ($tables as $name => $table) {
			if (!$table['has_primary_key'] && !in_array($name, $noKey, true)) {
				$messages[] = "Table $name has no PRIMARY KEY. Add one to the definition in cacti.sql, or, if intentional, add the table to missing_primary_key in the baseline with a reason.";
			}

			if (!in_array($table['engine'], self::ENGINES, true)) {
				$messages[] = "Table $name uses engine '{$table['engine']}'. Use InnoDB or MEMORY in cacti.sql.";
			} elseif ($table['engine'] === 'memory' && !in_array($name, $memory, true)) {
				$messages[] = "Table $name uses the MEMORY engine. Use InnoDB in cacti.sql, or, if intentional, add the table to memory_tables in the baseline with a reason.";
			}

			$zeroDates = 0;

			foreach ($table['columns'] as $column => $definition) {
				if (in_array($column, self::KEY_COLUMNS, true)
					&& $definition['type'] !== self::CANONICAL_KEY_TYPE
					&& !isset($typed["$name.$column:{$definition['type']}"])) {
					$messages[] = "Column $name.$column is {$definition['type']}, not " . self::CANONICAL_KEY_TYPE . '. Fix the definition in cacti.sql, or, if intentional, add the table, column and type to key_column_types in the baseline with a reason.';
				}

				$zeroDates += $definition['zero_date'] ? 1 : 0;
			}

			$allowed = $baseline['zero_date_defaults'][$name] ?? 0;

			if ($zeroDates > $allowed) {
				$messages[] = "Table $name has $zeroDates columns defaulting to '0000-00-00 00:00:00', baseline allows $allowed. Use a valid default such as CURRENT_TIMESTAMP or NULL for the new column in cacti.sql, or, if intentional, raise the count for the table in zero_date_defaults in the baseline with a reason.";
			}
		}

		return $messages;
	}
}
