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
 * Standalone contract test for Cacti\Repository\DeviceRepository.
 *
 * It needs neither a database nor Composer's autoloader nor Pest: it defines
 * tiny stubs for the db_*_prepared() helpers that record the exact SQL text
 * and bound parameters each call receives, then drives the repository and
 * asserts on what it would have sent to the database.
 *
 * Run it directly:
 *     php tests/standalone/DeviceRepositoryStandaloneTest.php
 *
 * It proves the structural SQL-injection guarantees:
 *   - find()/all()/save() emit parameterized statements (values bound, not
 *     interpolated),
 *   - an injection payload supplied as a criteria *value* travels in the bound
 *     parameter list and never appears in the SQL string,
 *   - the column and ORDER BY allowlists reject identifier-level injection
 *     attempts by throwing.
 */

declare(strict_types = 1);

error_reporting(E_ALL);

/* ---- recorder shared with the db_* stubs ------------------------------- */

$GLOBALS['__calls'] = [];

/* ---- minimal Cacti runtime stubs --------------------------------------- */

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a): int {
		return ($a === null || $a === false) ? 0 : count($a);
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared(string $sql, array $params = [], ...$rest) {
		$GLOBALS['__calls'][] = ['fn' => 'row', 'sql' => $sql, 'params' => $params];

		// Simulate: id 1 exists, everything else is absent.
		if ($params === [1] || $params === ['router-01.example.net']) {
			return ['id' => 1, 'description' => 'Core Router', 'hostname' => 'router-01.example.net'];
		}

		return [];
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared(string $sql, array $params = [], ...$rest) {
		$GLOBALS['__calls'][] = ['fn' => 'assoc', 'sql' => $sql, 'params' => $params];

		return [['id' => 1], ['id' => 2]];
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared(string $sql, array $params = [], ...$rest) {
		$GLOBALS['__calls'][] = ['fn' => 'exec', 'sql' => $sql, 'params' => $params];

		return true;
	}
}

if (!function_exists('db_fetch_insert_id')) {
	function db_fetch_insert_id(...$rest) {
		return 4584;
	}
}

require_once dirname(__DIR__, 2) . '/lib/Repository/DeviceRepository.php';

use Cacti\Repository\DeviceRepository;

/* ---- a very small assertion harness ------------------------------------ */

$failures = 0;
$passed   = 0;

function check(string $label, bool $cond): void {
	global $failures, $passed;

	if ($cond) {
		$passed++;
		fwrite(STDOUT, "  ok   - $label\n");
	} else {
		$failures++;
		fwrite(STDOUT, "  FAIL - $label\n");
	}
}

function last_call(): array {
	return end($GLOBALS['__calls']);
}

function reset_calls(): void {
	$GLOBALS['__calls'] = [];
}

$repo = DeviceRepository::instance();

/* ---- find() ------------------------------------------------------------ */

reset_calls();
$dev = $repo->find(1);
$c   = last_call();
check('find() issues exactly SELECT * FROM host WHERE id = ?', $c['sql'] === 'SELECT * FROM host WHERE id = ?');
check('find() binds the id as the sole parameter', $c['params'] === [1]);
check('find() returns the typed row array', is_array($dev) && $dev['id'] === 1);

reset_calls();
$missing = $repo->find(999999);
check('find() returns null when the device is absent', $missing === null);

/* ---- findByHostname() -------------------------------------------------- */

reset_calls();
$byname = $repo->findByHostname('router-01.example.net');
$c      = last_call();
check('findByHostname() parameterizes the hostname', $c['sql'] === 'SELECT * FROM host WHERE hostname = ? LIMIT 1' && $c['params'] === ['router-01.example.net']);
check('findByHostname() returns the typed row', is_array($byname) && $byname['hostname'] === 'router-01.example.net');

/* ---- all(): criteria are bound, not interpolated ----------------------- */

reset_calls();
$injection = "1 OR 1=1; DROP TABLE host; --";
$rows      = $repo->all(['poller_id' => 2, 'disabled' => $injection], 'description DESC', 50);
$c         = last_call();

check(
	'all() builds a parameterized WHERE with placeholders only',
	$c['sql'] === 'SELECT * FROM host WHERE `poller_id` = ? AND `disabled` = ? ORDER BY `description` DESC LIMIT 50'
);
check('all() binds every criteria value in order', $c['params'] === [2, $injection]);
check('all(): the SQL injection payload is NOT present in the SQL text', !str_contains($c['sql'], 'DROP TABLE'));
check('all(): the injection payload IS present as a bound parameter', in_array($injection, $c['params'], true));
check('all() returns an array of rows', is_array($rows) && cacti_sizeof($rows) === 2);

reset_calls();
$all = $repo->all();
$c   = last_call();
check('all() with no criteria selects the whole table unparameterized-but-safe', $c['sql'] === 'SELECT * FROM host' && $c['params'] === []);

/* ---- allowlist rejects identifier-level injection ---------------------- */

$threw = false;
try {
	$repo->all(['poller_id = 1 OR 1=1 -- ' => 'x']);
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('all() rejects an injected criteria column name (allowlist)', $threw);

$threw = false;
try {
	$repo->all([], 'description; DROP TABLE host');
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('all() rejects an injected ORDER BY expression (allowlist)', $threw);

$threw = false;
try {
	$repo->all([], 'description HAXX');
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('all() rejects an invalid sort direction', $threw);

$threw = false;
try {
	$repo->all([], 'id ASC, poller_id DESC');
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('all() accepts a valid multi-column ORDER BY', !$threw);

/* ---- save(): insert path ----------------------------------------------- */

reset_calls();
$newId = $repo->save(['description' => 'New Device', 'hostname' => '10.0.0.5', 'poller_id' => 1]);
$c     = last_call();
check(
	'save() insert emits a parameterized INSERT with placeholders only',
	$c['fn'] === 'exec'
		&& $c['sql'] === 'INSERT INTO host (`description`, `hostname`, `poller_id`) VALUES (?, ?, ?)'
);
check('save() insert binds all values in column order', $c['params'] === ['New Device', '10.0.0.5', 1]);
check('save() insert returns the new auto-increment id', $newId === 4584);

/* ---- save(): update path ----------------------------------------------- */

reset_calls();
$updId = $repo->save(['id' => 7, 'description' => "x'; DROP TABLE host; --", 'disabled' => 'on']);
$c     = last_call();
check(
	'save() update emits a parameterized UPDATE with placeholders only',
	$c['fn'] === 'exec'
		&& $c['sql'] === 'UPDATE host SET `description` = ?, `disabled` = ? WHERE id = ?'
);
check('save() update binds values + id, injection payload stays a parameter', $c['params'] === ["x'; DROP TABLE host; --", 'on', 7]);
check('save() update: no payload leaked into SQL text', !str_contains($c['sql'], 'DROP TABLE'));
check('save() update returns the existing id', $updId === 7);

/* ---- save(): allowlist + empty guards ---------------------------------- */

$threw = false;
try {
	$repo->save(['description' => 'ok', 'evil_col' => 1]);
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('save() rejects an unknown column (allowlist)', $threw);

$threw = false;
try {
	$repo->save([]);
} catch (InvalidArgumentException $e) {
	$threw = true;
}
check('save() rejects an empty field set', $threw);

/* ---- report ------------------------------------------------------------ */

fwrite(STDOUT, "\n$passed passed, $failures failed\n");

exit($failures === 0 ? 0 : 1);
