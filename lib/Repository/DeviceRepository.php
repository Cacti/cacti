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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Repository;

use InvalidArgumentException;

/**
 * DeviceRepository - typed, parameterized-by-construction data access for the
 * `host` table (Cacti "devices").
 *
 * This is a reference implementation of the repository pattern proposed for
 * draining the ~4,500 direct db_fetch_*()/db_execute*() call sites spread
 * across Cacti into one place per entity.  The security goal is structural,
 * not incidental: every value that reaches the database does so as a bound
 * parameter, and every SQL identifier (column name, ORDER BY term) is checked
 * against an in-class allowlist before it is ever concatenated into a query.
 * There is no code path through this class by which a caller-supplied string
 * can become SQL text, which closes the SQL-injection class (CWE-89) by
 * construction rather than by per-call-site discipline.
 *
 * No new dependency is introduced: the methods delegate to Cacti's existing
 * PDO-backed db_*_prepared() helpers, so they degrade to exactly the queries
 * the hand-written call sites already issue, and inherit Cacti's connection,
 * logging and error handling unchanged.
 */
class DeviceRepository {
	/** Table backing this repository. A constant, never caller-supplied. */
	private const TABLE = 'host';

	/**
	 * Allowlist of every column on the `host` table.  This is the single
	 * source of truth for which identifiers this class will place into SQL
	 * text.  A column not in this set cannot appear in a WHERE, ORDER BY or
	 * write statement - the request is rejected before any query is built.
	 *
	 * Kept as a set (column => true) for O(1) membership checks.
	 *
	 * @var array<string,bool>
	 */
	private const COLUMNS = [
		'id'                     => true,
		'poller_id'              => true,
		'site_id'                => true,
		'host_template_id'       => true,
		'description'            => true,
		'hostname'               => true,
		'location'               => true,
		'notes'                  => true,
		'external_id'            => true,
		'snmp_options'           => true,
		'snmp_community'         => true,
		'snmp_version'           => true,
		'snmp_username'          => true,
		'snmp_password'          => true,
		'snmp_auth_protocol'     => true,
		'snmp_priv_passphrase'   => true,
		'snmp_priv_protocol'     => true,
		'snmp_context'           => true,
		'snmp_engine_id'         => true,
		'snmp_port'              => true,
		'snmp_timeout'           => true,
		'snmp_retries'           => true,
		'snmp_sysDescr'          => true,
		'snmp_sysObjectID'       => true,
		'snmp_sysUpTimeInstance' => true,
		'snmp_sysContact'        => true,
		'snmp_sysName'           => true,
		'snmp_sysLocation'       => true,
		'availability_method'    => true,
		'ping_method'            => true,
		'ping_port'              => true,
		'ping_timeout'           => true,
		'ping_retries'           => true,
		'max_oids'               => true,
		'bulk_walk_size'         => true,
		'device_threads'         => true,
		'deleted'                => true,
		'disabled'               => true,
		'graphs'                 => true,
		'data_sources'           => true,
		'status'                 => true,
		'status_event_count'     => true,
		'status_fail_date'       => true,
		'status_rec_date'        => true,
		'status_options_date'    => true,
		'status_last_error'      => true,
		'min_time'               => true,
		'max_time'               => true,
		'cur_time'               => true,
		'avg_time'               => true,
		'polling_time'           => true,
		'current_errors'         => true,
		'total_polls'            => true,
		'failed_polls'           => true,
		'availability'          => true,
		'last_updated'           => true,
		'created'                => true,
	];

	/** Process-wide shared instance (optional convenience, see instance()). */
	private static ?self $instance = null;

	/**
	 * instance - convenience accessor so legacy procedural call sites can reach
	 * the repository without threading an object through their signatures:
	 *
	 *     DeviceRepository::instance()->find($host_id);
	 *
	 * The class is equally usable as `new DeviceRepository()`; it holds no
	 * mutable state, so the shared instance is purely to avoid repeated
	 * allocation at hot call sites.
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * find - load a single device by its primary key.
	 *
	 * @param int $id The host.id to load.
	 *
	 * @return array<string,mixed>|null The device row, or null when no such
	 *                                  device exists (or the query errored).
	 */
	public function find(int $id): ?array {
		$row = db_fetch_row_prepared(
			'SELECT * FROM ' . self::TABLE . ' WHERE id = ?',
			[$id]
		);

		return (is_array($row) && cacti_sizeof($row)) ? $row : null;
	}

	/**
	 * findByHostname - load a single device by its hostname.
	 *
	 * @param string $hostname The host.hostname to match exactly.
	 *
	 * @return array<string,mixed>|null The first matching device row, or null.
	 */
	public function findByHostname(string $hostname): ?array {
		$row = db_fetch_row_prepared(
			'SELECT * FROM ' . self::TABLE . ' WHERE hostname = ? LIMIT 1',
			[$hostname]
		);

		return (is_array($row) && cacti_sizeof($row)) ? $row : null;
	}

	/**
	 * all - load devices matching an equality criteria set.
	 *
	 * @param array<string,mixed> $criteria Column => value equality filters.
	 *                                       Each key must be a known `host`
	 *                                       column; each value is bound, never
	 *                                       interpolated.  An empty set selects
	 *                                       all devices.
	 * @param string|null         $order    Optional ORDER BY expression, e.g.
	 *                                       "description" or "poller_id DESC,
	 *                                       description ASC".  Every term is
	 *                                       validated against the column
	 *                                       allowlist; direction must be ASC or
	 *                                       DESC.
	 * @param int|null            $limit    Optional row cap (>= 1).
	 *
	 * @return array<int,array<string,mixed>> The matching device rows.
	 *
	 * @throws InvalidArgumentException When a criteria column, an ORDER BY
	 *                                  term, or the limit is not valid.
	 */
	public function all(array $criteria = [], ?string $order = null, ?int $limit = null): array {
		$sql    = 'SELECT * FROM ' . self::TABLE;
		$params = [];

		if (cacti_sizeof($criteria)) {
			$clauses = [];

			foreach ($criteria as $column => $value) {
				// Identifier: allowlisted before it can enter SQL text.
				$this->assertColumn((string) $column);

				// Value: always a bound parameter, never interpolated.
				$clauses[] = '`' . $column . '` = ?';
				$params[]  = $value;
			}

			$sql .= ' WHERE ' . implode(' AND ', $clauses);
		}

		$order_by = $this->buildOrderBy($order);

		if ($order_by !== '') {
			$sql .= ' ORDER BY ' . $order_by;
		}

		if ($limit !== null) {
			if ($limit < 1) {
				throw new InvalidArgumentException('DeviceRepository::all(): limit must be a positive integer.');
			}

			// Cast to int: a LIMIT cannot be a bound parameter on every
			// driver, so it is forced to an integer literal.  There is no
			// string path here - $limit is typed int and range-checked.
			$sql .= ' LIMIT ' . (int) $limit;
		}

		$rows = db_fetch_assoc_prepared($sql, $params);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * save - insert a new device or update an existing one.
	 *
	 * When $fields contains a positive 'id', the matching row is updated;
	 * otherwise a new device is inserted.  Every column name is allowlisted
	 * and every value is bound - the statement is parameterized regardless of
	 * what the caller supplies.
	 *
	 * @param array<string,mixed> $fields Column => value pairs to persist.
	 *
	 * @return int The device id (existing id on update, new auto-increment id
	 *             on insert); 0 when the write failed.
	 *
	 * @throws InvalidArgumentException When $fields is empty or names a column
	 *                                  outside the allowlist.
	 */
	public function save(array $fields): int {
		if (!cacti_sizeof($fields)) {
			throw new InvalidArgumentException('DeviceRepository::save(): no fields supplied.');
		}

		foreach (array_keys($fields) as $column) {
			$this->assertColumn((string) $column);
		}

		$id = isset($fields['id']) ? (int) $fields['id'] : 0;

		if ($id > 0) {
			return $this->update($id, $fields) ? $id : 0;
		}

		return $this->insert($fields);
	}

	/**
	 * insert - INSERT a new device row from an allowlisted field set.
	 *
	 * @param array<string,mixed> $fields Already-allowlisted column => value.
	 *
	 * @return int The new auto-increment id, or 0 on failure.
	 */
	private function insert(array $fields): int {
		// An explicit zero/empty id lets the auto-increment assign the value.
		unset($fields['id']);

		if (!cacti_sizeof($fields)) {
			throw new InvalidArgumentException('DeviceRepository::save(): insert requires at least one column besides id.');
		}

		$columns      = array_keys($fields);
		$placeholders = array_fill(0, cacti_sizeof($columns), '?');

		$sql = 'INSERT INTO ' . self::TABLE
			. ' (`' . implode('`, `', $columns) . '`)'
			. ' VALUES (' . implode(', ', $placeholders) . ')';

		$result = db_execute_prepared($sql, array_values($fields));

		if ($result === false) {
			return 0;
		}

		return (int) db_fetch_insert_id();
	}

	/**
	 * update - UPDATE an existing device row from an allowlisted field set.
	 *
	 * @param int                 $id     The host.id to update.
	 * @param array<string,mixed> $fields Already-allowlisted column => value.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function update(int $id, array $fields): bool {
		// id identifies the row via the bound WHERE; it is not itself an
		// assignment target.
		unset($fields['id']);

		if (!cacti_sizeof($fields)) {
			// Nothing to change; treat as a successful no-op.
			return true;
		}

		$assignments = [];
		$params      = [];

		foreach ($fields as $column => $value) {
			$assignments[] = '`' . $column . '` = ?';
			$params[]      = $value;
		}

		$params[] = $id;

		$sql = 'UPDATE ' . self::TABLE
			. ' SET ' . implode(', ', $assignments)
			. ' WHERE id = ?';

		return db_execute_prepared($sql, $params) !== false;
	}

	/**
	 * assertColumn - reject any identifier that is not an allowlisted `host`
	 * column before it can be concatenated into SQL text.
	 *
	 * @param string $column The candidate column name.
	 *
	 * @throws InvalidArgumentException When $column is not allowlisted.
	 */
	private function assertColumn(string $column): void {
		if (!isset(self::COLUMNS[$column])) {
			throw new InvalidArgumentException(
				"DeviceRepository: unknown or disallowed column '$column'."
			);
		}
	}

	/**
	 * buildOrderBy - validate and render an ORDER BY expression from an
	 * allowlisted column list.
	 *
	 * Accepts a comma-separated list of "column [ASC|DESC]" terms.  Each
	 * column is allowlisted and each direction is restricted to ASC/DESC, so
	 * the returned fragment can only ever contain known identifiers and fixed
	 * keywords - no caller input survives into it verbatim.
	 *
	 * @param string|null $order The raw ORDER BY expression, or null/empty.
	 *
	 * @return string The validated ORDER BY body (without the "ORDER BY"
	 *                keyword), or '' when no ordering was requested.
	 *
	 * @throws InvalidArgumentException When a term is malformed, names an
	 *                                  unknown column, or uses a bad direction.
	 */
	private function buildOrderBy(?string $order): string {
		if ($order === null || trim($order) === '') {
			return '';
		}

		$terms = [];

		foreach (explode(',', $order) as $raw) {
			$parts = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

			if ($parts === false || cacti_sizeof($parts) < 1 || cacti_sizeof($parts) > 2) {
				throw new InvalidArgumentException("DeviceRepository: malformed ORDER BY term '$raw'.");
			}

			$column    = $parts[0];
			$direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';

			$this->assertColumn($column);

			if ($direction !== 'ASC' && $direction !== 'DESC') {
				throw new InvalidArgumentException("DeviceRepository: invalid sort direction '$direction'.");
			}

			$terms[] = '`' . $column . '` ' . $direction;
		}

		return implode(', ', $terms);
	}
}
