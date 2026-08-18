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
*/

/*
 * FakeMySQLPDO rewrote UNIX_TIMESTAMP(col) to strftime('%s', col), which
 * returns TEXT. sqlite orders every integer before every string, so an in-SQL
 * comparison between a translated timestamp and anything numeric was decided by
 * type rather than by value: "started + timeout < now" held whatever the clock
 * said. A test built on that reads as passing, not as broken, which is the
 * dangerous kind of wrong for a helper the integration suites all share.
 *
 * MySQL returns a number from UNIX_TIMESTAMP(), so the translation now casts.
 */

require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';

/**
 * Seeds one row whose timestamp is a known distance in the past.
 *
 * @param int $age How many seconds ago the row started.
 *
 * @return FakeMySQLPDO A connection holding the row.
 */
function fake_mysql_pdo_timestamp_affinity_seed(int $age) : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE t (started TEXT, timeout INTEGER)');
	$conn->exec("INSERT INTO t VALUES ('" . gmdate('Y-m-d H:i:s', time() - $age) . "', 300)");

	return $conn;
}

test('a row inside its timeout is not reported as expired', function () {
	$conn = fake_mysql_pdo_timestamp_affinity_seed(10);

	$row = $conn->query('SELECT UNIX_TIMESTAMP(started) + timeout < UNIX_TIMESTAMP() AS expired FROM t')
		->fetch(PDO::FETCH_ASSOC);

	// this is the assertion the old translation failed: 10 seconds into a 300
	// second timeout is not expired by any reading of the clock
	expect((int) $row['expired'])->toBe(0);
});

test('a row past its timeout is reported as expired', function () {
	$conn = fake_mysql_pdo_timestamp_affinity_seed(600);

	$row = $conn->query('SELECT UNIX_TIMESTAMP(started) + timeout < UNIX_TIMESTAMP() AS expired FROM t')
		->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['expired'])->toBe(1);
});

test('a translated timestamp compares as a number, not as text', function () {
	$conn = fake_mysql_pdo_timestamp_affinity_seed(10);

	$row = $conn->query('SELECT UNIX_TIMESTAMP(started) AS ts, typeof(UNIX_TIMESTAMP(started)) AS kind FROM t')
		->fetch(PDO::FETCH_ASSOC);

	expect($row['kind'])->toBe('integer')
		->and((int) $row['ts'])->toBeGreaterThan(1700000000);
});

test('the bare UNIX_TIMESTAMP() form translates as well', function () {
	$conn = fake_mysql_pdo_timestamp_affinity_seed(10);

	// left untranslated this raised "no such function: UNIX_TIMESTAMP"
	$row = $conn->query('SELECT UNIX_TIMESTAMP() AS now FROM t')->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['now'])->toBeGreaterThan(1700000000);
});

/**
 * The projection form is what the existing Boost integration test uses, and it
 * casts in PHP afterwards, so this pins that the change did not disturb it.
 */
test('reading a translated timestamp back into php still works', function () {
	$conn = fake_mysql_pdo_timestamp_affinity_seed(0);

	$row = $conn->query('SELECT UNIX_TIMESTAMP(started) AS ts FROM t')->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['ts'])->toBeGreaterThanOrEqual(time() - 5)
		->and((int) $row['ts'])->toBeLessThanOrEqual(time() + 5);
});
