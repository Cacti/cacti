<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for issue #7530 (poller.php process-leveling queries
 * used a non-sargable rrd_next_step predicate).
 *
 * `rrd_next_step - $poller_interval <= 0` is algebraically equivalent to
 * `rrd_next_step <= $poller_interval`, but the arithmetic form can't use the
 * poller_id_rrd_next_step index. This test proves the rewrite selects the
 * exact same row set (including negative rrd_next_step and boundary values)
 * against a real (sqlite-backed) connection, not just that the two
 * expressions are algebraically equal on paper.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';

function poller_php_seed_rrd_next_step_rows(FakeMySQLPDO $conn, array $values) : void {
	$conn->exec('CREATE TABLE poller_item (local_data_id INTEGER PRIMARY KEY, rrd_next_step INTEGER)');

	foreach ($values as $id => $value) {
		$conn->exec("INSERT INTO poller_item (local_data_id, rrd_next_step) VALUES ($id, $value)");
	}
}

function poller_php_matching_ids(FakeMySQLPDO $conn, string $where, int $poller_interval) : array {
	$sql = str_replace('$poller_interval', (string) $poller_interval, "SELECT local_data_id FROM poller_item WHERE $where ORDER BY local_data_id");

	return array_column($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'local_data_id');
}

test('the sargable rewrite selects the same rows as the original arithmetic predicate', function () {
	// covers negatives, zero, the exact boundary, and values just past it
	$values = [1 => -100, 2 => -1, 3 => 0, 4 => 1, 5 => 299, 6 => 300, 7 => 301, 8 => 1000];

	foreach ([60, 300, 1] as $poller_interval) {
		$conn = new FakeMySQLPDO();
		poller_php_seed_rrd_next_step_rows($conn, $values);

		$original = poller_php_matching_ids($conn, 'rrd_next_step - $poller_interval <= 0', $poller_interval);
		$rewrite  = poller_php_matching_ids($conn, 'rrd_next_step <= $poller_interval', $poller_interval);

		expect($rewrite)->toBe($original);
	}
});
